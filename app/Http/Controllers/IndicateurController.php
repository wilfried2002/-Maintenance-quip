<?php

namespace App\Http\Controllers;

use App\Models\IndicateurPerformancePiece;
use App\Models\Piece;
use App\Services\IndicateurPerformanceCalculator;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IndicateurController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Indicateurs/Index', [
            'indicateurs' => IndicateurPerformancePiece::with('piece')
                ->whereNull('equipementable_type')
                ->whereHas('piece')
                ->orderByDesc('taux_defaillance')
                ->get(),
        ]);
    }

    public function show(Piece $piece): Response
    {
        $indicateur = IndicateurPerformancePiece::where('piece_id', $piece->id)
            ->whereNull('equipementable_type')
            ->first();

        $consommations = $piece->interventions()
            ->with('equipementable')
            ->orderByDesc('interventions.date_fin')
            ->orderByDesc('interventions.date_planifiee')
            ->get()
            ->map(fn ($intervention) => [
                'id' => $intervention->id,
                'date' => $intervention->date_fin ?? $intervention->date_planifiee ?? $intervention->created_at,
                'type_intervention' => $intervention->type_intervention,
                'titre' => $intervention->titre,
                'quantite' => $intervention->pivot->quantite,
                'prix_unitaire' => $intervention->pivot->prix_unitaire,
                'cout_total' => $intervention->pivot->quantite * $intervention->pivot->prix_unitaire,
                'equipement' => $intervention->equipementable
                    ? ($intervention->equipementable->code . ' — ' . ($intervention->equipementable->designation ?? $intervention->equipementable->immatriculation ?? ''))
                    : '—',
            ])
            ->values();

        return Inertia::render('Indicateurs/Show', [
            'piece' => $piece->only(['id', 'reference', 'designation', 'unite']),
            'indicateur' => $indicateur,
            'consommations' => $consommations,
        ]);
    }

    public function recalculer(IndicateurPerformanceCalculator $calculator): RedirectResponse
    {
        $count = $calculator->calculerTout();

        return back()->with('status', "Indicateurs recalculés pour {$count} pièce(s).");
    }
}
