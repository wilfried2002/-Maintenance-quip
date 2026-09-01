<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Workflow de statut des interventions (reintgré depuis le commit du 31/08 du
 * dépôt principal) : le technicien assigné démarre/termine/annule, un admin
 * peut le faire aussi ; transitions contrôlées, dates horodatées, notifications
 * à l'assignation et au changement de statut.
 */
class InterventionStatutTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $org;
    private User $admin;
    private User $technicien;
    private User $simpleUtilisateur;
    private Vehicule $vehicule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);

        $this->admin = User::factory()->create();
        $this->org->users()->attach($this->admin->id, ['role' => 'admin', 'is_active' => true]);

        $this->technicien = User::factory()->create();
        $this->org->users()->attach($this->technicien->id, ['role' => 'technicien', 'is_active' => true]);

        $this->simpleUtilisateur = User::factory()->create();
        $this->org->users()->attach($this->simpleUtilisateur->id, ['role' => 'user', 'is_active' => true]);

        $this->vehicule = Vehicule::create([
            'organisation_id' => $this->org->id,
            'code' => 'C-1',
            'immatriculation' => 'AA-111-BB',
        ]);
    }

    private function comme(User $user): void
    {
        $this->actingAs($user)->withSession(['current_organisation_id' => $this->org->id]);
    }

    private function interventionPlanifiee(): Intervention
    {
        return Intervention::create([
            'organisation_id' => $this->org->id,
            'equipementable_type' => Vehicule::class,
            'equipementable_id' => $this->vehicule->id,
            'type_intervention' => 'corrective',
            'statut' => 'planifiee',
            'priorite' => 'normale',
            'titre' => 'Freins qui grincent',
            'date_planifiee' => now()->addDays(2),
            'technicien_id' => $this->technicien->id,
        ]);
    }

    public function test_lassignation_a_la_creation_notifie_le_technicien(): void
    {
        $this->comme($this->admin);

        $this->post('/vehicules/interventions', [
            'equipementable_id' => $this->vehicule->id,
            'type_intervention' => 'corrective',
            'statut' => 'planifiee',
            'priorite' => 'normale',
            'titre' => 'Vidange',
            'date_planifiee' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'technicien_id' => $this->technicien->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notifications', [
            'type' => 'App\Notifications\InterventionAssignee',
            'notifiable_id' => $this->technicien->id,
        ]);
    }

    public function test_le_technicien_assigne_demarre_et_termine(): void
    {
        $this->comme($this->technicien);
        $intervention = $this->interventionPlanifiee();

        // Démarrage : planifiee → en_cours, date_debut horodatée.
        $this->post("/interventions/{$intervention->id}/status", ['statut' => 'en_cours'])
            ->assertSessionHasNoErrors();

        $intervention = $intervention->fresh();
        $this->assertSame('en_cours', $intervention->statut);
        $this->assertNotNull($intervention->date_debut);

        // L'admin (non acteur) est notifié, pas le technicien acteur.
        $this->assertDatabaseHas('notifications', [
            'type' => 'App\Notifications\InterventionStatusUpdated',
            'notifiable_id' => $this->admin->id,
        ]);

        // Terminaison : en_cours → terminee, date_fin horodatée.
        $this->post("/interventions/{$intervention->id}/status", ['statut' => 'terminee'])
            ->assertSessionHasNoErrors();

        $intervention = $intervention->fresh();
        $this->assertSame('terminee', $intervention->statut);
        $this->assertNotNull($intervention->date_fin);
    }

    public function test_transition_invalidre_est_rejetee(): void
    {
        $this->comme($this->technicien);
        $intervention = $this->interventionPlanifiee();

        // planifiee → terminee interdit (il faut passer par en_cours).
        $this->post("/interventions/{$intervention->id}/status", ['statut' => 'terminee'])
            ->assertSessionHasErrors('statut');

        $this->assertSame('planifiee', $intervention->fresh()->statut);
    }

    public function test_utilisateur_non_assigne_et_non_admin_est_refuse(): void
    {
        $this->comme($this->simpleUtilisateur);
        $intervention = $this->interventionPlanifiee();

        $this->post("/interventions/{$intervention->id}/status", ['statut' => 'en_cours'])
            ->assertForbidden();
    }

    public function test_admin_peut_annuler(): void
    {
        $this->comme($this->admin);
        $intervention = $this->interventionPlanifiee();

        $this->post("/interventions/{$intervention->id}/status", ['statut' => 'annulee'])
            ->assertSessionHasNoErrors();

        $this->assertSame('annulee', $intervention->fresh()->statut);
    }
}
