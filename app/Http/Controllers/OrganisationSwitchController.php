<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganisationSwitchController extends Controller
{
    /**
     * Réservé au super admin (voir routes/web.php) : change l'organisation "courante"
     * en session, qui pilote le cloisonnement des données (BelongsToOrganisation).
     * Un utilisateur normal ne peut pas changer d'organisation en cours de session —
     * il faut se reconnecter avec un autre code organisation.
     */
    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organisation_id' => ['required', 'integer', Rule::exists('organisations', 'id')],
        ]);

        session(['current_organisation_id' => $data['organisation_id']]);

        return back()->with('status', 'Organisation changée.');
    }
}
