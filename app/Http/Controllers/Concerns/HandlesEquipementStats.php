<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CoutEntretien;
use App\Models\Intervention;
use App\Models\PlanMaintenance;
use Illuminate\Support\Facades\DB;

trait HandlesEquipementStats
{
    /**
     * Indicateurs pour la fiche détail d'UN équipement (le "dashboard intelligent"
     * de la fiche) : interventions, coûts et échéances préventives propres à cet
     * équipement uniquement.
     */
    protected function equipementStats($equipement, string $class): array
    {
        $interventions = Intervention::where('equipementable_type', $class)
            ->where('equipementable_id', $equipement->id);

        $derniereIntervention = (clone $interventions)
            ->whereNotNull('date_fin')
            ->orderByDesc('date_fin')
            ->first();

        $coutMainOeuvre = (float) (clone $interventions)->sum('cout_main_oeuvre');
        $coutPieces = (float) DB::table('intervention_pieces')
            ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
            ->where('interventions.equipementable_type', $class)
            ->where('interventions.equipementable_id', $equipement->id)
            ->where('interventions.organisation_id', session('current_organisation_id'))
            ->sum(DB::raw('intervention_pieces.quantite * intervention_pieces.prix_unitaire'));
        $coutAutres = (float) CoutEntretien::where('equipementable_type', $class)
            ->where('equipementable_id', $equipement->id)
            ->whereIn('type_cout', ['prestation_externe', 'autre'])
            ->sum('montant');

        $plans = PlanMaintenance::where('equipementable_type', $class)
            ->where('equipementable_id', $equipement->id)
            ->where('actif', true)
            ->get()
            ->each(fn (PlanMaintenance $p) => $p->setRelation('equipementable', $equipement));

        $prochaineEcheance = $plans
            ->map(fn (PlanMaintenance $p) => $p->prochaine_echeance)
            ->filter()
            ->sort()
            ->first();

        return [
            'nombreInterventions' => (clone $interventions)->count(),
            'interventionsEnCours' => (clone $interventions)->where('statut', 'en_cours')->count(),
            'interventionsPlanifiees' => (clone $interventions)->where('statut', 'planifiee')->count(),
            'derniereInterventionDate' => $derniereIntervention?->date_fin,
            'coutTotal' => round($coutMainOeuvre + $coutPieces + $coutAutres, 2),
            'prochaineEcheance' => $prochaineEcheance,
            'plansActifs' => $plans->count(),
            'plansEnRetard' => $plans->filter(fn (PlanMaintenance $p) => $p->en_retard)->count(),
        ];
    }
}
