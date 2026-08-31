<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Nettoyer le code organisation (trim, majuscules pour cohérence).
        $organisationCode = $request->filled('organisation_code')
            ? strtoupper(trim($request->organisation_code))
            : null;

        // Si un code organisation est fourni, vérifier l'organisation AVANT authentification.
        $organisation = null;
        if ($organisationCode) {
            // Message générique unique (code inconnu OU organisation désactivée) :
            // ne pas révéler quels codes existent — ils servent aussi à la connexion
            // et à l'inscription (évite l'énumération des organisations clientes).
            $organisation = Organisation::where('code', $organisationCode)
                ->where('is_active', true)
                ->first();

            if (!$organisation) {
                return back()->withErrors([
                    'organisation_code' => 'Code organisation invalide ou désactivé.',
                ])->onlyInput('email', 'organisation_code');
            }
        }

        $request->authenticate();

        // Connexion unique par compte : refuser si ce compte a déjà une session
        // active ailleurs (autre navigateur/appareil), plutôt que d'en déconnecter
        // une pour laisser passer la nouvelle.
        $authenticatedUser = Auth::user();

        $hasOtherActiveSession = DB::table('sessions')
            ->where('user_id', $authenticatedUser->id)
            ->where('id', '!=', $request->session()->getId())
            ->where('last_activity', '>=', now()->subMinutes(config('session.lifetime'))->timestamp)
            ->exists();

        if ($hasOtherActiveSession) {
            Auth::logout();
            return back()->withErrors([
                'email' => 'Ce compte est déjà connecté sur un autre appareil ou navigateur.',
            ])->onlyInput('email', 'organisation_code');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // Si super admin, rediriger directement.
        if ($user->is_super_admin) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        // Pour les utilisateurs normaux : le code organisation est OBLIGATOIRE.
        if (!$organisation) {
            Auth::logout();
            return back()->withErrors([
                'organisation_code' => 'Le code organisation est requis.',
            ])->onlyInput('email');
        }

        // Vérifier que l'utilisateur appartient à cette organisation. Un membre
        // INACTIF (inscription en attente d'activation par un admin) reçoit un
        // message dédié plutôt que le refus générique.
        $membership = $user->organisations()
            ->where('organisations.id', $organisation->id)
            ->first();

        if (!$membership) {
            Auth::logout();
            return back()->withErrors([
                'email' => 'Vous n\'êtes pas autorisé à accéder à cette organisation.',
            ])->onlyInput('email', 'organisation_code');
        }

        if (!$membership->pivot->is_active) {
            Auth::logout();
            return back()->withErrors([
                'email' => 'Votre compte n\'a pas encore été activé par un administrateur de cette organisation.',
            ])->onlyInput('email', 'organisation_code');
        }

        // Stocker l'organisation courante en session et forcer la persistance.
        session(['current_organisation_id' => $organisation->id]);
        session()->save();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
