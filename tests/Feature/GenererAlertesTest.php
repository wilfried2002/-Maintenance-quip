<?php

namespace Tests\Feature;

use App\Models\EquipementIndustriel;
use App\Models\Organisation;
use App\Models\Piece;
use App\Models\PlanMaintenance;
use App\Models\User;
use App\Notifications\PlanMaintenanceEnRetard;
use App\Notifications\StockBas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GenererAlertesTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(): Organisation
    {
        return Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
    }

    public function test_notifie_les_utilisateurs_ayant_acces_au_module_du_plan_en_retard(): void
    {
        $org = $this->organisation();

        $responsable = User::factory()->create();
        $org->users()->attach($responsable->id, ['role' => 'responsable_maintenance', 'is_active' => true]);

        // 'magasinier' n'a pas accès à equipements_industriels : ne doit rien recevoir.
        $magasinier = User::factory()->create();
        $org->users()->attach($magasinier->id, ['role' => 'magasinier', 'is_active' => true]);

        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $equipement = EquipementIndustriel::create(['organisation_id' => $org->id, 'code' => 'IND-100', 'designation' => 'Compresseur']);
        PlanMaintenance::create([
            'organisation_id' => $org->id,
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $equipement->id,
            'operation' => 'Contrôle vibratoire',
            'type_frequence' => 'jours',
            'frequence_valeur' => 7,
            'derniere_execution_date' => now()->subDays(30),
            'actif' => true,
        ]);

        Artisan::call('alertes:generer');

        $this->assertSame(1, $responsable->fresh()->unreadNotifications()->count());
        $this->assertSame(PlanMaintenanceEnRetard::class, $responsable->notifications->first()->type);
        $this->assertSame(0, $magasinier->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $superAdmin->fresh()->unreadNotifications()->count());
    }

    public function test_ne_notifie_pas_les_utilisateurs_dune_autre_organisation(): void
    {
        $orgA = $this->organisation();
        $orgB = $this->organisation();

        // Même rôle, même module, mais rattaché à une AUTRE organisation que celle du plan.
        $responsableOrgB = User::factory()->create();
        $orgB->users()->attach($responsableOrgB->id, ['role' => 'responsable_maintenance', 'is_active' => true]);

        $equipement = EquipementIndustriel::create(['organisation_id' => $orgA->id, 'code' => 'IND-999', 'designation' => 'Compresseur A']);
        PlanMaintenance::create([
            'organisation_id' => $orgA->id,
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $equipement->id,
            'operation' => 'Contrôle',
            'type_frequence' => 'jours',
            'frequence_valeur' => 7,
            'derniere_execution_date' => now()->subDays(30),
            'actif' => true,
        ]);

        Artisan::call('alertes:generer');

        $this->assertSame(0, $responsableOrgB->fresh()->unreadNotifications()->count());
    }

    public function test_ne_double_pas_lalerte_tant_quelle_nest_pas_lue(): void
    {
        $org = $this->organisation();
        $responsable = User::factory()->create();
        $org->users()->attach($responsable->id, ['role' => 'responsable_maintenance', 'is_active' => true]);

        $equipement = EquipementIndustriel::create(['organisation_id' => $org->id, 'code' => 'IND-101', 'designation' => 'Compresseur 2']);
        PlanMaintenance::create([
            'organisation_id' => $org->id,
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $equipement->id,
            'operation' => 'Contrôle',
            'type_frequence' => 'jours',
            'frequence_valeur' => 7,
            'derniere_execution_date' => now()->subDays(30),
            'actif' => true,
        ]);

        Artisan::call('alertes:generer');
        Artisan::call('alertes:generer');
        Artisan::call('alertes:generer');

        $this->assertSame(1, $responsable->fresh()->notifications()->count());
    }

    public function test_resout_lalerte_quand_le_plan_nest_plus_en_retard(): void
    {
        $org = $this->organisation();
        $responsable = User::factory()->create();
        $org->users()->attach($responsable->id, ['role' => 'responsable_maintenance', 'is_active' => true]);

        $equipement = EquipementIndustriel::create(['organisation_id' => $org->id, 'code' => 'IND-102', 'designation' => 'Compresseur 3']);
        $plan = PlanMaintenance::create([
            'organisation_id' => $org->id,
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $equipement->id,
            'operation' => 'Contrôle',
            'type_frequence' => 'jours',
            'frequence_valeur' => 7,
            'derniere_execution_date' => now()->subDays(30),
            'actif' => true,
        ]);

        Artisan::call('alertes:generer');
        $this->assertSame(1, $responsable->fresh()->unreadNotifications()->count());

        // Le technicien exécute le plan : il n'est plus en retard.
        $plan->update(['derniere_execution_date' => now()]);

        Artisan::call('alertes:generer');

        $this->assertSame(0, $responsable->fresh()->unreadNotifications()->count());
    }

    public function test_stock_bas_notifie_uniquement_les_utilisateurs_du_module_pieces(): void
    {
        $org = $this->organisation();

        $magasinier = User::factory()->create();
        $org->users()->attach($magasinier->id, ['role' => 'magasinier', 'is_active' => true]);

        // 'user' n'a pas accès à pieces_stock.
        $simpleUser = User::factory()->create();
        $org->users()->attach($simpleUser->id, ['role' => 'user', 'is_active' => true]);

        Piece::create([
            'organisation_id' => $org->id,
            'reference' => 'PC-100',
            'designation' => 'Filtre',
            'stock_qte' => 1,
            'stock_min' => 5,
            'prix_unitaire_moyen' => 10,
        ]);

        Artisan::call('alertes:generer');

        $this->assertSame(1, $magasinier->fresh()->unreadNotifications()->count());
        $this->assertSame(StockBas::class, $magasinier->notifications->first()->type);
        $this->assertSame(0, $simpleUser->fresh()->unreadNotifications()->count());
    }
}
