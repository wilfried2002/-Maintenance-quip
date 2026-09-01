<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * is_super_admin est volontairement absent du $fillable de User (voir le modèle) :
 * ce test garantit qu'une assignation en masse — typiquement un
 * User::create($request->all()) qui laisserait passer une payload malveillante —
 * ne peut jamais élever un compte au rang de super administrateur.
 *
 * Les factories/seeders ne sont pas concernés : elles écrivent les attributs en
 * brut (elles contournent fillable), comme le prouvent les autres tests qui
 * créent des super admins via User::factory().
 */
class UserMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_super_admin_est_ignore_en_assignation_de_masse(): void
    {
        $user = User::create([
            'name' => 'Attaquant',
            'email' => 'attaquant@example.com',
            'password' => 'mot-de-passe-solide',
            'phone' => null,
            'position' => null,
            'is_super_admin' => true, // doit être silencieusement ignoré
        ]);

        $this->assertFalse(
            $user->fresh()->is_super_admin,
            'is_super_admin ne doit pas être assignable en masse.'
        );
    }
}
