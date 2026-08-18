<?php

namespace App\Services;

use App\Models\User;

class RoleService
{
    /**
     * Vérifier si un utilisateur peut accéder à un module, pour une organisation donnée
     * (celle passée explicitement, sinon l'organisation courante de l'utilisateur).
     */
    public static function canAccessModule(User $user, string $module, ?int $organisationId = null): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        $organisationId ??= $user->getCurrentOrganisation()?->id;
        if (!$organisationId) {
            return false;
        }

        $pivotRole = $user->organisations()
            ->where('organisations.id', $organisationId)
            ->first()?->pivot->role;

        if (!$pivotRole) {
            return false;
        }

        if ($pivotRole === 'admin') {
            return true;
        }

        $override = $user->modulePermissions()
            ->where('organisation_id', $organisationId)
            ->where('module', $module)
            ->first();

        return $override ? $override->granted : self::roleHasModuleDefault($pivotRole, $module);
    }

    /**
     * Vérifier si un rôle donne accès à un module par défaut (avant override éventuel).
     * Utilisé uniquement pour pré-cocher la grille de permissions dans l'UI — voir config/modules.php.
     */
    public static function roleHasModuleDefault(string $role, string $module): bool
    {
        return in_array($module, config('modules.role_defaults')[$role] ?? [], true);
    }

    /**
     * Utilisateurs actifs pouvant accéder à un module, pour cibler les destinataires
     * d'une alerte. Les super admins sont toujours inclus (visibilité plateforme).
     *
     * Passer $organisationId (cloisonnement SaaS) restreint aux membres de CETTE
     * organisation uniquement — indispensable pour les alertes : une donnée d'une
     * organisation ne doit jamais notifier les utilisateurs d'une autre. Sans
     * $organisationId, toutes les organisations de l'utilisateur sont considérées
     * (utile pour un ciblage non lié à une donnée précise).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function usersWithModuleAccess(string $module, ?int $organisationId = null): \Illuminate\Support\Collection
    {
        return User::with('organisations')->get()->filter(function (User $user) use ($module, $organisationId) {
            if ($user->is_super_admin) {
                return true;
            }

            $organisations = $organisationId
                ? $user->organisations->where('id', $organisationId)
                : $user->organisations;

            foreach ($organisations as $organisation) {
                if ($organisation->pivot->is_active && self::canAccessModule($user, $module, $organisation->id)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * Obtenir tous les rôles disponibles.
     */
    public static function getAllRoles(): array
    {
        return [
            'admin'                    => 'Administrateur',
            'responsable_maintenance'  => 'Responsable Maintenance',
            'technicien'               => 'Technicien',
            'magasinier'               => 'Magasinier',
            'responsable_parc'         => 'Responsable Parc Automobile',
            'superviseur'              => 'Superviseur',
            'user'                     => 'Utilisateur',
        ];
    }
}
