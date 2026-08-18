<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use App\Models\Vehicule;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class InterventionRapportController extends Controller
{
    public function show(Intervention $intervention): Response
    {
        $intervention->load(['equipementable', 'technicien', 'pieces', 'planMaintenance', 'organisation']);

        $equipement = $intervention->equipementable;
        $equipementLabel = $equipement instanceof Vehicule
            ? $equipement->immatriculation
            : $equipement?->designation;

        $pdf = Pdf::loadView('pdf.rapport-intervention', [
            'intervention' => $intervention,
            'equipement' => $equipement,
            'equipementLabel' => $equipementLabel ?? '—',
            'coutPieces' => $intervention->coutTotalPieces(),
            'coutTotal' => $intervention->coutTotal(),
            'devise' => $intervention->organisation?->symboleDevise() ?? '',
            'typeLabel' => $this->typeLabel($intervention->type_intervention),
            'statutLabel' => $this->statutLabel($intervention->statut),
            'prioriteLabel' => $this->prioriteLabel($intervention->priorite),
        ])->setPaper('a4');

        return $pdf->stream("rapport-intervention-{$intervention->id}.pdf");
    }

    private function typeLabel(string $value): string
    {
        return match ($value) {
            'preventive' => 'Préventive',
            'corrective' => 'Corrective',
            'predictive' => 'Prédictive',
            default => $value,
        };
    }

    private function statutLabel(string $value): string
    {
        return match ($value) {
            'planifiee' => 'Planifiée',
            'en_cours' => 'En cours',
            'terminee' => 'Terminée',
            'annulee' => 'Annulée',
            default => $value,
        };
    }

    private function prioriteLabel(?string $value): string
    {
        return match ($value) {
            'basse' => 'Basse',
            'normale' => 'Normale',
            'haute' => 'Haute',
            'critique' => 'Critique',
            default => $value ?? '—',
        };
    }
}
