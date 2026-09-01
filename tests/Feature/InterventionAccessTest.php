<?php

namespace Tests\Feature;

use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\Organisation;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les routes /interventions/* (consommation de pièces, rapport PDF, notes) ne
 * portent pas de préfixe de module dans l'URL : le module concerné dépend de
 * L'ÉQUIPEMENT de l'intervention. Ces tests vérifient qu'un rôle qui n'a pas
 * accès au module (ou à son stock) est rejeté, même s'il a accès ailleurs.
 */
class InterventionAccessTest extends TestCase
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

    private function interventionIndustrielle(Organisation $org): Intervention
    {
        $equipement = EquipementIndustriel::create([
            'organisation_id' => $org->id,
            'code' => 'IND-100',
            'designation' => 'Compresseur',
        ]);

        return Intervention::create([
            'organisation_id' => $org->id,
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $equipement->id,
            'titre' => 'Révision',
        ]);
    }

    public function test_magasinier_na_pas_acces_au_rapport_dune_intervention(): void
    {
        $org = $this->organisation();
        $intervention = $this->interventionIndustrielle($org);

        // Magasinier : aucun module équipement dans ses défauts (config/modules.php).
        $this->userAvecRole($org, 'magasinier')
            ->get("/interventions/{$intervention->id}/rapport")
            ->assertForbidden();
    }

    public function test_technicien_obtient_le_rapport(): void
    {
        $org = $this->organisation();
        $intervention = $this->interventionIndustrielle($org);

        $this->userAvecRole($org, 'technicien')
            ->get("/interventions/{$intervention->id}/rapport")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_magasinier_ne_peut_pas_modifier_les_notes_dune_intervention(): void
    {
        $org = $this->organisation();
        $intervention = $this->interventionIndustrielle($org);

        $this->userAvecRole($org, 'magasinier')
            ->put("/interventions/{$intervention->id}/notes", ['notes' => 'modification non autorisée'])
            ->assertForbidden();

        $this->assertDatabaseMissing('interventions', ['id' => $intervention->id, 'notes' => 'modification non autorisée']);
    }

    public function test_technicien_modifie_les_notes_dune_intervention_de_son_module(): void
    {
        $org = $this->organisation();
        $intervention = $this->interventionIndustrielle($org);

        $this->userAvecRole($org, 'technicien')
            ->put("/interventions/{$intervention->id}/notes", ['notes' => 'remplacé le joint'])
            ->assertRedirect();

        $this->assertDatabaseHas('interventions', ['id' => $intervention->id, 'notes' => 'remplacé le joint']);
    }

    public function test_responsable_parc_ne_consomme_pas_le_stock_dun_module_industriel(): void
    {
        $org = $this->organisation();
        $intervention = $this->interventionIndustrielle($org);

        $piece = Piece::create([
            'organisation_id' => $org->id,
            'reference' => 'IND-P-1',
            'designation' => 'Filtre industriel',
            'stock_qte' => 10,
            'stock_min' => 2,
            'module' => 'equipements_industriels',
        ]);

        // Responsable parc : gère le stock du parc automobile (et le hub /pieces),
        // PAS le stock industriel — l'intervention porte sur un équipement industriel.
        $this->userAvecRole($org, 'responsable_parc')
            ->post("/interventions/{$intervention->id}/pieces", [
                'piece_id' => $piece->id,
                'quantite' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(10, $piece->fresh()->stock_qte);
        $this->assertDatabaseMissing('intervention_pieces', ['piece_id' => $piece->id]);
    }

    public function test_magasinier_ne_consomme_pas_le_stock_dun_module_sans_y_avoir_droit(): void
    {
        $org = $this->organisation();
        $intervention = $this->interventionIndustrielle($org);

        $piece = Piece::create([
            'organisation_id' => $org->id,
            'reference' => 'IND-P-2',
            'designation' => 'Courroie',
            'stock_qte' => 5,
            'stock_min' => 1,
            'module' => 'equipements_industriels',
        ]);

        // Contrôle de la barrière la plus externe : le rôle 'user' (consultation)
        // n'est même pas dans l'union des rôles des stocks → rejet par middleware.
        $this->userAvecRole($org, 'user')
            ->post("/interventions/{$intervention->id}/pieces", [
                'piece_id' => $piece->id,
                'quantite' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(5, $piece->fresh()->stock_qte);
    }
}
