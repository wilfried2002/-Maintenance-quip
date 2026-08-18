<?php

namespace Tests\Unit;

use App\Models\Organisation;
use App\Models\User;
use App\Models\UserModulePermission;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(): Organisation
    {
        return Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
    }

    public function test_super_admin_a_toujours_acces(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $this->assertTrue(RoleService::canAccessModule($user, 'utilisateurs'));
        $this->assertTrue(RoleService::canAccessModule($user, 'parc_automobile'));
    }

    public function test_admin_organisation_a_acces_a_tous_les_modules(): void
    {
        $org = $this->organisation();
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'admin', 'is_active' => true]);

        $this->assertTrue(RoleService::canAccessModule($user, 'utilisateurs', $org->id));
        $this->assertTrue(RoleService::canAccessModule($user, 'indicateurs', $org->id));
    }

    public function test_role_par_defaut_donne_acces_uniquement_aux_modules_configures(): void
    {
        $org = $this->organisation();
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'magasinier', 'is_active' => true]);

        $this->assertTrue(RoleService::canAccessModule($user, 'pieces_stock', $org->id));
        $this->assertTrue(RoleService::canAccessModule($user, 'couts_entretien', $org->id));
        $this->assertFalse(RoleService::canAccessModule($user, 'utilisateurs', $org->id));
        $this->assertFalse(RoleService::canAccessModule($user, 'parc_automobile', $org->id));
    }

    public function test_override_revoque_prime_sur_lacces_par_defaut_du_role(): void
    {
        $org = $this->organisation();
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'technicien', 'is_active' => true]);

        // 'technicien' a accès à equipements_industriels par défaut.
        $this->assertTrue(RoleService::canAccessModule($user, 'equipements_industriels', $org->id));

        UserModulePermission::create([
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'module' => 'equipements_industriels',
            'granted' => false,
        ]);

        $this->assertFalse(RoleService::canAccessModule($user, 'equipements_industriels', $org->id));
    }

    public function test_override_accorde_prime_sur_labsence_dacces_par_defaut_du_role(): void
    {
        $org = $this->organisation();
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'magasinier', 'is_active' => true]);

        // 'magasinier' n'a pas accès à parc_automobile par défaut.
        $this->assertFalse(RoleService::canAccessModule($user, 'parc_automobile', $org->id));

        UserModulePermission::create([
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'module' => 'parc_automobile',
            'granted' => true,
        ]);

        $this->assertTrue(RoleService::canAccessModule($user, 'parc_automobile', $org->id));
    }

    public function test_utilisateur_sans_organisation_na_acces_a_rien(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(RoleService::canAccessModule($user, 'equipements_industriels'));
    }

    public function test_usersWithModuleAccess_cible_les_bons_utilisateurs(): void
    {
        $org = $this->organisation();

        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $technicien = User::factory()->create();
        $org->users()->attach($technicien->id, ['role' => 'technicien', 'is_active' => true]);

        $magasinier = User::factory()->create();
        $org->users()->attach($magasinier->id, ['role' => 'magasinier', 'is_active' => true]);

        $technicienInactif = User::factory()->create();
        $org->users()->attach($technicienInactif->id, ['role' => 'technicien', 'is_active' => false]);

        $resultat = RoleService::usersWithModuleAccess('equipements_industriels');
        $ids = $resultat->pluck('id')->all();

        $this->assertContains($superAdmin->id, $ids);
        $this->assertContains($technicien->id, $ids);
        $this->assertNotContains($magasinier->id, $ids);
        $this->assertNotContains($technicienInactif->id, $ids);
    }
}
