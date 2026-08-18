<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipements_industriels', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('designation');
            $table->string('categorie')->nullable();
            $table->string('marque')->nullable();
            $table->string('modele')->nullable();
            $table->string('numero_serie')->nullable();
            $table->string('ligne_production')->nullable();
            $table->decimal('puissance_kw', 10, 2)->nullable();
            $table->date('date_mise_service')->nullable();
            $table->date('date_acquisition')->nullable();
            $table->decimal('valeur_acquisition', 12, 2)->nullable();
            $table->string('localisation')->nullable();
            $table->enum('statut', ['en_service', 'en_panne', 'en_maintenance', 'hors_service', 'reforme'])->default('en_service');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('photo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipements_industriels');
    }
};
