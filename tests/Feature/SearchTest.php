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

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(Organisation $org, string $role): User
    {
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => $role, 'is_active' => true]);

        return tap($user, fn () => $this->actingAs($user)->withSession(['current_organisation_id' => $org->id]));
    }

    public function test_recherche_trouve_un_equipement_par_code(): void
    {
        $org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
        EquipementIndustriel::create(['organisation_id' => $org->id, 'code' => 'IND-050', 'designation' => 'Compresseur atelier']);
        $this->userWithRole($org, 'technicien');

        $this->getJson('/search?q=IND-050')
            ->assertOk()
            ->assertJsonPath('results.0.group', 'equipements_industriels')
            ->assertJsonFragment(['title' => 'IND-050 — Compresseur atelier']);
    }

    public function test_recherche_trouve_un_vehicule_par_immatriculation_pas_par_designation(): void
    {
        $org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
        Vehicule::create(['organisation_id' => $org->id, 'code' => 'VH-010', 'immatriculation' => 'AB-123-CD']);
        $this->userWithRole($org, 'technicien');

        $this->getJson('/search?q=AB-123-CD')
            ->assertOk()
            ->assertJsonPath('results.0.group', 'parc_automobile');
    }

    public function test_recherche_trouve_une_intervention_par_titre(): void
    {
        $org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
        $equipement = EquipementIndustriel::create(['organisation_id' => $org->id, 'code' => 'IND-060', 'designation' => 'Pompe']);
        Intervention::create([
            'organisation_id' => $org->id,
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $equipement->id,
            'titre' => 'Remplacement joint hydraulique',
        ]);
        $this->userWithRole($org, 'technicien');

        $this->getJson('/search?q=' . urlencode('joint hydraulique'))
            ->assertOk()
            ->assertJsonFragment(['group' => 'interventions']);
    }

    public function test_recherche_trouve_une_piece_par_reference(): void
    {
        $org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
        Piece::create([
            'organisation_id' => $org->id,
            'reference' => 'PC-777',
            'designation' => 'Joint',
            'stock_qte' => 5,
            'stock_min' => 1,
            'module' => 'equipements_industriels',
        ]);
        $this->userWithRole($org, 'technicien');

        $this->getJson('/search?q=PC-777')
            ->assertOk()
            ->assertJsonFragment(['group' => 'pieces']);
    }

    public function test_recherche_respecte_les_permissions_par_module(): void
    {
        $org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
        EquipementIndustriel::create(['organisation_id' => $org->id, 'code' => 'IND-070', 'designation' => 'Turbine']);
        Piece::create([
            'organisation_id' => $org->id,
            'reference' => 'PC-888',
            'designation' => 'Turbine roulement',
            'stock_qte' => 5,
            'stock_min' => 1,
            'module' => 'equipements_industriels',
        ]);
        // magasinier a pieces_stock mais aucun accès équipement (voir config/modules.php).
        $this->userWithRole($org, 'magasinier');

        $response = $this->getJson('/search?q=Turbine')->assertOk();
        $groups = collect($response->json('results'))->pluck('group');

        $this->assertFalse($groups->contains('equipements_industriels'));
        $this->assertTrue($groups->contains('pieces'));
    }

    public function test_recherche_est_cloisonnee_par_organisation(): void
    {
        $orgA = Organisation::create(['name' => 'Org A', 'code' => 'ORGA-'.uniqid(), 'is_active' => true]);
        $orgB = Organisation::create(['name' => 'Org B', 'code' => 'ORGB-'.uniqid(), 'is_active' => true]);

        EquipementIndustriel::create(['organisation_id' => $orgB->id, 'code' => 'IND-080', 'designation' => 'Autre organisation']);
        $this->userWithRole($orgA, 'technicien');

        $this->getJson('/search?q=IND-080')
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_recherche_sous_deux_caracteres_ne_fait_aucune_requete(): void
    {
        $org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
        $this->userWithRole($org, 'technicien');

        $this->getJson('/search?q=a')
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }
}
