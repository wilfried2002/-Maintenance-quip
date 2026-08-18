<?php

namespace App\Console\Commands;

use App\Models\Intervention;
use App\Models\PlanMaintenance;
use App\Models\Vehicule;
use Illuminate\Console\Command;

class GenerateMaintenancePreventive extends Command
{
    protected $signature = 'maintenance:generate';

    protected $description = 'Génère les interventions préventives dues à partir des plans de maintenance actifs';

    public function handle(): int
    {
        $plans = PlanMaintenance::query()
            ->where('actif', true)
            ->with('equipementable')
            ->get();

        $generated = 0;

        foreach ($plans as $plan) {
            if (!$plan->equipementable) {
                continue;
            }

            // Ne pas générer de doublon tant qu'une intervention issue de ce plan est
            // encore ouverte (planifiée ou en cours) : le comptage repart de l'exécution
            // (via "Marquer exécuté"), pas de la génération de l'intervention.
            $hasPending = Intervention::where('plan_maintenance_id', $plan->id)
                ->whereIn('statut', ['planifiee', 'en_cours'])
                ->exists();

            if ($hasPending) {
                continue;
            }

            $datePlanifiee = null;

            if ($plan->type_frequence === 'jours') {
                $echeance = $plan->prochaine_echeance;
                if (!$echeance || $echeance->isFuture()) {
                    continue;
                }
                $datePlanifiee = $echeance;
            } elseif ($plan->type_frequence === 'kilometres' && $plan->equipementable instanceof Vehicule) {
                $kmDepuis = $plan->equipementable->kilometrage_actuel - ($plan->derniere_execution_km ?? 0);
                if ($kmDepuis < $plan->frequence_valeur) {
                    continue;
                }
                $datePlanifiee = now();
            } else {
                continue;
            }

            Intervention::create([
                'organisation_id' => $plan->organisation_id,
                'equipementable_type' => $plan->equipementable_type,
                'equipementable_id' => $plan->equipementable_id,
                'plan_maintenance_id' => $plan->id,
                'type_intervention' => 'preventive',
                'statut' => 'planifiee',
                'priorite' => 'normale',
                'date_planifiee' => $datePlanifiee,
                'titre' => $plan->operation,
                'description' => "Intervention générée automatiquement par le plan de maintenance préventive #{$plan->id}.",
            ]);

            $generated++;
        }

        $this->info("{$generated} intervention(s) préventive(s) générée(s).");

        return self::SUCCESS;
    }
}
