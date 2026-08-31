<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demandes d'intervention émises par les utilisateurs finaux (sans accès aux
     * modules de maintenance) puis validées/refusées par les responsables du
     * module concerné — workflow : soumise → approuvee/refusee → convertie
     * (intervention planifiée créée).
     */
    public function up(): void
    {
        Schema::create('demandes_intervention', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('module', 50); // parc_automobile / equipements_industriels / equipement_bureau
            $table->string('equipementable_type', 190)->nullable();
            $table->unsignedBigInteger('equipementable_id')->nullable();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->enum('priorite', ['basse', 'normale', 'haute', 'critique'])->default('normale');
            $table->enum('statut', ['soumise', 'approuvee', 'refusee', 'convertie'])->default('soumise');
            $table->foreignId('demandeur_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('decideur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_decision')->nullable();
            $table->timestamp('decide_le')->nullable();
            $table->foreignId('intervention_id')->nullable()->constrained('interventions')->nullOnDelete();
            $table->timestamps();

            $table->index(['organisation_id', 'statut', 'created_at']);
            $table->index(['equipementable_type', 'equipementable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_intervention');
    }
};
