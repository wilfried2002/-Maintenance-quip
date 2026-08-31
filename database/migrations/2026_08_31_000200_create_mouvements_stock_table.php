<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traçabilité des mouvements de stock : chaque entrée (réapprovisionnement),
     * sortie manuelle ou ajustement d'inventaire est journalisée, avec le stock
     * résultant — avant, le champ stock_qte était modifié à la main sans trace.
     * (Les consommations liées aux interventions restent dans intervention_pieces,
     * déjà horodatées et figées en prix.)
     */
    public function up(): void
    {
        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['entree', 'sortie', 'ajustement']);
            $table->unsignedInteger('quantite');
            // Stock de la pièce APRÈS application du mouvement (historique lisible).
            $table->unsignedInteger('stock_apres');
            $table->string('motif')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['piece_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
    }
};
