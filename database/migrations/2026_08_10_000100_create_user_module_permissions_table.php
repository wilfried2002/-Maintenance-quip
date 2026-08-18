<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('module');
            $table->boolean('granted')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'organisation_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_module_permissions');
    }
};
