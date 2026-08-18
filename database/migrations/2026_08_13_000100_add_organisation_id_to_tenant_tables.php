<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables métier à cloisonner par organisation (cloisonnement SaaS strict — chaque
     * entreprise ne doit voir que ses propres données). Volontairement dénormalisé sur
     * interventions/plans_maintenance/couts_entretien/indicateurs (au lieu de ne le mettre
     * que sur les équipements et joindre via equipementable) pour que le filtrage reste
     * simple et rapide malgré la relation polymorphe vers 3 types d'équipements différents.
     *
     * Colonne laissée nullable au niveau SQL (pas de doctrine/dbal installé pour un ->change()
     * ultérieur) : la contrainte "toujours renseignée" est appliquée par
     * App\Models\Concerns\BelongsToOrganisation (auto-assignation à la création + scope
     * global en lecture), pas par le schéma.
     */
    private const TABLES = [
        'equipements_industriels',
        'vehicules',
        'equipements_bureau',
        'pieces',
        'fournisseurs',
        'interventions',
        'plans_maintenance',
        'couts_entretien',
        'indicateurs_performance_pieces',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        // Rattache les données existantes (créées avant le cloisonnement) à la première
        // organisation du système, pour ne rien perdre.
        $defaultOrganisationId = DB::table('organisations')->orderBy('id')->value('id');

        if ($defaultOrganisationId) {
            foreach (self::TABLES as $table) {
                DB::table($table)->whereNull('organisation_id')->update(['organisation_id' => $defaultOrganisationId]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('organisation_id');
            });
        }
    }
};
