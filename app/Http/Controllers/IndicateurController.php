<?php

namespace App\Http\Controllers;

use App\Models\IndicateurPerformancePiece;
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

    public function recalculer(IndicateurPerformanceCalculator $calculator): RedirectResponse
    {
        $count = $calculator->calculerTout();

        return back()->with('status', "Indicateurs recalculés pour {$count} pièce(s).");
    }
}
