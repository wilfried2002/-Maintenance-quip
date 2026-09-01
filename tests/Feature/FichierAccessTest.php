<?php

namespace Tests\Feature;

use App\Models\EquipementIndustriel;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Les fichiers métier (photos, documents) vivent sur le disque privé et sont
 * servis par FichierController après vérification du cloisonnement
 * organisation et de la permission sur le module de l'équipement.
 */
class FichierAccessTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(string $code): Organisation
    {
        return Organisation::create(['name' => "Org {$code}", 'code' => $code, 'is_active' => true]);
    }

    private function userAvecRole(Organisation $org, string $role): User
    {
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => $role, 'is_active' => true]);

        return tap($user, fn () => $this->actingAs($user)->withSession(['current_organisation_id' => $org->id]));
    }

    public function test_document_dune_autre_organisation_est_introuvable(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/vehicules/secret.pdf', 'contenu-secret');

        $orgA = $this->organisation('ORGA');
        $orgB = $this->organisation('ORGB');

        $vehiculeB = Vehicule::create([
            'organisation_id' => $orgB->id,
            'code' => 'VH-B',
            'immatriculation' => 'BB-222-BB',
        ]);
        $vehiculeB->documents()->create([
            'organisation_id' => $orgB->id,
            'nom_original' => 'secret.pdf',
            'chemin' => 'documents/vehicules/secret.pdf',
            'type_mime' => 'application/pdf',
            'taille' => 16,
        ]);
        $documentB = $vehiculeB->documents()->first();

        // L'admin A devine l'ID du document de l'org B : le scope global du modèle
        // Document rend le binding introuvable.
        $this->userAvecRole($orgA, 'admin')
            ->get("/fichiers/documents/{$documentB->id}")
            ->assertNotFound();
    }

    public function test_photo_dun_equipement_dune_autre_organisation_est_introuvable(): void
    {
        Storage::fake('local');

        $orgA = $this->organisation('ORGA');
        $orgB = $this->organisation('ORGB');

        $vehiculeB = Vehicule::create([
            'organisation_id' => $orgB->id,
            'code' => 'VH-B2',
            'immatriculation' => 'CC-333-CC',
            'photo' => 'vehicules/photo.jpg',
        ]);

        $this->userAvecRole($orgA, 'admin')
            ->get("/fichiers/photo/vehicules/{$vehiculeB->id}")
            ->assertNotFound();
    }

    public function test_membre_sans_acces_au_module_obtient_403(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/vehicules/facture.pdf', 'x');

        $org = $this->organisation('ORGA');
        $vehicule = Vehicule::create([
            'organisation_id' => $org->id,
            'code' => 'VH-A',
            'immatriculation' => 'AA-111-AA',
        ]);
        $vehicule->documents()->create([
            'organisation_id' => $org->id,
            'nom_original' => 'facture.pdf',
            'chemin' => 'documents/vehicules/facture.pdf',
            'type_mime' => 'application/pdf',
            'taille' => 1,
        ]);
        $document = $vehicule->documents()->first();

        // Magasinier : aucun module équipement par défaut (config/modules.php).
        $this->userAvecRole($org, 'magasinier')
            ->get("/fichiers/documents/{$document->id}")
            ->assertForbidden();
    }

    public function test_membre_avec_acces_au_module_obtient_le_fichier(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('vehicules/photo.jpg', 'octets-photo');

        $org = $this->organisation('ORGA');
        $vehicule = Vehicule::create([
            'organisation_id' => $org->id,
            'code' => 'VH-A2',
            'immatriculation' => 'DD-444-DD',
            'photo' => 'vehicules/photo.jpg',
        ]);

        $this->userAvecRole($org, 'technicien')
            ->get("/fichiers/photo/vehicules/{$vehicule->id}")
            ->assertOk();

        // StreamedResponse : le contenu passe par echo, on le capture via le buffer.
        $response = $this->get("/fichiers/photo/vehicules/{$vehicule->id}");
        ob_start();
        $response->baseResponse->sendContent();
        $this->assertSame('octets-photo', ob_get_clean());
    }
}
