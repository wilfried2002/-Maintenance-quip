<?php

namespace Tests\Feature\Auth;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inscription = demande de rattachement : le compte est créé membre de
 * l'organisation (rôle « user », inactif) puis doit être activé par un admin.
 * L'utilisateur n'est pas connecté à l'inscription (refus de connexion tant
 * que le compte est inactif).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(): Organisation
    {
        return Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $org = $this->organisation();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organisation_code' => $org->code,
        ]);

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->organisations->contains(fn ($organisation) => $organisation->id === $org->id
            && $organisation->pivot->role === 'user'
            && $organisation->pivot->is_active === false));

        // Pas de connexion automatique : le compte attend son activation.
        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false))->assertSessionHas('status');
    }

    public function test_registration_with_unknown_or_disabled_code_is_rejected_with_generic_message(): void
    {
        // Code inexistant et code d'une organisation désactivée : même message,
        // pour ne pas révéler quels codes organisation existent.
        $disabledOrg = Organisation::create(['name' => 'Off', 'code' => 'OFF-'.uniqid(), 'is_active' => false]);

        foreach (['INEXISTANT', $disabledOrg->code] as $code) {
            $this->post('/register', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'organisation_code' => $code,
            ])->assertSessionHasErrors('organisation_code');
        }

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_pending_user_cannot_log_in_until_activated(): void
    {
        $org = $this->organisation();

        $this->post('/register', [
            'name' => 'Pending User',
            'email' => 'pending@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organisation_code' => $org->code,
        ]);

        $credentials = [
            'email' => 'pending@example.com',
            'password' => 'password',
            'organisation_code' => $org->code,
        ];

        // Compte inactif -> message dédié, pas de session.
        $this->post('/login', $credentials)
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Après activation par un admin : la connexion passe.
        $org->users()->updateExistingPivot(
            User::where('email', 'pending@example.com')->value('id'),
            ['is_active' => true],
        );

        $this->post('/login', $credentials)->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }
}
