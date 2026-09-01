<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\User;
use App\Models\UserModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Grille de permissions par module (overrides au-delà du rôle, posés par un
 * admin via PUT /users/{user}/permissions) : mêmes effets que ceux que
 * CheckRole et RoleService::canAccessModule lisent depuis longtemps.
 */
class UserPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(string $code): Organisation
    {
        return Organisation::create(['name' => "Org {$code}", 'code' => $code, 'is_active' => true]);
    }

    private function userAvecRole(Organisation $org, string $role): User
    {
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => $role, 'is_active' => true]);

        return $user;
    }

    private function actingAsInOrg(User $user, Organisation $org): static
    {
        return $this->actingAs($user)->withSession(['current_organisation_id' => $org->id]);
    }

    public function test_admin_peut_accorder_un_module_que_le_role_na_pas_par_defaut(): void
    {
        $org = $this->organisation('ORGA');
        $admin = $this->userAvecRole($org, 'admin');
        $magasinier = $this->userAvecRole($org, 'magasinier');

        // Avant : magasinier n'a pas accès aux équipements industriels.
        $this->actingAsInOrg($magasinier, $org)
            ->get('/equipements-industriels')
            ->assertForbidden();

        $this->actingAsInOrg($admin, $org)
            ->put("/users/{$magasinier->id}/permissions", [
                'permissions' => [
                    'equipements_industriels' => 'granted',
                    'pieces_stock' => 'def',
                ],
            ])->assertRedirect();

        // Après : l'override « granted » ouvre l'accès.
        $this->actingAsInOrg($magasinier, $org)
            ->get('/equipements-industriels')
            ->assertOk();

        $this->assertDatabaseHas('user_module_permissions', [
            'user_id' => $magasinier->id,
            'organisation_id' => $org->id,
            'module' => 'equipements_industriels',
            'granted' => true,
        ]);
    }

    public function test_admin_peut_revoquer_un_module_que_le_role_a_par_defaut(): void
    {
        $org = $this->organisation('ORGA');
        $admin = $this->userAvecRole($org, 'admin');
        $technicien = $this->userAvecRole($org, 'technicien');

        $this->actingAsInOrg($admin, $org)
            ->put("/users/{$technicien->id}/permissions", [
                'permissions' => ['equipements_industriels' => 'revoked'],
            ])->assertRedirect();

        $this->actingAsInOrg($technicien, $org)
            ->get('/equipements-industriels')
            ->assertForbidden();
    }

    public function test_def_supprime_les_overrides_existants(): void
    {
        $org = $this->organisation('ORGA');
        $admin = $this->userAvecRole($org, 'admin');
        $technicien = $this->userAvecRole($org, 'technicien');

        UserModulePermission::create([
            'user_id' => $technicien->id,
            'organisation_id' => $org->id,
            'module' => 'equipements_industriels',
            'granted' => false,
        ]);

        $this->actingAsInOrg($admin, $org)
            ->put("/users/{$technicien->id}/permissions", [
                'permissions' => ['equipements_industriels' => 'def'],
            ])->assertRedirect();

        $this->assertDatabaseMissing('user_module_permissions', [
            'user_id' => $technicien->id,
            'organisation_id' => $org->id,
        ]);

        // Retour au défaut du rôle (technicien : accès).
        $this->actingAsInOrg($technicien, $org)
            ->get('/equipements-industriels')
            ->assertOk();
    }

    public function test_non_admin_ne_peut_pas_modifier_les_permissions(): void
    {
        $org = $this->organisation('ORGA');
        $responsable = $this->userAvecRole($org, 'responsable_maintenance');
        $cible = $this->userAvecRole($org, 'technicien');

        $this->actingAsInOrg($responsable, $org)
            ->put("/users/{$cible->id}/permissions", [
                'permissions' => ['fournisseurs' => 'granted'],
            ])->assertForbidden();
    }

    public function test_les_overrides_sont_scopes_a_lorganisation(): void
    {
        $orgA = $this->organisation('ORGA');
        $orgB = $this->organisation('ORGB');
        $adminA = $this->userAvecRole($orgA, 'admin');
        $userB = $this->userAvecRole($orgB, 'technicien');

        // Un admin ne peut paramétrer que les membres de SON organisation :
        // la cible appartient à l'org B -> refus, rien n'est écrit.
        $this->actingAsInOrg($adminA, $orgA)
            ->put("/users/{$userB->id}/permissions", [
                'permissions' => ['fournisseurs' => 'granted'],
            ])->assertSessionHasErrors('permissions');

        $this->assertDatabaseMissing('user_module_permissions', [
            'user_id' => $userB->id,
            'organisation_id' => $orgA->id,
        ]);
    }
}
