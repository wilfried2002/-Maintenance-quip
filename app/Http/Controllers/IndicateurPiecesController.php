<?php

namespace App\Http\Controllers;

use App\Models\IndicateurPerformancePiece;
use Inertia\Inertia;
use Inertia\Response;

class IndicateurPiecesController extends Controller
{
    /**
     * Affiche la page des indicateurs de performance des pièces avec toutes les données calculées.
     */
    public function index(): Response
    {
        $indicateurs = IndicateurPerformancePiece::with('piece')
            ->orderByDesc('taux_defaillance')
            ->get();

        return Inertia::render('Indicateurs/Pieces', [
            'indicateurs' => $indicateurs,
        ]);
    }
}
