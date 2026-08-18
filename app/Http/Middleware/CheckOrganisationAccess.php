<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckOrganisationAccess
{
    /**
     * Vérifie qu'une organisation est bien sélectionnée en session et que l'utilisateur
     * y a toujours accès (utile après une révocation d'accès pendant que la session est active).
     *
     * Le super admin a lui aussi une organisation "courante" en session (posée ici par
     * défaut sur la première organisation existante, changeable via le sélecteur — voir
     * OrganisationSwitchController) : c'est ce qui pilote le cloisonnement des données
     * (App\Models\Concerns\BelongsToOrganisation) même pour lui. Sans organisation
     * courante, ses requêtes ne seraient filtrées par aucune organisation.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->is_super_admin) {
            $currentOrganisationId = session('current_organisation_id');

            if (!$currentOrganisationId || !Organisation::whereKey($currentOrganisationId)->exists()) {
                $fallbackId = Organisation::orderBy('id')->value('id');
                if ($fallbackId) {
                    session(['current_organisation_id' => $fallbackId]);
                }
            }

            return $next($request);
        }

        $currentOrganisationId = session('current_organisation_id');

        if (!$currentOrganisationId) {
            Auth::logout();
            return redirect()->route('login')
                ->with('error', 'Veuillez vous reconnecter avec votre code organisation.');
        }

        $hasAccess = $user->organisations()
            ->where('organisations.id', $currentOrganisationId)
            ->where('user_organisations.is_active', true)
            ->exists();

        if (!$hasAccess) {
            Auth::logout();
            return redirect()->route('login')
                ->with('error', 'Accès non autorisé à cette organisation.');
        }

        return $next($request);
    }
}
