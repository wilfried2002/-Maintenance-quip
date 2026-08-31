<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pagination/recherche/tri côté serveur : les listes ne chargent plus que la
 * page courante (paginator Laravel exposé à Inertia) et les paramètres q/sort/
 * dir/page/per_page sont appliqués en base.
 */
class PaginationServeurTest extends TestCase
{
    use RefreshDatabase;

    private function sessionAdmin(): Organisation
    {
        $org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'admin', 'is_active' => true]);
        $this->actingAs($user)->withSession(['current_organisation_id' => $org->id]);

        return $org;
    }

    private function vehicule(Organisation $org, string $code, string $immatriculation): Vehicule
    {
        return Vehicule::create([
            'organisation_id' => $org->id,
            'code' => $code,
            'immatriculation' => $immatriculation,
            'marque' => 'Toyota',
        ]);
    }

    public function test_liste_vehicules_paginee(): void
    {
        $org = $this->sessionAdmin();
        foreach (range(1, 25) as $i) {
            $this->vehicule($org, sprintf('C-%02d', $i), sprintf('AA-%03d-ZZ', $i));
        }

        $this->get('/vehicules?page=2&per_page=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehicules.current_page', 2)
                ->where('vehicules.last_page', 3)
                ->where('vehicules.total', 25)
                ->has('vehicules.data', 10));
    }

    public function test_recherche_vehicules_cote_serveur(): void
    {
        $org = $this->sessionAdmin();
        foreach (range(1, 5) as $i) {
            $this->vehicule($org, "C-{$i}", "BB-{$i}-YY");
        }
        $this->vehicule($org, 'C-99', 'AA-999-ZZ');

        $this->get('/vehicules?q=AA-999')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehicules.total', 1)
                ->where('vehicules.data.0.immatriculation', 'AA-999-ZZ'));
    }

    public function test_tri_reserve_aux_colonnes_autorisees(): void
    {
        $org = $this->sessionAdmin();
        $this->vehicule($org, 'C-3', 'AA-3-ZZ');
        $this->vehicule($org, 'C-1', 'AA-1-ZZ');
        $this->vehicule($org, 'C-2', 'AA-2-ZZ');

        // Tri ascendant valide sur code.
        $this->get('/vehicules?sort=code&dir=asc&per_page=5')
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehicules.data.0.code', 'C-1'));

        // Colonne inconnue → retour au tri par défaut, sans erreur SQL.
        $this->get('/vehicules?sort=password&dir=asc')
            ->assertOk();
    }

    public function test_interventions_recherche_et_pagination(): void
    {
        $org = $this->sessionAdmin();
        $vehicule = $this->vehicule($org, 'C-1', 'AA-1-ZZ');

        foreach (range(1, 12) as $i) {
            Intervention::create([
                'equipementable_type' => Vehicule::class,
                'equipementable_id' => $vehicule->id,
                'type_intervention' => 'corrective',
                'statut' => 'planifiee',
                'priorite' => 'normale',
                'titre' => $i >= 10 ? "Panne moteur {$i}" : "Vidange {$i}",
                'date_planifiee' => now()->addDays($i),
            ]);
        }

        $this->get('/vehicules/interventions?per_page=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('interventions.total', 12)
                ->has('interventions.data', 10));

        // « Vidange » ne matche que les titres 1..9 (10/11/12 sont « Panne »).
        $this->get('/vehicules/interventions?q=Vidange')
            ->assertInertia(fn (Assert $page) => $page
                ->where('interventions.total', 9));
    }
}
