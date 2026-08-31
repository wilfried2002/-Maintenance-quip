<?php

use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Vehicule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rattache les documents à une organisation, comme toutes les autres tables métier
     * (voir 2026_08_13_000100_add_organisation_id_to_tenant_tables.php, dont ce fichier
     * est le complément : `documents` était restée hors cloisonnement). Sans cette
     * colonne + le scope global BelongsToOrganisation sur le modèle Document, un membre
     * d'une organisation pouvait supprimer par ID un document d'une autre organisation.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
        });

        // Backfill : chaque document existant hérite de l'organisation de l'équipement
        // auquel il est rattaché (relation polymorphe vers les 3 tables équipement).
        // Écrit en requêtes compatibles MySQL ET SQLite (pas d'UPDATE ... JOIN) pour
        // que les tests, qui migrent une base SQLite :memory:, passent par le même
        // chemin que la production.
        $classes = [
            EquipementIndustriel::class => 'equipements_industriels',
            Vehicule::class => 'vehicules',
            EquipementBureau::class => 'equipements_bureau',
        ];

        foreach ($classes as $classe => $tableEquipement) {
            DB::table($tableEquipement)
                ->whereNotNull('organisation_id')
                ->get(['id', 'organisation_id'])
                ->groupBy('organisation_id')
                ->each(function ($equipements, $organisationId) use ($classe) {
                    DB::table('documents')
                        ->whereNull('organisation_id')
                        ->where('equipementable_type', $classe)
                        ->whereIn('equipementable_id', $equipements->pluck('id'))
                        ->update(['organisation_id' => $organisationId]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_id');
        });
    }
};
