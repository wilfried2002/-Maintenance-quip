<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['equipements_industriels', 'vehicules', 'equipements_bureau'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->enum('criticite', ['basse', 'moyenne', 'haute', 'critique'])->default('moyenne')->after('statut');
                $table->date('date_fin_garantie')->nullable()->after('criticite');
                $table->foreignId('fournisseur_id')->nullable()->after('date_fin_garantie')
                    ->constrained('fournisseurs')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['equipements_industriels', 'vehicules', 'equipements_bureau'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('fournisseur_id');
                $table->dropColumn(['criticite', 'date_fin_garantie']);
            });
        }
    }
};
