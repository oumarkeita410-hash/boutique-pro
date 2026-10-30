<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantités sans limite pratique : on passe les colonnes « quantite » de
 * unsignedInteger (~4,29 milliards) à unsignedBigInteger (~9,2 × 10^18).
 * Ainsi un client peut commander n'importe quelle quantité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_vente', function (Blueprint $table) {
            $table->unsignedBigInteger('quantite')->change();
        });
        Schema::table('devis_lignes', function (Blueprint $table) {
            $table->unsignedBigInteger('quantite')->change();
        });
        Schema::table('prises_reparateur', function (Blueprint $table) {
            $table->unsignedBigInteger('quantite')->change();
        });
    }

    public function down(): void
    {
        Schema::table('lignes_vente', function (Blueprint $table) {
            $table->unsignedInteger('quantite')->change();
        });
        Schema::table('devis_lignes', function (Blueprint $table) {
            $table->unsignedInteger('quantite')->change();
        });
        Schema::table('prises_reparateur', function (Blueprint $table) {
            $table->unsignedInteger('quantite')->change();
        });
    }
};
