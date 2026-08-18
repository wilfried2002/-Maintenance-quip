<?php

namespace App\Http\Controllers;

use App\Models\CoutEntretien;
use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\Piece;
use App\Models\PlanMaintenance;
use App\Models\Vehicule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Tableau de bord post-connexion : indicateurs et graphiques calculés à partir
     * des données réelles (aucune valeur figée). Le menu par module reste porté par
     * auth.accessibleModules / moduleDefs (voir HandleInertiaRequests) et affiché
     * dans la sidebar, pas ici.
     */
    public function index(): Response
    {
        $nbIndustriels = EquipementIndustriel::count();
        $nbVehicules = Vehicule::count();
        $nbBureau = EquipementBureau::count();

        $interventionsParStatut = Intervention::query()
            ->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $interventionsEnRetard = Intervention::query()
            ->where('statut', 'planifiee')
            ->whereNotNull('date_planifiee')
            ->where('date_planifiee', '<', now())
            ->count();

        $debutMois = now()->startOfMonth();
        $coutMoisEntretien = (float) CoutEntretien::whereBetween('date', [$debutMois, now()])->sum('montant');
        $coutMoisPieces = (float) DB::table('intervention_pieces')
            ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
            ->whereBetween('interventions.created_at', [$debutMois, now()])
            ->where('interventions.organisation_id', session('current_organisation_id'))
            ->sum(DB::raw('intervention_pieces.quantite * intervention_pieces.prix_unitaire'));

        $piecesStockBas = Piece::whereColumn('stock_qte', '<=', 'stock_min')->count();

        $plansActifs = PlanMaintenance::where('actif', true)->with('equipementable')->get();
        $plansEnRetard = $plansActifs->filter(fn (PlanMaintenance $p) => $p->en_retard)->count();

        return Inertia::render('Dashboard', [
            'kpis' => [
                'totalEquipements' => $nbIndustriels + $nbVehicules + $nbBureau,
                'equipementsParType' => [
                    'industriel' => $nbIndustriels,
                    'vehicule' => $nbVehicules,
                    'bureau' => $nbBureau,
                ],
                'interventionsEnCours' => (int) ($interventionsParStatut['en_cours'] ?? 0),
                'interventionsPlanifiees' => (int) ($interventionsParStatut['planifiee'] ?? 0),
                'interventionsEnRetard' => $interventionsEnRetard,
                'coutMois' => $coutMoisEntretien + $coutMoisPieces,
                'piecesStockBas' => $piecesStockBas,
                'plansActifs' => $plansActifs->count(),
                'plansEnRetard' => $plansEnRetard,
            ],
            'charts' => [
                'coutsParMois' => $this->coutsParMois(),
                'interventionsParStatut' => [
                    'planifiee' => (int) ($interventionsParStatut['planifiee'] ?? 0),
                    'en_cours' => (int) ($interventionsParStatut['en_cours'] ?? 0),
                    'terminee' => (int) ($interventionsParStatut['terminee'] ?? 0),
                    'annulee' => (int) ($interventionsParStatut['annulee'] ?? 0),
                ],
                'coutsParType' => $this->coutsParType(),
            ],
        ]);
    }

    /**
     * Coût total d'entretien (main d'œuvre + prestations + autre + pièces) par mois,
     * sur les 6 derniers mois.
     */
    private function coutsParMois(): array
    {
        $mois = [];

        for ($i = 5; $i >= 0; $i--) {
            $debut = now()->subMonths($i)->startOfMonth();
            $fin = now()->subMonths($i)->endOfMonth();

            $entretien = (float) CoutEntretien::whereBetween('date', [$debut, $fin])->sum('montant');
            $pieces = (float) DB::table('intervention_pieces')
                ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
                ->whereBetween('interventions.created_at', [$debut, $fin])
                ->where('interventions.organisation_id', session('current_organisation_id'))
                ->sum(DB::raw('intervention_pieces.quantite * intervention_pieces.prix_unitaire'));

            $mois[] = [
                'label' => Carbon::parse($debut)->translatedFormat('M Y'),
                'total' => round($entretien + $pieces, 2),
            ];
        }

        return $mois;
    }

    private function coutsParType(): array
    {
        $couts = CoutEntretien::select('type_cout', DB::raw('sum(montant) as total'))
            ->groupBy('type_cout')
            ->pluck('total', 'type_cout');

        $pieces = (float) DB::table('intervention_pieces')
            ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
            ->where('interventions.organisation_id', session('current_organisation_id'))
            ->sum(DB::raw('intervention_pieces.quantite * intervention_pieces.prix_unitaire'));

        return [
            'main_oeuvre' => round((float) ($couts['main_oeuvre'] ?? 0), 2),
            'pieces' => round($pieces, 2),
            'prestation_externe' => round((float) ($couts['prestation_externe'] ?? 0), 2),
            'autre' => round((float) ($couts['autre'] ?? 0), 2),
        ];
    }
}
