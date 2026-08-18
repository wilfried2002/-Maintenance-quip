<?php

namespace Tests\Unit;

use App\Models\EquipementIndustriel;
use App\Models\PlanMaintenance;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function equipement(): EquipementIndustriel
    {
        return EquipementIndustriel::create([
            'code' => 'IND-'.uniqid(),
            'designation' => 'Compresseur test',
        ]);
    }

    public function test_plan_jours_non_encore_execute_est_en_retard_si_delai_depasse_depuis_creation(): void
    {
        $plan = PlanMaintenance::create([
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $this->equipement()->id,
            'operation' => 'Contrôle',
            'type_frequence' => 'jours',
            'frequence_valeur' => 7,
            'actif' => true,
        ]);
        // Le plan vient d'être créé : l'échéance calendaire (created_at + 7j) est dans le futur.
        $this->assertFalse($plan->en_retard);

        $plan->created_at = now()->subDays(10);
        $plan->save();
        $plan->refresh();

        $this->assertTrue($plan->en_retard);
    }

    public function test_plan_jours_derniere_execution_recente_nest_pas_en_retard(): void
    {
        $plan = PlanMaintenance::create([
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $this->equipement()->id,
            'operation' => 'Vidange',
            'type_frequence' => 'jours',
            'frequence_valeur' => 30,
            'derniere_execution_date' => now()->subDays(5),
            'actif' => true,
        ]);

        $this->assertFalse($plan->en_retard);
        // derniere_execution_date est castée en 'date' (minuit) : comparer contre la même
        // base plutôt que now(), qui inclut l'heure courante.
        $expected = now()->startOfDay()->subDays(5)->addDays(30);
        $this->assertEqualsWithDelta($expected->timestamp, $plan->prochaine_echeance->timestamp, 5);
    }

    public function test_plan_inactif_nest_jamais_en_retard(): void
    {
        $plan = PlanMaintenance::create([
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $this->equipement()->id,
            'operation' => 'Contrôle',
            'type_frequence' => 'jours',
            'frequence_valeur' => 7,
            'derniere_execution_date' => now()->subDays(30),
            'actif' => false,
        ]);

        $this->assertFalse($plan->en_retard);
    }

    public function test_plan_kilometres_en_retard_quand_seuil_depasse(): void
    {
        $vehicule = Vehicule::create([
            'code' => 'VEH-'.uniqid(),
            'immatriculation' => 'AA-'.rand(100, 999).'-BB',
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

        // 15000 - 9000 = 6000 >= 5000 : en retard.
        $this->assertTrue($plan->en_retard);
    }

    public function test_plan_kilometres_pas_en_retard_sous_le_seuil(): void
    {
        $vehicule = Vehicule::create([
            'code' => 'VEH-'.uniqid(),
            'immatriculation' => 'AA-'.rand(100, 999).'-CC',
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

        // 12000 - 9000 = 3000 < 5000 : pas encore en retard.
        $this->assertFalse($plan->en_retard);
    }

    public function test_plan_kilometres_sur_equipement_non_vehicule_nest_jamais_en_retard(): void
    {
        $plan = PlanMaintenance::create([
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $this->equipement()->id,
            'operation' => 'Opération incohérente',
            'type_frequence' => 'kilometres',
            'frequence_valeur' => 5000,
            'actif' => true,
        ]);

        $this->assertFalse($plan->en_retard);
    }
}
