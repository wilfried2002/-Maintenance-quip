<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Services\RoleService;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * IMPORTANT : is_super_admin n'y figure VOLONTAIREMENT pas — l'élévation de
     * privilèges par mass assignment (ex. User::create($request->all()) avec
     * is_super_admin=true dans la payload) doit rester impossible. Les seeders et
     * factories ne sont pas concernés : ils contournent fillable (attributs bruts).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'position',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Organisations auxquelles l'utilisateur appartient.
     */
    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, 'user_organisations')
            ->withPivot('role', 'is_active')
            ->withTimestamps();
    }

    /**
     * Overrides explicites de permissions par module (accès accordé ou révoqué au-delà du rôle),
     * scopés à une organisation.
     */
    public function modulePermissions(): HasMany
    {
        return $this->hasMany(UserModulePermission::class);
    }

    /**
     * Vérifier si l'utilisateur a accès à un module, pour une organisation donnée.
     *
     * Important : passer $organisationId explicitement quand on vérifie l'accès d'un
     * utilisateur AUTRE que celui connecté — getCurrentOrganisation() lit la session de
     * l'utilisateur CONNECTÉ, pas celle de la cible.
     */
    public function hasModuleAccess(string $module, ?int $organisationId = null): bool
    {
        return RoleService::canAccessModule($this, $module, $organisationId);
    }

    /**
     * Obtenir l'organisation courante (depuis la session, sinon la première).
     *
     * Le super admin n'a pas de ligne dans user_organisations (il n'est membre d'aucune
     * organisation) : son "organisation courante" vient uniquement de la session (posée
     * par défaut par CheckOrganisationAccess, changeable via le sélecteur), lue
     * directement sur le modèle Organisation plutôt que via la relation d'appartenance.
     */
    public function getCurrentOrganisation(): ?Organisation
    {
        $currentOrgId = session('current_organisation_id');

        if ($this->is_super_admin) {
            return $currentOrgId
                ? Organisation::find($currentOrgId)
                : Organisation::orderBy('id')->first();
        }

        if ($currentOrgId) {
            return $this->organisations()->where('organisations.id', $currentOrgId)->first();
        }

        return $this->organisations()->first();
    }

    /**
     * Vérifier si l'utilisateur est super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    /**
     * Vérifier si l'utilisateur est admin d'une organisation.
     */
    public function isAdminOf(Organisation $organisation): bool
    {
        return $this->organisations()
            ->where('organisation_id', $organisation->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    /**
     * Vérifier si l'utilisateur est admin dans l'organisation courante.
     */
    public function isAdmin(): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $currentOrg = $this->getCurrentOrganisation();
        if (!$currentOrg) {
            return false;
        }

        return $currentOrg->pivot->role === 'admin';
    }

    /**
     * Vérifier si l'utilisateur a un rôle spécifique dans l'organisation courante.
     *
     * @param string|array $roles
     */
    public function hasRole($roles): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $currentOrg = $this->getCurrentOrganisation();
        if (!$currentOrg) {
            return false;
        }

        $userRole = $currentOrg->pivot->role ?? null;

        if (is_array($roles)) {
            return in_array($userRole, $roles, true);
        }

        return $userRole === $roles;
    }

    /**
     * Obtenir le rôle de l'utilisateur dans l'organisation courante.
     */
    public function getRole(): ?string
    {
        if ($this->is_super_admin) {
            return 'super_admin';
        }

        $currentOrg = $this->getCurrentOrganisation();
        return $currentOrg ? ($currentOrg->pivot->role ?? null) : null;
    }
}
