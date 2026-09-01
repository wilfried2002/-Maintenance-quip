<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * Inscription = demande de rattachement : le compte est créé membre de
     * l'organisation indiquée (via son code) avec le rôle « user », mais INACTIF —
     * un administrateur de l'organisation l'active ensuite depuis la page
     * Utilisateurs. Sans ce détour, un compte auto-inscrit n'appartient à aucune
     * organisation et CheckOrganisationAccess le déconnecte immédiatement
     * (impasse totale). L'utilisateur n'est PAS connecté ici : tant que le compte
     * est inactif, la connexion refuserait de toute façon l'accès.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'organisation_code' => ['required', 'string', 'max:50'],
        ]);

        $organisationCode = strtoupper(trim($request->organisation_code));

        // Message générique unique (code inconnu OU désactivé) : ne pas révéler
        // quels codes organisation existent — ils servent aussi à la connexion.
        $organisation = Organisation::where('code', $organisationCode)->where('is_active', true)->first();

        if (!$organisation) {
            throw ValidationException::withMessages([
                'organisation_code' => 'Code organisation invalide ou désactivé.',
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $organisation->users()->attach($user->id, [
            'role' => 'user',
            'is_active' => false,
        ]);

        event(new Registered($user));

        return redirect()
            ->route('login')
            ->with('status', 'Compte créé. Un administrateur de « '.$organisation->name.' » doit maintenant l\'activer avant que vous puissiez vous connecter.');
    }
}
