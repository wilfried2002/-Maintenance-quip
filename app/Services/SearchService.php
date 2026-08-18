<?php

namespace App\Services;

use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\Piece;
use App\Models\User;
use App\Models\Vehicule;

/**
 * Recherche globale (barre du topbar) : cherche dans les équipements des 3 modules, leurs
 * interventions et leurs pièces, en respectant les mêmes permissions par module que la
 * barre latérale (User::hasModuleAccess) — un utilisateur ne voit jamais un résultat d'un
 * module auquel il n'a pas accès.
 */
class SearchService
{
    private const MODULES = [
        'equipements_industriels' => [
            'model' => EquipementIndustriel::class,
            'route_prefix' => 'equipements-industriels',
            'label' => 'Équipements industriels',
        ],
        'parc_automobile' => [
            'model' => Vehicule::class,
            'route_prefix' => 'vehicules',
            'label' => 'Véhicules',
        ],
        'equipement_bureau' => [
            'model' => EquipementBureau::class,
            'route_prefix' => 'equipements-bureau',
            'label' => 'Équipements de bureau',
        ],
    ];

    // Les routes /{prefix}/pieces ont chacune leur propre liste de rôles (voir
    // routes/web.php), distincte de la permission générale du module équipement — un
    // magasinier a accès au stock sans avoir accès aux équipements. Répliqué ici tel
    // quel plutôt que via hasModuleAccess('pieces_stock'), qui est un flag unique et ne
    // distingue pas responsable_parc (autorisé sur vehicules.pieces uniquement).
    private const PIECES_ROLES = [
        'equipements_industriels' => ['responsable_maintenance', 'technicien', 'magasinier'],
        'parc_automobile' => ['responsable_maintenance', 'technicien', 'responsable_parc', 'magasinier'],
        'equipement_bureau' => ['responsable_maintenance', 'technicien', 'magasinier'],
    ];

    private const LIMIT = 5;

    public function search(User $user, string $q): array
    {
        $accessibleModules = array_filter(
            array_keys(self::MODULES),
            fn (string $key) => $user->hasModuleAccess($key)
        );

        $results = [];

        foreach ($accessibleModules as $moduleKey) {
            $equipements = $this->searchEquipements($moduleKey, $q);
            if ($equipements->isNotEmpty()) {
                $results[] = [
                    'group' => $moduleKey,
                    'label' => self::MODULES[$moduleKey]['label'],
                    'items' => $equipements->all(),
                ];
            }
        }

        $interventions = $this->searchInterventions($accessibleModules, $q);
        if (!empty($interventions)) {
            $results[] = ['group' => 'interventions', 'label' => 'Interventions', 'items' => $interventions];
        }

        $pieceAccessibleModules = array_filter(
            array_keys(self::PIECES_ROLES),
            fn (string $key) => $this->canAccessPieces($user, $key)
        );

        $pieces = $this->searchPieces($pieceAccessibleModules, $q);
        if (!empty($pieces)) {
            $results[] = ['group' => 'pieces', 'label' => 'Pièces', 'items' => $pieces];
        }

        return $results;
    }

    private function canAccessPieces(User $user, string $moduleKey): bool
    {
        $role = $user->getRole();

        if (in_array($role, ['super_admin', 'admin'], true)) {
            return true;
        }

        return in_array($role, self::PIECES_ROLES[$moduleKey] ?? [], true);
    }

    private function searchEquipements(string $moduleKey, string $q)
    {
        $config = self::MODULES[$moduleKey];
        $model = $config['model'];
        $prefix = $config['route_prefix'];

        $query = $model::query();

        if ($moduleKey === 'parc_automobile') {
            $query->where(fn ($w) => $w
                ->where('code', 'like', "%{$q}%")
                ->orWhere('immatriculation', 'like', "%{$q}%")
                ->orWhere('marque', 'like', "%{$q}%")
                ->orWhere('modele', 'like', "%{$q}%"));
        } else {
            $query->where(fn ($w) => $w
                ->where('code', 'like', "%{$q}%")
                ->orWhere('designation', 'like', "%{$q}%"));
        }

        return $query->limit(self::LIMIT)->get()->map(fn ($item) => [
            'id' => $item->id,
            'title' => $moduleKey === 'parc_automobile'
                ? "{$item->code} — {$item->immatriculation}"
                : "{$item->code} — {$item->designation}",
            'subtitle' => $item->statut ?? null,
            'url' => "/{$prefix}/{$item->id}",
        ]);
    }

    private function searchInterventions(array $accessibleModules, string $q): array
    {
        $results = [];

        foreach ($accessibleModules as $moduleKey) {
            $config = self::MODULES[$moduleKey];

            $interventions = Intervention::query()
                ->where('equipementable_type', $config['model'])
                ->where('titre', 'like', "%{$q}%")
                ->with('equipementable')
                ->limit(self::LIMIT)
                ->get();

            foreach ($interventions as $intervention) {
                $results[] = [
                    'id' => $intervention->id,
                    'title' => $intervention->titre,
                    'subtitle' => $intervention->equipementable?->code ?? null,
                    'url' => "/{$config['route_prefix']}/interventions?q=" . urlencode($intervention->titre),
                ];
            }
        }

        return array_slice($results, 0, self::LIMIT);
    }

    private function searchPieces(array $accessibleModules, string $q): array
    {
        if (empty($accessibleModules)) {
            return [];
        }

        return Piece::query()
            ->whereIn('module', $accessibleModules)
            ->where(fn ($w) => $w
                ->where('reference', 'like', "%{$q}%")
                ->orWhere('designation', 'like', "%{$q}%"))
            ->limit(self::LIMIT)
            ->get()
            ->map(function ($piece) {
                $prefix = self::MODULES[$piece->module]['route_prefix'] ?? null;

                return [
                    'id' => $piece->id,
                    'title' => "{$piece->reference} — {$piece->designation}",
                    'subtitle' => "Stock : {$piece->stock_qte} {$piece->unite}",
                    'url' => $prefix ? "/{$prefix}/pieces?q=" . urlencode($piece->reference) : null,
                ];
            })
            ->filter(fn ($item) => $item['url'] !== null)
            ->values()
            ->all();
    }
}
