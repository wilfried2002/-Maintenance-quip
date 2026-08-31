<?php

namespace App\Services;

use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\User;
use App\Models\Vehicule;

class RoleService
{
    /**
     * Classe d'équipement (valeur d'equipementable_type côté interventions/documents)
     * -> clé de module de config/modules.php. Centralisé ici pour les contrôles qui
     * dépendent de L'ÉQUIPEMENT concerné (routes /interventions/...), donc sans
     * préfixe de module dans l'URL que CheckRole pourrait mapper via route_module_map.
     */
    private const MODULE_PAR_CLASSE_EQUIPEMENT = [
        EquipementIndustriel::class => 'equipements_industriels',
        Vehicule::class => 'parc_automobile',
        EquipementBureau::class => 'equipement_bureau',
    ];

    /**
     * Rôles autorisés sur le stock de chaque module — copie conforme des listes de
     * rôles des routes {module}/pieces (voir routes/web.php) : responsable_parc n'a
     * accès qu'au stock du parc automobile, pas à ceux industriel/bureau.
     */
    private const ROLES_STOCK_PAR_MODULE = [
        'equipements_industriels' => ['responsable_maintenance', 'technicien', 'magasinier'],
        'parc_automobile' => ['responsable_maintenance', 'technicien', 'responsable_parc', 'magasinier'],
        'equipement_bureau' => ['responsable_maintenance', 'technicien', 'magasinier'],
    ];

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

    /**
     * Clé de module (config/modules.php) correspondant à une classe d'équipement,
     * null si la classe n'est pas un équipement connu.
     */
    public static function modulePourClasseEquipement(?string $classe): ?string
    {
        return self::MODULE_PAR_CLASSE_EQUIPEMENT[$classe] ?? null;
    }

    /**
     * Accès aux données d'une intervention (rapport PDF, notes de terrain...) : il
     * faut avoir accès au module de L'ÉQUIPEMENT concerné par l'intervention. Les
     * routes /interventions/* n'ont pas de préfixe de module dans l'URL, le
     * check.role posé sur ces routes ne voit donc que l'union des rôles des 3
     * modules — ce contrôle-ci fait la vérification fine, avec la même sémantique
     * que les routes du module (rôle par défaut + override, voir hasModuleAccess).
     */
    public static function peutAccederIntervention(User $user, Intervention $intervention): bool
    {
        $module = self::modulePourClasseEquipement($intervention->equipementable_type);

        return $module !== null && $user->hasModuleAccess($module);
    }

    /**
     * Consommation de pièces sur une intervention : même logique que les routes
     * {module}/pieces — l'utilisateur doit gérer le stock DU module de l'équipement
     * de l'intervention (un responsable_parc ne consomme pas le stock industriel).
     * L'override « pieces_stock » est honoré comme pour ces routes.
     */
    public static function peutConsommerPiecesIntervention(User $user, Intervention $intervention): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        $organisationId = $user->getCurrentOrganisation()?->id;
        if (!$organisationId) {
            return false;
        }

        $module = self::modulePourClasseEquipement($intervention->equipementable_type);
        if ($module === null) {
            return false;
        }

        $role = $user->organisations()
            ->where('organisations.id', $organisationId)
            ->first()?->pivot->role;

        if (!$role) {
            return false;
        }

        if ($role === 'admin') {
            return true;
        }

        $override = $user->modulePermissions()
            ->where('organisation_id', $organisationId)
            ->where('module', 'pieces_stock')
            ->first();

        if ($override) {
            return (bool) $override->granted;
        }

        return in_array($role, self::ROLES_STOCK_PAR_MODULE[$module] ?? [], true);
    }
}
