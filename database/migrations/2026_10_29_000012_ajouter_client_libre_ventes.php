<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            // Nom et téléphone du client saisis à la main (vente rapide, sans client enregistré).
            $table->string('client_nom')->nullable()->after('client_id');
            $table->string('client_tel', 30)->nullable()->after('client_nom');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['client_nom', 'client_tel']);
        });
    }
};
