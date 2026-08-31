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
     * Implémentation en UNE requête SQL (l'ancienne version chargeait tous les
     * utilisateurs puis relançait 2 requêtes par utilisateur pour canAccessModule).
     * Sémantique identique à canAccessModule : super admin → vrai ; rôle admin →
     * vrai ; override « granted » → vrai ; override « revoked » → faux ; sans
     * override → rôle qui a le module par défaut.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function usersWithModuleAccess(string $module, ?int $organisationId = null): \Illuminate\Support\Collection
    {
        $rolesAvecDefaut = collect(config('modules.role_defaults', []))
            ->filter(fn (array $modules) => in_array($module, $modules, true))
            ->keys()
            ->all();

        return User::query()
            ->where(function ($q) use ($module, $rolesAvecDefaut, $organisationId) {
                // Les super admins sont toujours inclus (visibilité plateforme).
                $q->where('is_super_admin', true);

                // ... ou membres actifs d'une organisation où ils ont accès au
                // module : rôle admin, override accordé, ou (sans override) rôle
                // qui a le module par défaut.
                $q->orWhereHas('organisations', function ($appartenance) use ($module, $rolesAvecDefaut, $organisationId) {
                    $appartenance->where('user_organisations.is_active', true);

                    if ($organisationId !== null) {
                        $appartenance->where('user_organisations.organisation_id', $organisationId);
                    }

                    $appartenance->where(function ($acces) use ($module, $rolesAvecDefaut) {
                        $acces->where('user_organisations.role', 'admin');

                        $acces->orWhereExists(function ($override) use ($module) {
                            $override->from('user_module_permissions')
                                ->whereColumn('user_module_permissions.user_id', 'users.id')
                                ->whereColumn('user_module_permissions.organisation_id', 'user_organisations.organisation_id')
                                ->where('user_module_permissions.module', $module)
                                ->where('user_module_permissions.granted', true);
                        });

                        $acces->orWhere(function ($defaut) use ($module, $rolesAvecDefaut) {
                            $defaut->whereNotExists(function ($override) use ($module) {
                                $override->from('user_module_permissions')
                                    ->whereColumn('user_module_permissions.user_id', 'users.id')
                                    ->whereColumn('user_module_permissions.organisation_id', 'user_organisations.organisation_id')
                                    ->where('user_module_permissions.module', $module);
                            })->whereIn('user_organisations.role', $rolesAvecDefaut);
                        });
                    });
                });
            })
            ->get();
    }

    /**
     * Liste des modules accessibles à l'utilisateur dans son organisation courante,
     * dans l'ordre de config('modules.list'). Version groupée de canAccessModule
     * pour les props partagées Inertia : 2 requêtes au lieu de 2 par module
     * (le menu partageait cette liste à CHAQUE requête HTTP).
     *
     * @return array<int, string>
     */
    public static function modulesAccessibles(User $user): array
    {
        $tous = array_keys(config('modules.list', []));

        if ($user->is_super_admin) {
            return $tous;
        }

        $organisationId = $user->getCurrentOrganisation()?->id;

        if (!$organisationId) {
            return [];
        }

        $role = $user->organisations()
            ->where('organisations.id', $organisationId)
            ->first()?->pivot->role;

        if (!$role) {
            return [];
        }

        if ($role === 'admin') {
            return $tous;
        }

        $overrides = $user->modulePermissions()
            ->where('organisation_id', $organisationId)
            ->get(['module', 'granted'])
            ->keyBy('module');

        return array_values(array_filter(
            $tous,
            function (string $module) use ($overrides, $role) {
                if ($overrides->has($module)) {
                    return (bool) $overrides->get($module)->granted;
                }

                return self::roleHasModuleDefault($role, $module);
            }
        ));
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
        $module = self::modulePourClasseEquipement($intervention->equipementable_type);

        return $module !== null && self::peutGererStockModule($user, $module);
    }

    /**
     * L'utilisateur peut gérer le stock du module donné (mouvements, consommation) :
     * super admin → vrai ; rôle admin → vrai ; override « pieces_stock » → sa
     * valeur ; sinon le rôle doit faire partie des gestionnaires du stock de CE
     * module (responsable_parc ne gère que le stock du parc automobile).
     */
    public static function peutGererStockModule(User $user, string $module): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        $organisationId = $user->getCurrentOrganisation()?->id;
        if (!$organisationId) {
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

    /**
     * Modules dont l'utilisateur gère le stock (page mouvements-stock) : clés de
     * ROLES_STOCK_PAR_MODULE filtrées par peutGererStockModule.
     *
     * @return array<int, string>
     */
    public static function modulesStockAccessibles(User $user): array
    {
        return array_values(array_filter(
            array_keys(self::ROLES_STOCK_PAR_MODULE),
            fn (string $module) => self::peutGererStockModule($user, $module)
        ));
    }
}
