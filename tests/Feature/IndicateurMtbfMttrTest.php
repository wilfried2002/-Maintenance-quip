<?php

namespace Tests\Feature;

use App\Models\EquipementIndustriel;
use App\Models\IndicateurPerformancePiece;
use App\Models\Intervention;
use App\Models\Organisation;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * MTBF / MTTR des pièces (indicateurs:calculer) :
 * - MTBF = écart moyen en heures entre défaillances correctives consécutives de
 *   la même pièce sur le MÊME équipement ;
 * - MTTR = durée moyenne (date_fin - date_debut) des interventions correctives
 *   ayant consommé la pièce.
 */
class IndicateurMtbfMttrTest extends TestCase
{
    use RefreshDatabase;

    public function test_mtbf_et_mttr_sont_calcules_depuis_les_interventions_correctives(): void
    {
        $org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'admin', 'is_active' => true]);
        $this->actingAs($user)->withSession(['current_organisation_id' => $org->id]);

        $equipement = EquipementIndustriel::create([
            'organisation_id' => $org->id,
            'code' => 'IND-MTBF',
            'designation' => 'Presse',
        ]);

        $piece = Piece::create([
            'organisation_id' => $org->id,
            'reference' => 'P-MTBF',
            'designation' => 'Courroie',
            'module' => 'equipements_industriels',
        ]);

        // Trois défaillances correctives : J-10 (2h de réparation), J-5 (4h), J0 (1h).
        // MTBF = 5 jours entre événements = 120 h ; MTTR = (2+4+1)/3 = 2,33 h.
        foreach ([
            [now()->subDays(10)->setTime(8, 0), now()->subDays(10)->setTime(10, 0)],
            [now()->subDays(5)->setTime(8, 0), now()->subDays(5)->setTime(12, 0)],
            [now()->setTime(8, 0), now()->setTime(9, 0)],
        ] as [$debut, $fin]) {
            $intervention = Intervention::create([
                'equipementable_type' => EquipementIndustriel::class,
                'equipementable_id' => $equipement->id,
                'type_intervention' => 'corrective',
                'statut' => 'terminee',
                'priorite' => 'normale',
                'titre' => 'Remplacement courroie',
                'date_debut' => $debut,
                'date_fin' => $fin,
            ]);
            $intervention->pieces()->attach($piece->id, ['quantite' => 1, 'prix_unitaire' => 10]);
        }

        Artisan::call('indicateurs:calculer');

        $indicateur = IndicateurPerformancePiece::where('piece_id', $piece->id)
            ->whereNull('equipementable_type')
            ->first();

        $this->assertNotNull($indicateur);
        $this->assertEqualsWithDelta(120.0, (float) $indicateur->mtbf_heures, 1.0);
        $this->assertEqualsWithDelta(2.33, (float) $indicateur->mttr_heures, 0.05);
    }

    public function test_sans_panne_consecutive_mtbf_est_null(): void
    {
        $org = Organisation::create(['name' => 'Démo', 'code' => 'DEMO-'.uniqid(), 'is_active' => true]);
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'admin', 'is_active' => true]);
        $this->actingAs($user)->withSession(['current_organisation_id' => $org->id]);

        $equipement = EquipementIndustriel::create([
            'organisation_id' => $org->id,
            'code' => 'IND-1',
            'designation' => 'Compresseur',
        ]);
        $piece = Piece::create([
            'organisation_id' => $org->id,
            'reference' => 'P-SEULE',
            'designation' => 'Joint',
            'module' => 'equipements_industriels',
        ]);

        // Une seule consommation préventive : ni MTBF ni MTTR calculables.
        $intervention = Intervention::create([
            'equipementable_type' => EquipementIndustriel::class,
            'equipementable_id' => $equipement->id,
            'type_intervention' => 'preventive',
            'statut' => 'terminee',
            'priorite' => 'normale',
            'titre' => 'Vidange',
            'date_debut' => now()->subHours(3),
            'date_fin' => now()->subHours(1),
        ]);
        $intervention->pieces()->attach($piece->id, ['quantite' => 1, 'prix_unitaire' => 5]);

        Artisan::call('indicateurs:calculer');

        $indicateur = IndicateurPerformancePiece::where('piece_id', $piece->id)->first();

        $this->assertNotNull($indicateur);
        $this->assertNull($indicateur->mtbf_heures);
        $this->assertNull($indicateur->mttr_heures);
    }
}
