<?php

namespace Tests\Feature;

use App\Models\EquipementIndustriel;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Alertes d'expiration de garantie (alertes:generer) : notifie les utilisateurs
 * ayant accès au module d'un équipement dont la garantie expire sous 30 jours
 * (ou est expirée), et résout l'alerte quand la garantie est repoussée.
 */
class GenererAlertesGarantiesTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(): Organisation
    {
        return Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
    }

    private function userAvecRole(Organisation $org, string $role): User
    {
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => $role, 'is_active' => true]);

        return $user;
    }

    private function equipement(Organisation $org, ?string $garantie): EquipementIndustriel
    {
        return EquipementIndustriel::create([
            'organisation_id' => $org->id,
            'code' => 'IND-'.uniqid(),
            'designation' => 'Compresseur',
            'date_fin_garantie' => $garantie,
        ]);
    }

    public function test_garantie_expirant_sous_30_jours_notifie_le_module(): void
    {
        $org = $this->organisation();
        $responsable = $this->userAvecRole($org, 'responsable_maintenance');
        $this->userAvecRole($org, 'magasinier'); // sans accès au module : pas notifié

        $this->equipement($org, now()->addDays(10)->toDateString());

        Artisan::call('alertes:generer');

        $this->assertSame(1, $responsable->unreadNotifications()->count());
        $notification = $responsable->unreadNotifications()->first();
        $this->assertSame('garantie', $notification->data['kind']);
        $this->assertStringContainsString('garantie', $notification->data['title']);
    }

    public function test_garantie_deja_expiree_notifie_aussi(): void
    {
        $org = $this->organisation();
        $responsable = $this->userAvecRole($org, 'responsable_maintenance');

        $this->equipement($org, now()->subDays(5)->toDateString());

        Artisan::call('alertes:generer');

        $this->assertSame(1, $responsable->unreadNotifications()->count());
    }

    public function test_garantie_au_dela_du_seuil_ne_notifie_pas(): void
    {
        $org = $this->organisation();
        $responsable = $this->userAvecRole($org, 'responsable_maintenance');

        $this->equipement($org, now()->addDays(60)->toDateString());

        Artisan::call('alertes:generer');

        $this->assertSame(0, $responsable->unreadNotifications()->count());
    }

    public function test_alerte_resolue_quand_la_garantie_est_repoussee(): void
    {
        $org = $this->organisation();
        $responsable = $this->userAvecRole($org, 'responsable_maintenance');
        $equipement = $this->equipement($org, now()->addDays(5)->toDateString());

        Artisan::call('alertes:generer');
        $this->assertSame(1, $responsable->unreadNotifications()->count());

        // Garantie prolongée au-delà du seuil : l'alerte non lue est marquée lue.
        $equipement->update(['date_fin_garantie' => now()->addDays(120)->toDateString()]);

        Artisan::call('alertes:generer');

        $this->assertSame(0, $responsable->unreadNotifications()->count());
    }

    public function test_pas_de_double_alerte_tant_que_non_lue(): void
    {
        $org = $this->organisation();
        $responsable = $this->userAvecRole($org, 'responsable_maintenance');

        $this->equipement($org, now()->addDays(3)->toDateString());

        Artisan::call('alertes:generer');
        Artisan::call('alertes:generer');

        $this->assertSame(1, $responsable->unreadNotifications()->count());
    }
}
