<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervention_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervention_id')->constrained()->cascadeOnDelete();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantite')->nullable()->default(1);
            $table->decimal('prix_unitaire', 10, 2)->nullable()->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_pieces');
    }
};
