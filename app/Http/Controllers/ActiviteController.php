<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesPagination;
use App\Models\Activite;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Journal d'activité : qui a créé/modifié/supprimé/restauré quoi (alimenté
 * automatiquement par le trait ConsigneActivite). Réservé aux admins.
 */
class ActiviteController extends Controller
{
    use HandlesPagination;

    private const SUJETS = [
        \App\Models\Vehicule::class => 'Véhicule',
        \App\Models\EquipementIndustriel::class => 'Équipement industriel',
        \App\Models\EquipementBureau::class => 'Équipement de bureau',
        \App\Models\Intervention::class => 'Intervention',
        \App\Models\PlanMaintenance::class => 'Plan de maintenance',
        \App\Models\Piece::class => 'Pièce',
        \App\Models\Fournisseur::class => 'Fournisseur',
        \App\Models\CoutEntretien::class => 'Coût d\'entretien',
        \App\Models\MouvementStock::class => 'Mouvement de stock',
    ];

    public function index(Request $request): Response
    {
        [$tri, $sens, $parPage] = $this->parametresTri($request, ['created_at'], 'created_at');

        $recherche = $this->termeRecherche($request);
        $action = (string) $request->query('action', '');

        $activites = Activite::query()
            ->with('user:id,name')
            ->when(in_array($action, Activite::ACTIONS, true), fn ($q) => $q->where('action', $action))
            ->when($recherche !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('sujet_type', 'like', "%{$recherche}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$recherche}%"))))
            ->orderBy($tri, $sens)
            ->paginate($parPage)
            ->withQueryString();

        $activites->through(function (Activite $activite) {
            return [
                'id' => $activite->id,
                'created_at' => $activite->created_at?->toIso8601String(),
                'user' => $activite->user?->name ?? 'Système',
                'action' => $activite->action,
                'sujet' => self::SUJETS[$activite->sujet_type] ?? $activite->sujet_type,
                'sujet_id' => $activite->sujet_id,
                'changements' => $activite->changements,
            ];
        });

        return Inertia::render('Activites/Index', [
            'activites' => $activites,
            'actions' => Activite::ACTIONS,
        ]);
    }
}
