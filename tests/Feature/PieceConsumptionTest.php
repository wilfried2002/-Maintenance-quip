<?php

namespace Tests\Feature;

use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\Organisation;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PieceConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'technicien', 'is_active' => true]);

        return tap($user, fn () => $this->actingAs($user)->withSession(['current_organisation_id' => $org->id]));
    }

    private function intervention(): Intervention
    {
        $equipement = EquipementIndustriel::create(['code' => 'IND-050', 'designation' => 'Compresseur']);

        return Intervention::create([
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $equipement->id,
            'titre' => 'Réparation',
        ]);
    }

    public function test_consommer_une_piece_decremente_le_stock_et_fige_le_prix(): void
    {
        $this->actingUser();
        $intervention = $this->intervention();
        $piece = Piece::create([
            'reference' => 'PC-001',
            'designation' => 'Joint',
            'stock_qte' => 10,
            'stock_min' => 2,
            'prix_unitaire_moyen' => 15.50,
            'module' => 'equipements_industriels',
        ]);

        $this->post("/interventions/{$intervention->id}/pieces", [
            'piece_id' => $piece->id,
            'quantite' => 3,
        ])->assertSessionHasNoErrors();

        $this->assertSame(7, $piece->fresh()->stock_qte);
        $this->assertDatabaseHas('intervention_pieces', [
            'intervention_id' => $intervention->id,
            'piece_id' => $piece->id,
            'quantite' => 3,
            'prix_unitaire' => 15.50,
        ]);
    }

    public function test_stock_insuffisant_est_rejete_sans_effet_de_bord(): void
    {
        $this->actingUser();
        $intervention = $this->intervention();
        $piece = Piece::create([
            'reference' => 'PC-002',
            'designation' => 'Roulement',
            'stock_qte' => 2,
            'stock_min' => 1,
            'prix_unitaire_moyen' => 40,
            'module' => 'equipements_industriels',
        ]);

        $this->post("/interventions/{$intervention->id}/pieces", [
            'piece_id' => $piece->id,
            'quantite' => 5,
        ])->assertSessionHasErrors('quantite');

        $this->assertSame(2, $piece->fresh()->stock_qte);
        $this->assertDatabaseMissing('intervention_pieces', ['piece_id' => $piece->id]);
    }

    public function test_retirer_une_piece_restitue_le_stock(): void
    {
        $this->actingUser();
        $intervention = $this->intervention();
        $piece = Piece::create([
            'reference' => 'PC-003',
            'designation' => 'Filtre',
            'stock_qte' => 10,
            'stock_min' => 2,
            'prix_unitaire_moyen' => 8,
            'module' => 'equipements_industriels',
        ]);

        $this->post("/interventions/{$intervention->id}/pieces", [
            'piece_id' => $piece->id,
            'quantite' => 4,
        ]);
        $this->assertSame(6, $piece->fresh()->stock_qte);

        $pivotId = $intervention->pieces()->first()->pivot->id;

        $this->delete("/interventions/{$intervention->id}/pieces/{$pivotId}")
            ->assertSessionHasNoErrors();

        $this->assertSame(10, $piece->fresh()->stock_qte);
        $this->assertDatabaseMissing('intervention_pieces', ['id' => $pivotId]);
    }
}
