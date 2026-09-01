<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Piece;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mouvements de stock : entrées/sorties/ajustements journalisés avec le stock
 * résultant, verrou pessimiste, motif obligatoire hors entrée, et contrôle du
 * module de stock par pièce (un responsable_parc ne touche pas le stock industriel).
 */
class MouvementStockTest extends TestCase
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

        return tap($user, fn () => $this->actingAs($user)->withSession(['current_organisation_id' => $org->id]));
    }

    private function piece(Organisation $org, string $module = 'equipements_industriels', int $stock = 10): Piece
    {
        return Piece::create([
            'organisation_id' => $org->id,
            'reference' => 'P-'.uniqid(),
            'designation' => 'Filtre',
            'stock_qte' => $stock,
            'stock_min' => 2,
            'module' => $module,
        ]);
    }

    public function test_entree_augmente_le_stock_et_journalise(): void
    {
        $org = $this->organisation();
        $this->userAvecRole($org, 'magasinier');
        $piece = $this->piece($org);

        $this->post("/pieces/{$piece->id}/mouvements", [
            'type' => 'entree',
            'quantite' => 5,
            'motif' => 'Réapprovisionnement fournisseur',
        ])->assertSessionHasNoErrors();

        $this->assertSame(15, $piece->fresh()->stock_qte);
        $this->assertDatabaseHas('mouvements_stock', [
            'piece_id' => $piece->id,
            'type' => 'entree',
            'quantite' => 5,
            'stock_apres' => 15,
            'organisation_id' => $org->id,
        ]);
    }

    public function test_sortie_insuffisante_est_rejetee_avec_motif_requis(): void
    {
        $org = $this->organisation();
        $this->userAvecRole($org, 'magasinier');
        $piece = $this->piece($org, stock: 4);

        // Sans motif : rejet.
        $this->post("/pieces/{$piece->id}/mouvements", [
            'type' => 'sortie',
            'quantite' => 2,
        ])->assertSessionHasErrors('motif');

        // Quantité supérieure au stock : rejet, stock inchangé.
        $this->post("/pieces/{$piece->id}/mouvements", [
            'type' => 'sortie',
            'quantite' => 9,
            'motif' => 'Casse',
        ])->assertSessionHasErrors('quantite');

        $this->assertSame(4, $piece->fresh()->stock_qte);
        $this->assertDatabaseMissing('mouvements_stock', ['piece_id' => $piece->id]);
    }

    public function test_ajustement_fixe_le_stock_physique_compte(): void
    {
        $org = $this->organisation();
        $this->userAvecRole($org, 'magasinier');
        $piece = $this->piece($org, stock: 10);

        $this->post("/pieces/{$piece->id}/mouvements", [
            'type' => 'ajustement',
            'quantite' => 7,
            'motif' => 'Inventaire annuel',
        ])->assertSessionHasNoErrors();

        $this->assertSame(7, $piece->fresh()->stock_qte);
        $this->assertDatabaseHas('mouvements_stock', [
            'type' => 'ajustement',
            'quantite' => 7,
            'stock_apres' => 7,
        ]);
    }

    public function test_responsable_parc_ne_touche_pas_le_stock_industriel(): void
    {
        $org = $this->organisation();
        $this->userAvecRole($org, 'responsable_parc');
        $piece = $this->piece($org, 'equipements_industriels', 10);

        $this->post("/pieces/{$piece->id}/mouvements", [
            'type' => 'entree',
            'quantite' => 5,
        ])->assertForbidden();

        $this->assertSame(10, $piece->fresh()->stock_qte);
    }

    public function test_role_sans_stock_est_rejete_par_le_middleware(): void
    {
        $org = $this->organisation();
        $this->userAvecRole($org, 'user');
        $piece = $this->piece($org);

        $this->post("/pieces/{$piece->id}/mouvements", [
            'type' => 'entree',
            'quantite' => 5,
        ])->assertForbidden();
    }

    public function test_le_journal_est_limite_aux_modules_geres(): void
    {
        $org = $this->organisation();
        $magasinier = $this->userAvecRole($org, 'magasinier');
        $pieceIndustrielle = $this->piece($org, 'equipements_industriels');
        $pieceParc = $this->piece($org, 'parc_automobile');

        // Un véhicule de l'org pour que le module parc soit cohérent.
        Vehicule::create(['organisation_id' => $org->id, 'code' => 'VH-1', 'immatriculation' => 'AA-111-AA']);

        $this->post("/pieces/{$pieceIndustrielle->id}/mouvements", ['type' => 'entree', 'quantite' => 3]);
        $this->post("/pieces/{$pieceParc->id}/mouvements", ['type' => 'entree', 'quantite' => 3]);

        $response = $this->get('/mouvements-stock');
        $response->assertOk();

        // Magasinier gère le stock industriel ET parc auto (ROLES_STOCK_PAR_MODULE)
        // → les deux mouvements figurent au journal.
        $response->assertInertia(fn ($page) => $page->has('mouvements', 2));
    }
}
