<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        // super_admin : les utilisateurs normaux doivent en plus fournir un code
        // organisation valide, voir AuthenticatedSessionControllerTest ci-dessous.
        $user = User::factory()->create(['is_super_admin' => true]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_utilisateur_organisation_doit_fournir_un_code_valide(): void
    {
        // Le contrôleur de login uppercase le code soumis : le stocker déjà en majuscules.
        $org = \App\Models\Organisation::create(['name' => 'Démo', 'code' => strtoupper('DEMO-'.uniqid()), 'is_active' => true]);
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'technicien', 'is_active' => true]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('organisation_code');
        $this->assertGuest();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'organisation_code' => $org->code,
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
