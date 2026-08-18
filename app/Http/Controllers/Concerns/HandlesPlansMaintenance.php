<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PlanMaintenance;

trait HandlesPlansMaintenance
{
    protected function planValidationRules(): array
    {
        return [
            'operation' => ['required', 'string', 'max:255'],
            'type_frequence' => ['required', 'in:jours,kilometres'],
            'frequence_valeur' => ['required', 'integer', 'min:1'],
            'actif' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Marque le plan comme exécuté aujourd'hui : réinitialise le compteur de jours et,
     * pour un plan au kilométrage, capture le kilométrage actuel du véhicule comme
     * nouveau point de départ.
     */
    protected function markPlanExecuted(PlanMaintenance $plan): void
    {
        $plan->derniere_execution_date = now()->toDateString();

        if ($plan->type_frequence === 'kilometres' && $plan->equipementable) {
            $plan->derniere_execution_km = $plan->equipementable->kilometrage_actuel;
        }

        $plan->save();
    }
}
