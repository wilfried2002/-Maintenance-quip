<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserModulePermission;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $organisation = Auth::user()->getCurrentOrganisation();

        // Super admin sans organisation courante (aucune organisation, ou aucune sélectionnée
        // en session) : vue globale de tous les utilisateurs plutôt qu'un crash sur null.
        if (!$organisation) {
            $users = User::orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'position' => $user->position,
                'role' => $user->is_super_admin ? 'super_admin' : $user->organisations->first()?->pivot->role,
                'is_active' => true,
                'permissions' => new \stdClass(),
            ]);
        } else {
            $users = $organisation->users()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'position' => $user->position,
                    'role' => $user->pivot->role,
                    'is_active' => (bool) $user->pivot->is_active,
                    // Overrides explicites de cet utilisateur pour CETTE organisation :
                    // { module: granted(true/false) } — l'absence de clé = défaut du rôle.
                    'permissions' => $user->modulePermissions()
                        ->where('organisation_id', $organisation->id)
                        ->get(['module', 'granted'])
                        ->mapWithKeys(fn ($p) => [$p->module => (bool) $p->granted]),
                ]);
        }

        return Inertia::render('Utilisateurs/Index', [
            'users' => $users,
            'roles' => RoleService::getAllRoles(),
            // Pour pré-cocher la grille : état initial = défauts du rôle sélectionné.
            'roleDefaults' => config('modules.role_defaults'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organisation = Auth::user()->getCurrentOrganisation();

        if (!$organisation) {
            return back()->withErrors(['organisation' => 'Sélectionnez une organisation avant de créer un utilisateur.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_keys(RoleService::getAllRoles()))],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'position' => $data['position'] ?? null,
        ]);

        $organisation->users()->attach($user->id, [
            'role' => $data['role'],
            'is_active' => true,
        ]);

        return back()->with('status', 'Utilisateur créé et rattaché à l\'organisation.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $organisation = Auth::user()->getCurrentOrganisation();

        if (!$organisation) {
            return back()->withErrors(['organisation' => 'Sélectionnez une organisation avant de modifier un utilisateur.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_keys(RoleService::getAllRoles()))],
            'is_active' => ['boolean'],
        ]);

        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'position' => $data['position'] ?? null,
        ]);

        $organisation->users()->updateExistingPivot($user->id, [
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('status', 'Utilisateur mis à jour.');
    }

    /**
     * Grille de permissions par module : pour chaque module de config/modules.php,
     * l'admin choisit « def » (défaut du rôle — pas de ligne d'override), « granted »
     * (accès accordé au-delà du rôle) ou « revoked » (accès révoqué malgré le rôle).
     *
     * La table user_module_permissions est resynchronisée pour l'organisation
     * courante uniquement (les overrides d'autres organisations sont intouchés).
     * Le rôle admin n'est pas affecté : CheckRole et RoleService le laissent
     * toujours passer, quel que soit le contenu de la grille.
     */
    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        $organisation = Auth::user()->getCurrentOrganisation();

        if (!$organisation) {
            return back()->withErrors(['organisation' => 'Sélectionnez une organisation avant de modifier des permissions.']);
        }

        // Un admin ne peut paramétrer que les membres de SA organisation courante.
        if (!$user->organisations()->where('organisations.id', $organisation->id)->exists()) {
            return back()->withErrors(['permissions' => 'Cet utilisateur n\'appartient pas à l\'organisation courante.']);
        }

        $modules = array_keys(config('modules.list'));

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required', 'in:def,granted,revoked'],
        ]);

        // Rejeter toute clé hors module connu plutôt que de l'ignorer silencieusement.
        $inconnus = array_diff(array_keys($data['permissions']), $modules);
        if ($inconnus !== []) {
            return back()->withErrors([
                'permissions' => 'Module inconnu : '.implode(', ', $inconnus).'.',
            ]);
        }

        $user->modulePermissions()->where('organisation_id', $organisation->id)->delete();

        foreach ($data['permissions'] as $module => $etat) {
            if ($etat === 'def') {
                continue;
            }

            UserModulePermission::create([
                'user_id' => $user->id,
                'organisation_id' => $organisation->id,
                'module' => $module,
                'granted' => $etat === 'granted',
            ]);
        }

        return back()->with('status', 'Permissions de « '.$user->name.' » mises à jour pour cette organisation.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas retirer votre propre accès.']);
        }

        $organisation = Auth::user()->getCurrentOrganisation();

        if (!$organisation) {
            return back()->withErrors(['organisation' => 'Sélectionnez une organisation avant de retirer un utilisateur.']);
        }

        $organisation->users()->detach($user->id);

        return back()->with('status', 'Utilisateur retiré de l\'organisation.');
    }
}
