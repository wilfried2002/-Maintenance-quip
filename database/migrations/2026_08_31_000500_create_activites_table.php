<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal d'activité : qui a créé/modifié/supprimé/restauré quoi, et quand.
     * Complète les soft deletes (suppressions réversibles mais invisibles) par
     * une trace exploitable côté UI.
     */
    public function up(): void
    {
        Schema::create('activites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sujet_type', 190); // morph class du modèle concerné
            $table->unsignedBigInteger('sujet_id')->nullable();
            $table->string('action', 50); // creation | modification | suppression | restauration
            $table->json('changements')->nullable(); // ['attribut' => ['avant' => …, 'apres' => …]]
            $table->timestamps();

            $table->index(['sujet_type', 'sujet_id']);
            $table->index(['organisation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activites');
    }
};
