<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cloisonnement du stock par module (industriel/parc auto/bureau) : chaque module a
     * son propre catalogue de pièces désormais, comme les interventions et plans sont déjà
     * cloisonnés par équipement malgré une table partagée.
     */
    public function up(): void
    {
        Schema::table('pieces', function (Blueprint $blueprint) {
            $blueprint->string('module')->nullable()->after('organisation_id');
        });

        // Rattache chaque pièce existante au module de sa consommation la plus fréquente
        // (une pièce déjà consommée révèle son module réel) ; à défaut, au module industriel.
        $moduleParClasse = [
            'App\\Models\\EquipementIndustriel' => 'equipements_industriels',
            'App\\Models\\Vehicule' => 'parc_automobile',
            'App\\Models\\EquipementBureau' => 'equipement_bureau',
        ];

        $consommations = DB::table('intervention_pieces')
            ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
            ->select('intervention_pieces.piece_id', 'interventions.equipementable_type', DB::raw('count(*) as total'))
            ->groupBy('intervention_pieces.piece_id', 'interventions.equipementable_type')
            ->orderByDesc('total')
            ->get()
            ->unique('piece_id');

        foreach ($consommations as $ligne) {
            $module = $moduleParClasse[$ligne->equipementable_type] ?? null;
            if ($module) {
                DB::table('pieces')->where('id', $ligne->piece_id)->update(['module' => $module]);
            }
        }

        DB::table('pieces')->whereNull('module')->update(['module' => 'equipements_industriels']);
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $blueprint) {
            $blueprint->dropColumn('module');
        });
    }
};
