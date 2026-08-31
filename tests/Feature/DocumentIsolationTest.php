<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cloisonnement des documents (photos, factures, contrats… rattachés aux
 * équipements) : une organisation ne doit pouvoir supprimer ni par ID ni par URL
 * les documents d'une autre organisation, ni même ceux d'un autre équipement de
 * sa propre organisation. Même propriété de bout en bout que TenantIsolationTest.
 */
class DocumentIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function adminDans(Organisation $org): User
    {
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'admin', 'is_active' => true]);

        return $user;
    }

    private function organisation(string $code): Organisation
    {
        return Organisation::create(['name' => "Org {$code}", 'code' => $code, 'is_active' => true]);
    }

    private function vehiculeDans(Organisation $org, string $code): Vehicule
    {
        return Vehicule::create([
            'organisation_id' => $org->id,
            'code' => $code,
            'immatriculation' => $code.'-AA-001',
        ]);
    }

    private function documentSur(Vehicule $vehicule, string $chemin): void
    {
        $vehicule->documents()->create([
            'organisation_id' => $vehicule->organisation_id,
            'nom_original' => 'facture.pdf',
            'chemin' => $chemin,
            'type_mime' => 'application/pdf',
            'taille' => 1024,
        ]);
    }

    public function test_supprimer_par_id_le_document_dune_autre_organisation_renvoie_404(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('documents/vehicules/b.pdf', 'x');

        $orgA = $this->organisation('ORGA');
        $orgB = $this->organisation('ORGB');

        $vehiculeA = $this->vehiculeDans($orgA, 'VH-A');
        $vehiculeB = $this->vehiculeDans($orgB, 'VH-B');
        $this->documentSur($vehiculeB, 'documents/vehicules/b.pdf');
        $documentB = $vehiculeB->documents()->first();

        // IDOR : l'admin A utilise l'ID du document de l'org B dans l'URL.
        $this->actingAs($this->adminDans($orgA))
            ->withSession(['current_organisation_id' => $orgA->id])
            ->delete("/vehicules/{$vehiculeA->id}/documents/{$documentB->id}")
            ->assertNotFound();

        // Ni la ligne, ni le fichier ne doivent avoir été touchés.
        $this->assertDatabaseHas('documents', ['id' => $documentB->id]);
        Storage::disk('public')->assertExists('documents/vehicules/b.pdf');
    }

    public function test_supprimer_par_id_le_document_dun_autre_equipement_de_la_meme_organisation_renvoie_404(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('documents/vehicules/a2.pdf', 'x');

        $orgA = $this->organisation('ORGA');
        $vehiculeA1 = $this->vehiculeDans($orgA, 'VH-A1');
        $vehiculeA2 = $this->vehiculeDans($orgA, 'VH-A2');
        $this->documentSur($vehiculeA2, 'documents/vehicules/a2.pdf');
        $documentA2 = $vehiculeA2->documents()->first();

        // L'URL vise le véhicule A1 mais le document appartient au véhicule A2 :
        // la vérification d'appartenance dans HandlesDocuments::destroyDocument
        // doit bloquer, sinon n'importe quel membre peut effacer le document
        // d'un autre équipement de la même organisation.
        $this->actingAs($this->adminDans($orgA))
            ->withSession(['current_organisation_id' => $orgA->id])
            ->delete("/vehicules/{$vehiculeA1->id}/documents/{$documentA2->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('documents', ['id' => $documentA2->id]);
        Storage::disk('public')->assertExists('documents/vehicules/a2.pdf');
    }

    public function test_supprimer_son_propre_document_fonctionne_toujours(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('documents/vehicules/a1.pdf', 'x');

        $orgA = $this->organisation('ORGA');
        $vehiculeA = $this->vehiculeDans($orgA, 'VH-A');
        $this->documentSur($vehiculeA, 'documents/vehicules/a1.pdf');
        $document = $vehiculeA->documents()->first();

        $this->actingAs($this->adminDans($orgA))
            ->withSession(['current_organisation_id' => $orgA->id])
            ->delete("/vehicules/{$vehiculeA->id}/documents/{$document->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('public')->assertMissing('documents/vehicules/a1.pdf');
    }
}
