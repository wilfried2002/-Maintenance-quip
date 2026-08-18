<?php

namespace App\Http\Controllers;

use App\Services\IndicateurPerformanceCalculator;
use Illuminate\Http\JsonResponse;

class IndicateurPerformanceController extends Controller
{
    /**
     * Recalcule les indicateurs de performance des pièces.
     * Déclenché via un bouton "Recalculer" dans l'interface.
     * 
     * Les indicateurs sont calculés à partir des données réelles des interventions
     * (consommations de pièces) sans intervention manuelle.
     */
    public function recalculate(IndicateurPerformanceCalculator $calculator): JsonResponse
    {
        try {
            $count = $calculator->calculerTout();

            return response()->json([
                'success' => true,
                'message' => "✅ $count pièces ont été mises à jour",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '❌ Erreur lors du recalcul : ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
