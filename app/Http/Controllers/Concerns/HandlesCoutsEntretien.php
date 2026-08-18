<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CoutEntretien;
use App\Models\Intervention;

trait HandlesCoutsEntretien
{
    /**
     * Journalise automatiquement le coût de main d'œuvre d'une intervention dans le
     * grand livre des coûts d'entretien, au moment de sa création. Les interventions
     * n'étant pas modifiables depuis l'UI actuelle, un seul enregistrement à la
     * création suffit — pas de risque de désynchronisation.
     */
    protected function recordCoutMainOeuvre(Intervention $intervention): void
    {
        if ((float) $intervention->cout_main_oeuvre <= 0) {
            return;
        }

        CoutEntretien::create([
            'organisation_id' => $intervention->organisation_id,
            'equipementable_type' => $intervention->equipementable_type,
            'equipementable_id' => $intervention->equipementable_id,
            'intervention_id' => $intervention->id,
            'type_cout' => 'main_oeuvre',
            'montant' => $intervention->cout_main_oeuvre,
            'date' => $intervention->date_planifiee?->toDateString() ?? now()->toDateString(),
            'description' => $intervention->titre,
        ]);
    }
}
