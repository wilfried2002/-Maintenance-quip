<?php

namespace Tests\Feature;

use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\Organisation;
use App\Models\Piece;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exports PDF (9/10) : fiche équipement, étiquette QR et listes — générés par
 * le moteur maison (PdfDocument) sans dépendance externe.
 */
class RapportPdfTest extends TestCase
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

    private function vehicule(): Vehicule
    {
        return Vehicule::create([
            'organisation_id' => $this->org->id,
            'code' => 'C-PDF',
            'immatriculation' => 'AA-PDF-42',
            'marque' => 'Toyota',
            'statut' => 'en_service',
        ]);
    }

    public function test_fiche_equipement_est_un_pdf_valide(): void
    {
        $this->commeResponsableParc();
        $vehicule = $this->vehicule();

        $reponse = $this->get("/rapports/fiche/vehicules/{$vehicule->id}");

        $reponse->assertOk();
        $this->assertSame('application/pdf', $reponse->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $reponse->getContent());
    }

    public function test_etiquette_avec_qr_est_un_pdf_valide(): void
    {
        $this->commeResponsableParc();
        $vehicule = $this->vehicule();

        $reponse = $this->get("/rapports/etiquette/vehicules/{$vehicule->id}");

        $reponse->assertOk();
        $this->assertStringStartsWith('%PDF-', $reponse->getContent());
    }

    public function test_listes_pdf_equipe_interventions_pieces(): void
    {
        $this->commeResponsableParc();
        $vehicule = $this->vehicule();

        // Le responsable parc gère le stock du module parc (ROLES_STOCK_PAR_MODULE).
        Piece::create([
            'organisation_id' => $this->org->id,
            'reference' => 'P-PARC',
            'designation' => 'Plaquettes',
            'module' => 'parc_automobile',
        ]);

        Intervention::create([
            'equipementable_type' => Vehicule::class,
            'equipementable_id' => $vehicule->id,
            'type_intervention' => 'corrective',
            'statut' => 'terminee',
            'priorite' => 'haute',
            'titre' => 'Remplacement plaquettes',
            'date_planifiee' => now(),
        ]);

        foreach (['equipements', 'interventions', 'pieces'] as $quoi) {
            $reponse = $this->get("/rapports/liste/vehicules/{$quoi}");

            $reponse->assertOk();
            $this->assertStringStartsWith('%PDF-', $reponse->getContent());
        }
    }

    public function test_acces_refuse_sans_acces_au_module(): void
    {
        $this->actingAs($this->simpleUtilisateur)->withSession(['current_organisation_id' => $this->org->id]);
        $vehicule = $this->vehicule();

        $this->get("/rapports/fiche/vehicules/{$vehicule->id}")->assertForbidden();
        $this->get("/rapports/etiquette/vehicules/{$vehicule->id}")->assertForbidden();
        $this->get('/rapports/liste/vehicules/equipements')->assertForbidden();
        $this->get('/rapports/liste/vehicules/pieces')->assertForbidden();
    }

    public function test_responsable_parc_nexporte_pas_les_pieces_industrielles(): void
    {
        $this->commeResponsableParc();

        $this->get('/rapports/liste/equipements-industriels/pieces')->assertForbidden();
    }

    public function test_equipement_dune_autre_organisation_introuvable(): void
    {
        $this->commeResponsableParc();

        $autreOrg = Organisation::create(['name' => 'Autre', 'code' => 'AUTRE-'.uniqid(), 'is_active' => true]);
        $equipement = EquipementIndustriel::create([
            'organisation_id' => $autreOrg->id,
            'code' => 'IND-X',
            'designation' => 'Presse',
        ]);

        $this->get("/rapports/fiche/equipements-industriels/{$equipement->id}")->assertNotFound();
    }
}
