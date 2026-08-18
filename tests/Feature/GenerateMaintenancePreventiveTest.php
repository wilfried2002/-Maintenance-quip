<?php

namespace Tests\Feature;

use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\PlanMaintenance;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GenerateMaintenancePreventiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_genere_une_intervention_pour_un_plan_jours_en_retard(): void
    {
        $equipement = EquipementIndustriel::create(['code' => 'IND-001', 'designation' => 'Compresseur']);

        $plan = PlanMaintenance::create([
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $equipement->id,
            'operation' => 'Contrôle vibratoire',
            'type_frequence' => 'jours',
            'frequence_valeur' => 7,
            'derniere_execution_date' => now()->subDays(10),
            'actif' => true,
        ]);

        Artisan::call('maintenance:generate');

        $this->assertDatabaseHas('interventions', [
            'plan_maintenance_id' => $plan->id,
            'type_intervention' => 'preventive',
            'statut' => 'planifiee',
        ]);
        $this->assertSame(1, Intervention::where('plan_maintenance_id', $plan->id)->count());
    }

    public function test_ne_genere_pas_de_doublon_tant_quune_intervention_est_ouverte(): void
    {
        $equipement = EquipementIndustriel::create(['code' => 'IND-002', 'designation' => 'Compresseur 2']);

        $plan = PlanMaintenance::create([
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $equipement->id,
            'operation' => 'Contrôle vibratoire',
            'type_frequence' => 'jours',
            'frequence_valeur' => 7,
            'derniere_execution_date' => now()->subDays(10),
            'actif' => true,
        ]);

        Artisan::call('maintenance:generate');
        Artisan::call('maintenance:generate');

        $this->assertSame(1, Intervention::where('plan_maintenance_id', $plan->id)->count());
    }

    public function test_genere_une_intervention_quand_le_seuil_kilometrique_est_atteint(): void
    {
        $vehicule = Vehicule::create([
            'code' => 'VEH-010',
            'immatriculation' => 'AA-010-BB',
            'kilometrage_actuel' => 15000,
        ]);

        $plan = PlanMaintenance::create([
            'equipementable_type' => Vehicule::class,
            'equipementable_id' => $vehicule->id,
            'operation' => 'Vidange moteur',
            'type_frequence' => 'kilometres',
            'frequence_valeur' => 5000,
            'derniere_execution_km' => 9000,
            'actif' => true,
        ]);

        Artisan::call('maintenance:generate');

        $this->assertDatabaseHas('interventions', [
            'plan_maintenance_id' => $plan->id,
            'type_intervention' => 'preventive',
        ]);
    }

    public function test_ne_genere_rien_sous_le_seuil_kilometrique(): void
    {
        $vehicule = Vehicule::create([
            'code' => 'VEH-011',
            'immatriculation' => 'AA-011-BB',
            'kilometrage_actuel' => 12000,
        ]);

        $plan = PlanMaintenance::create([
            'equipementable_type' => Vehicule::class,
            'equipementable_id' => $vehicule->id,
            'operation' => 'Vidange moteur',
            'type_frequence' => 'kilometres',
            'frequence_valeur' => 5000,
            'derniere_execution_km' => 9000,
            'actif' => true,
        ]);

        Artisan::call('maintenance:generate');

        $this->assertSame(0, Intervention::where('plan_maintenance_id', $plan->id)->count());
    }
}
