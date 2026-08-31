<?php

namespace App\Http\Controllers;

use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\Piece;
use App\Models\Vehicule;
use App\Services\RapportPdfService;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exports PDF (fonctionnalité 9/10) : fiche équipement, étiquette QR et listes
 * (équipements / interventions / pièces). Un seul type de route par export, le
 * « type » identifiant le module — les permissions suivent exactement celles
 * des pages correspondantes (accès au module ; rôle stock pour les pièces).
 */
class RapportController extends Controller
{
    private const TYPES = [
        'vehicules' => [Vehicule::class, 'vehicules.show', 'Parc automobile'],
        'equipements-industriels' => [EquipementIndustriel::class, 'equipements-industriels.show', 'Équipements industriels'],
        'equipements-bureau' => [EquipementBureau::class, 'equipements-bureau.show', 'Équipements de bureau'],
    ];

    public function __construct(private readonly RapportPdfService $rapports)
    {
    }

    public function fiche(string $type, int $id): Response
    {
        [$classe, , $libelleModule] = $this->typeOu404($type);
        $this->autoriserModulePourClasse($classe);

        $equipement = $classe::findOrFail($id);

        return $this->pdf(
            $this->rapports->fiche($equipement, $libelleModule),
            "fiche-{$type}-{$id}.pdf"
        );
    }

    public function etiquette(string $type, int $id): Response
    {
        [$classe, $routeShow, $libelleModule] = $this->typeOu404($type);
        $this->autoriserModulePourClasse($classe);

        $equipement = $classe::findOrFail($id);
        $url = route($routeShow, $equipement->getKey());

        return $this->pdf(
            $this->rapports->etiquette($equipement, $libelleModule, $url),
            "etiquette-{$type}-{$id}.pdf"
        );
    }

    public function liste(string $type, string $quoi, Request $request): Response
    {
        [$classe, , $libelleModule] = $this->typeOu404($type);

        $recherche = trim((string) $request->query('q', ''));

        // Les listes de pièces suivent la permission stock du module, les autres
        // l'accès au module.
        if ($quoi === 'pieces') {
            abort_unless(RoleService::peutGererStockModule(Auth::user(), RoleService::modulePourClasseEquipement($classe)), 403);
        } else {
            $this->autoriserModulePourClasse($classe);
        }

        return match ($quoi) {
            'equipements' => $this->listeEquipements($type, $classe, $libelleModule, $recherche),
            'interventions' => $this->listeInterventions($type, $classe, $libelleModule, $recherche),
            'pieces' => $this->listePieces($classe, $libelleModule, $recherche),
            default => abort(404),
        };
    }

    // ── Listes ──────────────────────────────────────────────────────────────

    private function listeEquipements(string $type, string $classe, string $libelleModule, string $recherche): Response
    {
        $colonnesCode = $classe === Vehicule::class;

        $requete = $classe::query()
            ->when($recherche !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$recherche}%")
                ->orWhere($colonnesCode ? 'immatriculation' : 'designation', 'like', "%{$recherche}%")));

        $lignes = $requete
            ->orderBy($colonnesCode ? 'immatriculation' : 'designation')
            ->limit(500)
            ->get()
            ->map(fn ($e) => $colonnesCode
                ? [$e->code, $e->immatriculation, $e->marque ?? '—', $e->statut ?? '—', $e->criticite ?? '—']
                : [$e->code, $e->designation, $e->categorie ?? '—', $e->statut ?? '—', $e->criticite ?? '—'])
            ->all();

        return $this->pdf(
            $this->rapports->liste(
                $libelleModule.' — équipements'.($recherche !== '' ? ' (recherche : '.$recherche.')' : ''),
                $colonnesCode ? ['Code', 'Immatriculation', 'Marque', 'Statut', 'Criticité'] : ['Code', 'Désignation', 'Catégorie', 'Statut', 'Criticité'],
                [80, 210, 150, 110, 110],
                $lignes
            ),
            "liste-equipements-{$type}.pdf"
        );
    }

    private function listeInterventions(string $type, string $classe, string $libelleModule, string $recherche): Response
    {
        $lignes = Intervention::query()
            ->where('equipementable_type', $classe)
            ->when($recherche !== '', fn ($q) => $q->where('titre', 'like', "%{$recherche}%"))
            ->with('equipementable')
            ->orderByDesc('date_planifiee')
            ->limit(500)
            ->get()
            ->map(fn (Intervention $i) => [
                optional($i->date_planifiee)->format('d/m/Y') ?? '—',
                $i->titre,
                $i->equipementable?->immatriculation ?? $i->equipementable?->designation ?? '—',
                $i->type_intervention,
                $i->statut,
                $i->priorite,
            ])
            ->all();

        return $this->pdf(
            $this->rapports->liste(
                $libelleModule.' — interventions'.($recherche !== '' ? ' (recherche : '.$recherche.')' : ''),
                ['Date', 'Titre', 'Équipement', 'Type', 'Statut', 'Priorité'],
                [75, 230, 170, 90, 90, 80],
                $lignes
            ),
            "liste-interventions-{$type}.pdf"
        );
    }

    private function listePieces(string $classe, string $libelleModule, string $recherche): Response
    {
        $module = RoleService::modulePourClasseEquipement($classe);

        $lignes = Piece::query()
            ->where('module', $module)
            ->when($recherche !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$recherche}%")
                ->orWhere('designation', 'like', "%{$recherche}%")))
            ->orderBy('designation')
            ->limit(500)
            ->get()
            ->map(fn (Piece $p) => [
                $p->reference,
                $p->designation,
                (string) $p->stock_qte,
                (string) $p->stock_min,
                $p->prix_unitaire_moyen !== null ? number_format((float) $p->prix_unitaire_moyen, 2, ',', ' ') : '—',
                $p->fournisseur ?? '—',
            ])
            ->all();

        return $this->pdf(
            $this->rapports->liste(
                $libelleModule.' — pièces en stock'.($recherche !== '' ? ' (recherche : '.$recherche.')' : ''),
                ['Référence', 'Désignation', 'Stock', 'Seuil min.', 'Prix moyen', 'Fournisseur'],
                [90, 220, 70, 80, 100, 175],
                $lignes
            ),
            "liste-pieces-{$module}.pdf"
        );
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function typeOu404(string $type): array
    {
        return self::TYPES[$type] ?? abort(404);
    }

    private function autoriserModulePourClasse(string $classe): void
    {
        $module = RoleService::modulePourClasseEquipement($classe);

        abort_unless(Auth::user()?->hasModuleAccess($module), 403);
    }

    private function pdf(string $contenu, string $nomFichier): Response
    {
        return response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nomFichier.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
