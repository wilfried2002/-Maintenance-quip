<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicateurs_performance_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            // Equipement concerné (optionnel : un indicateur peut être global à la pièce
            // ou spécifique à un équipement précis).
            $table->nullableMorphs('equipementable', 'idx_perf_pieces_equipementable');
            $table->unsignedInteger('nombre_remplacements')->default(0);
            $table->decimal('duree_vie_moyenne_jours', 10, 2)->nullable();
            $table->decimal('mtbf_heures', 10, 2)->nullable();
            $table->decimal('taux_defaillance', 6, 4)->nullable();
            $table->decimal('cout_total_remplacement', 12, 2)->default(0);
            $table->date('derniere_maj')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicateurs_performance_pieces');
    }
};
