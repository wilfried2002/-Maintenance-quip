<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mtbf_heures existait en base mais n'était jamais alimentée ; on ajoute
     * mttr_heures pour l'accompagner — tous deux calculés par
     * IndicateurPerformanceCalculator à partir des interventions correctives.
     */
    public function up(): void
    {
        Schema::table('indicateurs_performance_pieces', function (Blueprint $table) {
            $table->decimal('mttr_heures', 8, 2)->nullable()->after('mtbf_heures');
        });
    }

    public function down(): void
    {
        Schema::table('indicateurs_performance_pieces', function (Blueprint $table) {
            $table->dropColumn('mttr_heures');
        });
    }
};
