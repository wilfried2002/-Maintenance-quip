<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique des relevés kilométriques : le compteur était saisi à la main
     * (dernière valeur écrasée, aucune trace) alors que les plans de maintenance
     * au kilomètre en dépendent — impossible de suivre la cadence réelle.
     */
    public function up(): void
    {
        Schema::create('releves_kilometriques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicule_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('kilometrage');
            $table->date('date_releve');
            $table->string('source', 30)->default('saisie'); // saisie | edition_vehicule
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['vehicule_id', 'date_releve']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releves_kilometriques');
    }
};
