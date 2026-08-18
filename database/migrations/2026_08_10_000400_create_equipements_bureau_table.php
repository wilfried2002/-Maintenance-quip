<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipements_bureau', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('designation');
            $table->enum('categorie', ['informatique', 'mobilier', 'climatisation', 'electromenager', 'autre'])->default('autre');
            $table->string('marque')->nullable();
            $table->string('modele')->nullable();
            $table->string('numero_serie')->nullable();
            $table->date('date_acquisition')->nullable();
            $table->decimal('valeur_acquisition', 12, 2)->nullable();
            $table->string('localisation')->nullable();
            $table->string('service_affecte')->nullable();
            $table->enum('statut', ['en_service', 'en_panne', 'en_maintenance', 'hors_service', 'reforme'])->default('en_service');
            $table->string('photo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipements_bureau');
    }
};
