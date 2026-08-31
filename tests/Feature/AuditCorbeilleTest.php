<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Organisation;
use App\Models\Piece;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Journal d'activité (qui a créé/modifié/supprimé/restauré quoi) + corbeille :
 * restauration et suppression définitive des éléments soft-deleted, réservées
 * aux admins.
 */
class AuditCorbeilleTest extends TestCase
{
    use RefreshDatabase;

    private function sessionAvecRole(string $role): Organisation
    {
        $org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => $role, 'is_active' => true]);
        $this->actingAs($user)->withSession(['current_organisation_id' => $org->id]);

        return $org;
    }

    public function test_creation_et_modification_sont_journalisees_avec_diff(): void
    {
        $org = $this->sessionAvecRole('admin');

        $piece = Piece::create([
            'organisation_id' => $org->id,
            'reference' => 'P-AUDIT',
            'designation' => 'Filtre à air',
            'stock_qte' => 10,
            'module' => 'equipements_industriels',
        ]);

        $this->assertDatabaseHas('activites', [
            'action' => 'creation',
            'sujet_type' => Piece::class,
            'sujet_id' => $piece->id,
            'organisation_id' => $org->id,
        ]);

        $piece->update(['stock_qte' => 25]);

        $modification = Activite::query()
            ->where('action', 'modification')
            ->where('sujet_id', $piece->id)
            ->first();

        $this->assertNotNull($modification);
        $this->assertSame(10, (int) $modification->changements['stock_qte']['avant']);
        $this->assertSame(25, (int) $modification->changements['stock_qte']['apres']);
    }

    public function test_suppression_restauration_via_corbeille(): void
    {
        $org = $this->sessionAvecRole('admin');

        $vehicule = Vehicule::create([
            'organisation_id' => $org->id,
            'code' => 'C-1',
            'immatriculation' => 'AA-111-BB',
        ]);

        $vehicule->delete();

        $this->assertDatabaseHas('activites', ['action' => 'suppression', 'sujet_type' => Vehicule::class, 'sujet_id' => $vehicule->id]);

        // Le véhicule supprimé apparaît dans la corbeille de son organisation.
        $this->get('/corbeille')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('corbeilles'));

        $this->assertDatabaseHas('vehicules', ['id' => $vehicule->id, 'deleted_at' => $vehicule->fresh()->deleted_at]);

        $this->post("/corbeille/vehicules/{$vehicule->id}/restore")
            ->assertSessionHas('status');

        $this->assertNotNull($vehicule->fresh());
        $this->assertNull($vehicule->fresh()->deleted_at);
        $this->assertDatabaseHas('activites', ['action' => 'restauration', 'sujet_type' => Vehicule::class, 'sujet_id' => $vehicule->id]);
    }

    public function test_suppression_definitive(): void
    {
        $org = $this->sessionAvecRole('admin');

        $vehicule = Vehicule::create([
            'organisation_id' => $org->id,
            'code' => 'C-2',
            'immatriculation' => 'AA-222-BB',
        ]);

        $vehicule->delete();

        $this->delete("/corbeille/vehicules/{$vehicule->id}")
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('vehicules', ['id' => $vehicule->id]);
    }

    public function test_corbeille_et_journal_reserves_aux_admins(): void
    {
        $this->sessionAvecRole('user');

        $this->get('/corbeille')->assertForbidden();
        $this->get('/activites')->assertForbidden();
        $this->post('/corbeille/vehicules/1/restore')->assertForbidden();
    }

    public function test_journal_affiche_les_activites(): void
    {
        $org = $this->sessionAvecRole('admin');

        Piece::create([
            'organisation_id' => $org->id,
            'reference' => 'P-J',
            'designation' => 'Joint',
            'module' => 'equipements_industriels',
        ]);

        $this->get('/activites')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('activites.data'));
    }
}
