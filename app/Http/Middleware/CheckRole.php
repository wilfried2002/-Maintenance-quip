<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Services\RoleService;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Super admin a toujours accès.
        if ($user->is_super_admin) {
            return $next($request);
        }

        $currentOrganisation = $user->getCurrentOrganisation();

        if (!$currentOrganisation) {
            abort(403, 'Aucune organisation sélectionnée.');
        }

        $userRole = $user->getRole();

        if (!$userRole) {
            abort(403, 'Aucun rôle assigné pour cette organisation.');
        }

        // Admin a toujours accès.
        if ($userRole === 'admin') {
            return $next($request);
        }

        // Override explicite de permission par module (accordé/révoqué par un admin via
        // la grille de cases à cocher) : consulté AVANT la logique de rôle ci-dessous, qui
        // reste inchangée pour tout utilisateur sans override. Voir config/modules.php.
        $module = $this->resolveModuleFromRouteName($request->route()?->getName());

        if ($module) {
            $override = $user->modulePermissions()
                ->where('organisation_id', $currentOrganisation->id)
                ->where('module', $module)
                ->first();

            if ($override) {
                if ($override->granted) {
                    return $next($request);
                }
                abort(403, "Accès au module « $module » révoqué pour votre compte.");
            }
        }

        // Mode exclusion : check.role:except,magasinier → tout le monde SAUF les rôles listés.
        if (($roles[0] ?? null) === 'except') {
            $excludedRoles = array_slice($roles, 1);
            if (in_array($userRole, $excludedRoles, true)) {
                abort(403, 'Vous n\'avez pas les permissions nécessaires.');
            }
            return $next($request);
        }

        // Vérifier si l'utilisateur a un des rôles autorisés (correspondance exacte).
        if (in_array($userRole, $roles, true)) {
            return $next($request);
        }

        abort(403, 'Vous n\'avez pas les permissions nécessaires. Rôle requis : ' . implode(', ', $roles) . '. Votre rôle : ' . $userRole);
    }

    /**
     * Dériver la clé de module (config/modules.php) depuis le nom de la route courante,
     * via les préfixes de config('modules.route_module_map'). Retourne null si la route
     * n'est pas mappée à un module — dans ce cas, aucun override ne peut s'appliquer.
     */
    private function resolveModuleFromRouteName(?string $routeName): ?string
    {
        if (!$routeName) {
            return null;
        }

        foreach (config('modules.route_module_map', []) as $prefix => $module) {
            if (Str::startsWith($routeName, $prefix)) {
                return $module;
            }
        }

        return null;
    }
}
