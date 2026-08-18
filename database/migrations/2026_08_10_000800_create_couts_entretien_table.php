<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couts_entretien', function (Blueprint $table) {
            $table->id();
            // Relation polymorphe vers equipements_industriels, vehicules ou equipements_bureau.
            $table->morphs('equipementable');
            $table->foreignId('intervention_id')->nullable()->constrained('interventions')->nullOnDelete();
            $table->enum('type_cout', ['main_oeuvre', 'pieces', 'prestation_externe', 'autre'])->default('autre');
            $table->decimal('montant', 12, 2);
            $table->date('date');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couts_entretien');
    }
};
