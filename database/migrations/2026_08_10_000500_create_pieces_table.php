<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pieces', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('designation');
            $table->string('categorie')->nullable();
            $table->string('unite')->default('unite');
            $table->unsignedInteger('stock_qte')->nullable()->default(0);
            $table->unsignedInteger('stock_min')->nullable()->default(0);
            $table->decimal('prix_unitaire_moyen', 10, 2)->nullable()->default(0);
            $table->string('fournisseur')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces');
    }
};
