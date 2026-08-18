<?php

namespace App\Services;

use App\Models\CoutEntretien;
use App\Models\Intervention;
use App\Models\PlanMaintenance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ModuleDashboardService
{
    /**
     * Indicateurs pour le tableau de bord d'UN module équipement (industriel,
     * véhicule ou bureau) : ne regarde que les équipements de cette classe.
     */
    public function calculer(string $class): array
    {
        $total = $class::count();

        $parStatut = $class::select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $interventions = Intervention::where('equipementable_type', $class);

        $interventionsParStatut = (clone $interventions)
            ->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $interventionsEnRetard = (clone $interventions)
            ->where('statut', 'planifiee')
            ->whereNotNull('date_planifiee')
            ->where('date_planifiee', '<', now())
            ->count();

        $coutMainOeuvre = (float) (clone $interventions)->sum('cout_main_oeuvre');
        $coutPieces = (float) DB::table('intervention_pieces')
            ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
            ->where('interventions.equipementable_type', $class)
            ->where('interventions.organisation_id', session('current_organisation_id'))
            ->sum(DB::raw('intervention_pieces.quantite * intervention_pieces.prix_unitaire'));
        $coutAutres = (float) CoutEntretien::where('equipementable_type', $class)
            ->whereIn('type_cout', ['prestation_externe', 'autre'])
            ->sum('montant');

        $plans = PlanMaintenance::where('equipementable_type', $class)
            ->where('actif', true)
            ->with('equipementable')
            ->get();
        $plansEnRetard = $plans->filter(fn (PlanMaintenance $p) => $p->en_retard)->count();

        return [
            'total' => $total,
            'parStatut' => [
                'en_service' => (int) ($parStatut['en_service'] ?? 0),
                'en_panne' => (int) ($parStatut['en_panne'] ?? 0),
                'en_maintenance' => (int) ($parStatut['en_maintenance'] ?? 0),
                'hors_service' => (int) ($parStatut['hors_service'] ?? 0),
                'reforme' => (int) ($parStatut['reforme'] ?? 0),
            ],
            'interventionsEnCours' => (int) ($interventionsParStatut['en_cours'] ?? 0),
            'interventionsPlanifiees' => (int) ($interventionsParStatut['planifiee'] ?? 0),
            'interventionsEnRetard' => $interventionsEnRetard,
            'coutTotal' => round($coutMainOeuvre + $coutPieces + $coutAutres, 2),
            'coutsParMois' => $this->coutsParMois($class),
            'plansActifs' => $plans->count(),
            'plansEnRetard' => $plansEnRetard,
            'topEquipements' => $this->topEquipementsParCout($class),
        ];
    }

    private function coutsParMois(string $class): array
    {
        $mois = [];

        for ($i = 5; $i >= 0; $i--) {
            $debut = now()->subMonths($i)->startOfMonth();
            $fin = now()->subMonths($i)->endOfMonth();

            $entretien = (float) CoutEntretien::where('equipementable_type', $class)
                ->whereBetween('date', [$debut, $fin])
                ->sum('montant');
            $pieces = (float) DB::table('intervention_pieces')
                ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
                ->where('interventions.equipementable_type', $class)
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

    /**
     * Les 5 équipements du module les plus coûteux (main d'œuvre + pièces + autres
     * coûts confondus), pour repérer d'un coup d'œil ceux qui pèsent le plus.
     */
    private function topEquipementsParCout(string $class): array
    {
        $parEquipement = [];

        CoutEntretien::where('equipementable_type', $class)
            ->select('equipementable_id', DB::raw('sum(montant) as total'))
            ->groupBy('equipementable_id')
            ->get()
            ->each(function ($row) use (&$parEquipement) {
                $parEquipement[$row->equipementable_id] = ($parEquipement[$row->equipementable_id] ?? 0) + (float) $row->total;
            });

        DB::table('intervention_pieces')
            ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
            ->where('interventions.equipementable_type', $class)
            ->where('interventions.organisation_id', session('current_organisation_id'))
            ->select('interventions.equipementable_id', DB::raw('sum(intervention_pieces.quantite * intervention_pieces.prix_unitaire) as total'))
            ->groupBy('interventions.equipementable_id')
            ->get()
            ->each(function ($row) use (&$parEquipement) {
                $parEquipement[$row->equipementable_id] = ($parEquipement[$row->equipementable_id] ?? 0) + (float) $row->total;
            });

        arsort($parEquipement);
        $top = array_slice($parEquipement, 0, 5, true);

        if (empty($top)) {
            return [];
        }

        $equipements = $class::whereIn('id', array_keys($top))->get()->keyBy('id');

        return collect($top)
            ->map(fn ($total, $id) => [
                'label' => $this->labelPourEquipement($equipements->get($id)),
                'total' => round($total, 2),
            ])
            ->values()
            ->all();
    }

    private function labelPourEquipement($equip): string
    {
        if (!$equip) {
            return '—';
        }

        return $equip->immatriculation
            ? "{$equip->code} — {$equip->immatriculation}"
            : "{$equip->code} — {$equip->designation}";
    }
}
