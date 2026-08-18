<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_maintenance', function (Blueprint $table) {
            $table->id();
            // Relation polymorphe vers equipements_industriels, vehicules ou equipements_bureau.
            $table->morphs('equipementable');
            $table->string('operation');
            $table->enum('type_frequence', ['jours', 'kilometres'])->default('jours');
            $table->unsignedInteger('frequence_valeur');
            $table->date('derniere_execution_date')->nullable();
            $table->unsignedInteger('derniere_execution_km')->nullable();
            $table->boolean('actif')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans_maintenance');
    }
};
