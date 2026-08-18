<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InterventionController extends Controller
{
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
