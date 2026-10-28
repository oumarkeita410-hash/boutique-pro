<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            // Montant déjà réglé par le client (utile pour les ventes à crédit).
            $table->unsignedBigInteger('montant_paye')->default(0)->after('montant_total');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn('montant_paye');
        });
    }
};
