<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('immatriculation')->unique();
            $table->string('marque')->nullable();
            $table->string('modele')->nullable();
            $table->enum('type_vehicule', ['vl', 'pl', 'utilitaire', 'engin', 'moto'])->default('vl');
            $table->string('type_carburant')->nullable();
            $table->date('date_mise_circulation')->nullable();
            $table->date('date_acquisition')->nullable();
            $table->decimal('valeur_acquisition', 12, 2)->nullable();
            $table->unsignedInteger('kilometrage_actuel')->nullable()->default(0);
            $table->foreignId('chauffeur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('statut', ['en_service', 'en_panne', 'en_maintenance', 'hors_service', 'reforme'])->default('en_service');
            $table->string('photo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicules');
    }
};
