<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            // Relation polymorphe vers equipements_industriels, vehicules ou equipements_bureau.
            $table->morphs('equipementable');
            $table->enum('type_intervention', ['preventive', 'corrective', 'predictive'])->default('corrective');
            $table->enum('statut', ['planifiee', 'en_cours', 'terminee', 'annulee'])->default('planifiee');
            $table->enum('priorite', ['basse', 'normale', 'haute', 'critique'])->default('normale');
            $table->dateTime('date_planifiee')->nullable();
            $table->dateTime('date_debut')->nullable();
            $table->dateTime('date_fin')->nullable();
            $table->foreignId('technicien_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->decimal('cout_main_oeuvre', 10, 2)->nullable()->default(0);
            $table->decimal('duree_heures', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
