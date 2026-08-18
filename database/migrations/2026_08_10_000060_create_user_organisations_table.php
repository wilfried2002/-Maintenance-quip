<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_organisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'organisation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_organisations');
    }
};
