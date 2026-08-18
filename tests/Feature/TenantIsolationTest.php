<?php

namespace Tests\Feature;

use App\Models\EquipementIndustriel;
use App\Models\Organisation;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le cloisonnement SaaS (chaque organisation ne voit que ses propres données) repose sur
 * App\Models\Concerns\BelongsToOrganisation (scope global Eloquent). Ces tests vérifient
 * la propriété de bout en bout, au niveau HTTP — pas seulement que le scope existe, mais
 * qu'une organisation ne peut ni lister ni accéder par IDOR aux données d'une autre.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function adminDans(Organisation $org): User
    {
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'admin', 'is_active' => true]);

        return $user;
    }

    public function test_la_liste_des_equipements_ne_montre_que_ceux_de_lorganisation_courante(): void
    {
        $orgA = Organisation::create(['name' => 'Org A', 'code' => 'ORGA', 'is_active' => true]);
        $orgB = Organisation::create(['name' => 'Org B', 'code' => 'ORGB', 'is_active' => true]);

        EquipementIndustriel::create(['organisation_id' => $orgA->id, 'code' => 'A-1', 'designation' => 'Équipement A']);
        EquipementIndustriel::create(['organisation_id' => $orgB->id, 'code' => 'B-1', 'designation' => 'Équipement B']);

        $adminA = $this->adminDans($orgA);

        $this->actingAs($adminA)
            ->withSession(['current_organisation_id' => $orgA->id])
            ->get('/equipements-industriels')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('equipements', 1)
                ->where('equipements.0.code', 'A-1')
            );
    }

    public function test_acceder_par_id_a_lequipement_dune_autre_organisation_renvoie_404(): void
    {
        $orgA = Organisation::create(['name' => 'Org A', 'code' => 'ORGA', 'is_active' => true]);
        $orgB = Organisation::create(['name' => 'Org B', 'code' => 'ORGB', 'is_active' => true]);

        $equipementB = EquipementIndustriel::create(['organisation_id' => $orgB->id, 'code' => 'B-1', 'designation' => 'Équipement B']);

        $adminA = $this->adminDans($orgA);

        // Tentative d'IDOR : deviner l'id d'un équipement d'une autre organisation dans l'URL.
        $this->actingAs($adminA)
            ->withSession(['current_organisation_id' => $orgA->id])
            ->get("/equipements-industriels/{$equipementB->id}")
            ->assertNotFound();
    }

    public function test_le_stock_de_pieces_est_cloisonne_par_organisation(): void
    {
        $orgA = Organisation::create(['name' => 'Org A', 'code' => 'ORGA', 'is_active' => true]);
        $orgB = Organisation::create(['name' => 'Org B', 'code' => 'ORGB', 'is_active' => true]);

        Piece::create(['organisation_id' => $orgA->id, 'reference' => 'A-REF', 'designation' => 'Pièce A', 'stock_qte' => 10, 'stock_min' => 1, 'module' => 'equipements_industriels']);
        Piece::create(['organisation_id' => $orgB->id, 'reference' => 'B-REF', 'designation' => 'Pièce B', 'stock_qte' => 10, 'stock_min' => 1, 'module' => 'equipements_industriels']);

        $adminA = $this->adminDans($orgA);

        // Le stock est désormais cloisonné par module (voir HandlesPieces) : la liste
        // passe par la route dédiée au module, plus par l'ancien /pieces générique.
        $this->actingAs($adminA)
            ->withSession(['current_organisation_id' => $orgA->id])
            ->get('/equipements-industriels/pieces')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pieces', 1)
                ->where('pieces.0.reference', 'A-REF')
            );
    }

    public function test_creer_un_equipement_lattache_automatiquement_a_lorganisation_courante(): void
    {
        $org = Organisation::create(['name' => 'Org A', 'code' => 'ORGA', 'is_active' => true]);
        $admin = $this->adminDans($org);

        $this->actingAs($admin)
            ->withSession(['current_organisation_id' => $org->id])
            ->post('/equipements-industriels', [
                'code' => 'NEW-1',
                'designation' => 'Nouvel équipement',
                'statut' => 'en_service',
                'criticite' => 'moyenne',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('equipements_industriels', [
            'code' => 'NEW-1',
            'organisation_id' => $org->id,
        ]);
    }
}
