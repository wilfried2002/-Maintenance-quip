<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\User;
use App\Models\UserModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckRoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(): Organisation
    {
        return Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
    }

    /**
     * check.organisation exige 'current_organisation_id' en session (posé au login) : on le
     * simule directement pour tester check.role indépendamment du flux de connexion.
     */
    private function actingAsInOrg(User $user, Organisation $org): static
    {
        return $this->actingAs($user)->withSession(['current_organisation_id' => $org->id]);
    }

    public function test_visiteur_non_connecte_est_redirige_vers_login(): void
    {
        $this->get('/equipements-industriels')->assertRedirect(route('login'));
    }

    public function test_role_autorise_par_defaut_accede_au_module(): void
    {
        $org = $this->organisation();
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'technicien', 'is_active' => true]);

        $this->actingAsInOrg($user, $org)
            ->get('/equipements-industriels')
            ->assertOk();
    }

    public function test_role_non_autorise_est_rejete(): void
    {
        $org = $this->organisation();
        $user = User::factory()->create();
        // 'magasinier' n'a pas equipements_industriels dans ses modules par défaut.
        $org->users()->attach($user->id, ['role' => 'magasinier', 'is_active' => true]);

        $this->actingAsInOrg($user, $org)
            ->get('/equipements-industriels')
            ->assertForbidden();
    }

    public function test_admin_organisation_accede_meme_hors_liste_de_roles_autorises(): void
    {
        $org = $this->organisation();
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'admin', 'is_active' => true]);

        // La route users.* exige explicitement check.role:admin.
        $this->actingAsInOrg($user, $org)
            ->get('/users')
            ->assertOk();
    }

    public function test_super_admin_accede_a_tout_sans_organisation_en_session(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($user)
            ->get('/users')
            ->assertOk();
    }

    public function test_utilisateur_non_admin_ne_peut_pas_gerer_les_utilisateurs(): void
    {
        $org = $this->organisation();
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'superviseur', 'is_active' => true]);

        $this->actingAsInOrg($user, $org)
            ->get('/users')
            ->assertForbidden();
    }

    public function test_override_revoque_bloque_meme_si_le_role_autorise_par_defaut(): void
    {
        $org = $this->organisation();
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'technicien', 'is_active' => true]);

        UserModulePermission::create([
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'module' => 'equipements_industriels',
            'granted' => false,
        ]);

        $this->actingAsInOrg($user, $org)
            ->get('/equipements-industriels')
            ->assertForbidden();
    }

    public function test_override_accorde_debloque_meme_si_le_role_ne_le_permet_pas_par_defaut(): void
    {
        $org = $this->organisation();
        $user = User::factory()->create();
        // 'magasinier' n'a pas parc_automobile par défaut.
        $org->users()->attach($user->id, ['role' => 'magasinier', 'is_active' => true]);

        UserModulePermission::create([
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'module' => 'parc_automobile',
            'granted' => true,
        ]);

        $this->actingAsInOrg($user, $org)
            ->get('/vehicules')
            ->assertOk();
    }
}
