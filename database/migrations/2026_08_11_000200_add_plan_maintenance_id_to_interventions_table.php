<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->foreignId('plan_maintenance_id')->nullable()->after('equipementable_id')
                ->constrained('plans_maintenance')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_maintenance_id');
        });
    }
};
