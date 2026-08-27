<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use App\Services\IndicateurPerformanceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InterventionController extends Controller
{
    public function update(Request $request, Intervention $intervention, IndicateurPerformanceCalculator $calculator): RedirectResponse
    {
        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'type_intervention' => ['required', 'in:preventive,corrective,predictive'],
            'statut' => ['required', 'in:planifiee,en_cours,terminee,annulee'],
            'priorite' => ['required', 'in:basse,normale,haute,critique'],
            'date_planifiee' => ['nullable', 'date'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'technicien_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'cout_main_oeuvre' => ['nullable', 'numeric', 'min:0'],
            'duree_heures' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $intervention->update($data);
        $intervention->pieces()->get()->each(fn ($piece) => $calculator->recalculerPiece($piece));

        return back()->with('status', 'Intervention mise à jour.');
    }

    public function destroy(Intervention $intervention, IndicateurPerformanceCalculator $calculator): RedirectResponse
    {
        $pieces = $intervention->pieces()->get();

        DB::transaction(function () use ($intervention, $pieces) {
            foreach ($pieces as $piece) {
                $piece->increment('stock_qte', $piece->pivot->quantite);
            }

            $intervention->pieces()->detach();
            $intervention->delete();
        });

        $pieces->each(fn ($piece) => $calculator->recalculerPiece($piece));

        return back()->with('status', 'Intervention supprimée et stock des pièces restitué.');
    }

    /**
     * Enregistrer les notes de terrain d'une intervention (observations du technicien
     * pendant/après l'exécution) — distinct de description, saisie à la planification.
     */
    public function updateNotes(Request $request, Intervention $intervention): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $intervention->update($data);

        return back()->with('status', 'Notes enregistrées.');
    }
}
