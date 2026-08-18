<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $blueprint) {
            $blueprint->string('devise', 3)->default('XOF')->after('code');
        });

        // Les organisations existantes (créées avant ce choix) restent en FCFA, déjà
        // affiché tel quel sur la fiche équipement avant l'ajout de ce réglage.
        DB::table('organisations')->update(['devise' => 'XOF']);
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $blueprint) {
            $blueprint->dropColumn('devise');
        });
    }
};
