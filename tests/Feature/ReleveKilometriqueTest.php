<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ReleveKilometrique;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Historique des relevés kilométriques (10/10) : chaque lecture du compteur est
 * journalisée, le compteur du véhicule suit le dernier relevé et toute hausse
 * via la fiche véhicule alimente aussi l'historique.
 */
class ReleveKilometriqueTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $org;
    private User $responsableParc;
    private User $simpleUtilisateur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);

        $this->responsableParc = User::factory()->create();
        $this->org->users()->attach($this->responsableParc->id, ['role' => 'responsable_parc', 'is_active' => true]);

        $this->simpleUtilisateur = User::factory()->create();
        $this->org->users()->attach($this->simpleUtilisateur->id, ['role' => 'user', 'is_active' => true]);
    }

    private function commeResponsableParc(): void
    {
        $this->actingAs($this->responsableParc)->withSession(['current_organisation_id' => $this->org->id]);
    }

    public function test_le_releve_est_journalise_et_le_compteur_suit(): void
    {
        $this->commeResponsableParc();
        $vehicule = Vehicule::create([
            'organisation_id' => $this->org->id,
            'code' => 'C-1',
            'immatriculation' => 'AA-111-BB',
            'kilometrage_actuel' => 10000,
        ]);

        $this->post("/vehicules/{$vehicule->id}/releves", [
            'kilometrage' => 12500,
            'date_releve' => now()->toDateString(),
            'note' => 'Retour de mission',
        ])->assertSessionHasNoErrors();

        $this->assertSame(12500, $vehicule->fresh()->kilometrage_actuel);
        $this->assertDatabaseHas('releves_kilometriques', [
            'vehicule_id' => $vehicule->id,
            'kilometrage' => 12500,
            'user_id' => $this->responsableParc->id,
        ]);
    }

    public function test_le_compteur_ne_peut_pas_diminuer(): void
    {
        $this->commeResponsableParc();
        $vehicule = Vehicule::create([
            'organisation_id' => $this->org->id,
            'code' => 'C-2',
            'immatriculation' => 'AA-222-BB',
            'kilometrage_actuel' => 50000,
        ]);

        ReleveKilometrique::create([
            'organisation_id' => $this->org->id,
            'vehicule_id' => $vehicule->id,
            'kilometrage' => 50000,
            'date_releve' => now()->toDateString(),
            'source' => 'saisie',
        ]);

        $this->post("/vehicules/{$vehicule->id}/releves", [
            'kilometrage' => 49000,
            'date_releve' => now()->toDateString(),
        ])->assertSessionHasErrors('kilometrage');

        $this->assertSame(50000, $vehicule->fresh()->kilometrage_actuel);
        $this->assertSame(1, ReleveKilometrique::where('vehicule_id', $vehicule->id)->count());
    }

    public function test_une_hausse_via_la_fiche_vehicule_alimente_lhistorique(): void
    {
        $this->commeResponsableParc();
        $vehicule = Vehicule::create([
            'organisation_id' => $this->org->id,
            'code' => 'C-3',
            'immatriculation' => 'AA-333-BB',
            'type_vehicule' => 'vl',
            'statut' => 'en_service',
            'criticite' => 'basse',
            'kilometrage_actuel' => 20000,
        ]);

        $this->put("/vehicules/{$vehicule->id}", [
            'code' => 'C-3',
            'immatriculation' => 'AA-333-BB',
            'type_vehicule' => 'vl',
            'statut' => 'en_service',
            'criticite' => 'basse',
            'kilometrage_actuel' => 21000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('releves_kilometriques', [
            'vehicule_id' => $vehicule->id,
            'kilometrage' => 21000,
            'source' => 'edition_vehicule',
        ]);
    }

    public function test_la_fiche_affiche_lhistorique_des_releves(): void
    {
        $this->commeResponsableParc();
        $vehicule = Vehicule::create([
            'organisation_id' => $this->org->id,
            'code' => 'C-4',
            'immatriculation' => 'AA-444-BB',
        ]);

        foreach ([30000, 31000] as $km) {
            ReleveKilometrique::create([
                'organisation_id' => $this->org->id,
                'vehicule_id' => $vehicule->id,
                'kilometrage' => $km,
                'date_releve' => now()->subDays($km === 30000 ? 10 : 0)->toDateString(),
                'source' => 'saisie',
            ]);
        }

        $this->get("/vehicules/{$vehicule->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('releves', 2));
    }

    public function test_suppression_reservee_a_lauteur_ou_a_un_admin(): void
    {
        $this->commeResponsableParc();
        $vehicule = Vehicule::create([
            'organisation_id' => $this->org->id,
            'code' => 'C-5',
            'immatriculation' => 'AA-555-BB',
        ]);

        $releve = ReleveKilometrique::create([
            'organisation_id' => $this->org->id,
            'vehicule_id' => $vehicule->id,
            'kilometrage' => 1000,
            'date_releve' => now()->toDateString(),
            'source' => 'saisie',
            'user_id' => $this->responsableParc->id,
        ]);

        // Un autre utilisateur simple : refus.
        $this->actingAs($this->simpleUtilisateur)->withSession(['current_organisation_id' => $this->org->id]);
        $this->delete("/vehicules/{$vehicule->id}/releves/{$releve->id}")->assertForbidden();

        // L'auteur : autorisé, compteur recalé.
        $this->commeResponsableParc();
        $this->delete("/vehicules/{$vehicule->id}/releves/{$releve->id}")->assertSessionHas('status');

        $this->assertDatabaseMissing('releves_kilometriques', ['id' => $releve->id]);
        $this->assertSame(0, $vehicule->fresh()->kilometrage_actuel);
    }
}
