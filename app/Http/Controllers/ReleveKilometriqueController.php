<?php

namespace App\Http\Controllers;

use App\Models\ReleveKilometrique;
use App\Models\Vehicule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Historique des relevés kilométriques (10/10) : le compteur n'est plus une
 * valeur écrasée à la main — chaque lecture est journalisée, le compteur du
 * véhicule suit le dernier relevé et les plans au kilomètre s'appuient dessus.
 */
class ReleveKilometriqueController extends Controller
{
    public function store(Request $request, Vehicule $vehicule): RedirectResponse
    {
        $data = $request->validate([
            'kilometrage' => ['required', 'integer', 'min:0'],
            'date_releve' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // Compteur non décroissant : un relevé inférieur au dernier est une erreur de saisie.
        $dernier = $vehicule->dernierReleve();

        if ($dernier !== null && (int) $data['kilometrage'] < $dernier->kilometrage) {
            return back()->withErrors([
                'kilometrage' => "Le compteur ne peut pas diminuer (dernier relevé : {$dernier->kilometrage} km).",
            ]);
        }

        ReleveKilometrique::create([
            'vehicule_id' => $vehicule->id,
            'kilometrage' => $data['kilometrage'],
            'date_releve' => $data['date_releve'],
            'source' => 'saisie',
            'user_id' => Auth::id(),
            'note' => $data['note'] ?? null,
        ]);

        $vehicule->forceFill(['kilometrage_actuel' => $data['kilometrage']])->saveQuietly();

        return back()->with('status', 'Relevé enregistré.');
    }

    public function destroy(ReleveKilometrique $releve): RedirectResponse
    {
        // Un relevé erroné peut être retiré par son auteur ou un admin.
        abort_unless(
            Auth::user()?->is_super_admin
            || Auth::user()?->getRole() === 'admin'
            || $releve->user_id === Auth::id(),
            403
        );

        $vehicule = $releve->vehicule;
        $releve->delete();

        // Recaler le compteur sur le dernier relevé restant.
        $nouveauDernier = $vehicule->dernierReleve();
        $vehicule->forceFill(['kilometrage_actuel' => $nouveauDernier?->kilometrage ?? 0])->saveQuietly();

        return back()->with('status', 'Relevé supprimé.');
    }
}
