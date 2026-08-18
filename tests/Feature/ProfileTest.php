<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        // super_admin : /profile est protégé par check.organisation, qui exige sinon une
        // organisation sélectionnée en session (posée normalement au login).
        $user = User::factory()->create(['is_super_admin' => true]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        // super_admin : /profile est protégé par check.organisation, qui exige sinon une
        // organisation sélectionnée en session (posée normalement au login).
        $user = User::factory()->create(['is_super_admin' => true]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        // super_admin : /profile est protégé par check.organisation, qui exige sinon une
        // organisation sélectionnée en session (posée normalement au login).
        $user = User::factory()->create(['is_super_admin' => true]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        // super_admin : /profile est protégé par check.organisation, qui exige sinon une
        // organisation sélectionnée en session (posée normalement au login).
        $user = User::factory()->create(['is_super_admin' => true]);

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        // User utilise SoftDeletes : la ligne persiste avec deleted_at renseigné plutôt
        // que d'être réellement supprimée.
        $this->assertSoftDeleted($user);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        // super_admin : /profile est protégé par check.organisation, qui exige sinon une
        // organisation sélectionnée en session (posée normalement au login).
        $user = User::factory()->create(['is_super_admin' => true]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
