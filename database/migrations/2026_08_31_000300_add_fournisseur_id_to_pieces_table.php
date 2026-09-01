<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remplace progressivement le champ texte libre pieces.fournisseur (cassé au
     * premier renommage du fournisseur) par une vraie clé étrangère vers
     * fournisseurs. Le champ texte est conservé et resynchronisé (nom du
     * fournisseur choisi) pour ne pas casser les affichages existants.
     */
    public function up(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->foreignId('fournisseur_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fournisseur_id');
        });
    }
};
