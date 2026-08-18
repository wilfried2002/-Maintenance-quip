<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            // Relation polymorphe vers equipements_industriels, vehicules ou equipements_bureau.
            $table->morphs('equipementable');
            $table->string('nom_original');
            $table->string('chemin');
            $table->string('type_mime')->nullable();
            $table->unsignedBigInteger('taille')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
