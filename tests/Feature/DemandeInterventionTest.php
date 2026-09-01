<?php

namespace Tests\Feature;

use App\Models\DemandeIntervention;
use App\Models\Intervention;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicule;
use App\Notifications\DemandeInterventionSoumise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * Workflow des demandes d'intervention : soumission par un utilisateur final,
 * notification des responsables du module, approbation/refus motivé, conversion
 * en intervention planifiée — plus la vue calendrier limitée aux modules gérés.
 */
class DemandeInterventionTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $org;
    private User $demandeur;
    private User $responsableMaintenance;
    private User $responsableParc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);

        $this->demandeur = User::factory()->create();
        $this->org->users()->attach($this->demandeur->id, ['role' => 'user', 'is_active' => true]);

        $this->responsableMaintenance = User::factory()->create();
        $this->org->users()->attach($this->responsableMaintenance->id, ['role' => 'responsable_maintenance', 'is_active' => true]);

        $this->responsableParc = User::factory()->create();
        $this->org->users()->attach($this->responsableParc->id, ['role' => 'responsable_parc', 'is_active' => true]);
    }

    private function commeDemandeur(): void
    {
        $this->actingAs($this->demandeur)->withSession(['current_organisation_id' => $this->org->id]);
    }

    private function commeResponsable(User $user): void
    {
        $this->actingAs($user)->withSession(['current_organisation_id' => $this->org->id]);
    }

    public function test_workflow_complet_de_la_soumission_a_la_conversion(): void
    {
        NotificationFacade::fake();

        $vehicule = Vehicule::create([
            'organisation_id' => $this->org->id,
            'code' => 'C-1',
            'immatriculation' => 'AA-111-BB',
        ]);

        // 1) Le demandeur soumet.
        $this->commeDemandeur();
        $this->post('/demandes', [
            'module' => 'parc_automobile',
            'equipementable_id' => $vehicule->id,
            'titre' => 'Freins qui grincent',
            'description' => 'Bruits métalliques au freinage.',
            'priorite' => 'haute',
        ])->assertSessionHasNoErrors();

        $demande = DemandeIntervention::query()->where('demandeur_id', $this->demandeur->id)->first();
        $this->assertNotNull($demande);
        $this->assertSame('soumise', $demande->statut);

        // Les responsables du module sont notifiés (le demandeur non).
        NotificationFacade::assertSentTo($this->responsableMaintenance, DemandeInterventionSoumise::class);
        NotificationFacade::assertNotSentTo($this->demandeur, DemandeInterventionSoumise::class);

        // 2) Le responsable du module approuve.
        $this->commeResponsable($this->responsableMaintenance);
        $this->post("/demandes/{$demande->id}/decision", ['action' => 'approuver'])
            ->assertSessionHasNoErrors();

        $this->assertSame('approuvee', $demande->fresh()->statut);
        $this->assertSame($this->responsableMaintenance->id, $demande->fresh()->decideur_id);

        // 3) Conversion en intervention planifiée.
        $this->post("/demandes/{$demande->id}/convertir", [
            'date_planifiee' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $demande = $demande->fresh();
        $this->assertSame('convertie', $demande->statut);
        $this->assertNotNull($demande->intervention_id);

        $intervention = Intervention::find($demande->intervention_id);
        $this->assertSame('Freins qui grincent', $intervention->titre);
        $this->assertSame('planifiee', $intervention->statut);
        $this->assertSame('haute', $intervention->priorite);
        $this->assertSame($vehicule->id, $intervention->equipementable_id);
    }

    public function test_refus_exige_un_motif(): void
    {
        $this->commeDemandeur();
        $this->post('/demandes', ['module' => 'parc_automobile', 'titre' => 'Vitre bloquée']);
        $demande = DemandeIntervention::first();

        $this->commeResponsable($this->responsableParc);
        $this->post("/demandes/{$demande->id}/decision", ['action' => 'refuser'])
            ->assertSessionHasErrors('motif_decision');

        $this->post("/demandes/{$demande->id}/decision", ['action' => 'refuser', 'motif_decision' => 'Hors périmètre'])
            ->assertSessionHasNoErrors();

        $this->assertSame('refusee', $demande->fresh()->statut);
        $this->assertSame('Hors périmètre', $demande->fresh()->motif_decision);
    }

    public function test_un_responsable_parc_ne_decide_pas_dune_demande_industrielle(): void
    {
        $this->commeDemandeur();
        $this->post('/demandes', ['module' => 'equipements_industriels', 'titre' => 'Fuite d\'huile compresseur']);
        $demande = DemandeIntervention::first();

        $this->commeResponsable($this->responsableParc);
        $this->post("/demandes/{$demande->id}/decision", ['action' => 'approuver'])->assertForbidden();
    }

    public function test_la_file_est_limitee_aux_modules_geres(): void
    {
        $this->commeDemandeur();
        $this->post('/demandes', ['module' => 'parc_automobile', 'titre' => 'Demande parc']);
        $this->post('/demandes', ['module' => 'equipements_industriels', 'titre' => 'Demande industriel']);

        $this->commeResponsable($this->responsableParc);
        $this->get('/demandes')->assertInertia(fn ($page) => $page->has('demandes.data', 1));

        $this->commeResponsable($this->responsableMaintenance);
        $this->get('/demandes')->assertInertia(fn ($page) => $page->has('demandes.data', 2));
    }

    public function test_le_calendrier_naffiche_que_les_modules_accessibles(): void
    {
        $vehicule = Vehicule::create([
            'organisation_id' => $this->org->id,
            'code' => 'C-9',
            'immatriculation' => 'AA-999-BB',
        ]);

        Intervention::create([
            'equipementable_type' => Vehicule::class,
            'equipementable_id' => $vehicule->id,
            'type_intervention' => 'corrective',
            'statut' => 'planifiee',
            'priorite' => 'normale',
            'titre' => 'Révision des 100 000 km',
            'date_planifiee' => now()->startOfMonth()->addDays(3),
        ]);

        $this->commeResponsable($this->responsableParc);
        $mois = now()->format('Y-m');
        $this->get("/calendrier?mois={$mois}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('evenements', 1)->where('evenements.0.titre', 'Révision des 100 000 km'));
    }
}
