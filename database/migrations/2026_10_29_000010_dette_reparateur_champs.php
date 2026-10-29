<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prises_reparateur', function (Blueprint $table) {
            // Détails saisis à la main (quand l'article n'est pas pris dans le catalogue).
            $table->string('modele')->nullable()->after('produit_id');
            $table->string('marque')->nullable()->after('modele');
            $table->string('categorie')->nullable()->after('marque');
            // Une dette peut être saisie manuellement, sans produit du catalogue.
            $table->unsignedBigInteger('produit_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('prises_reparateur', function (Blueprint $table) {
            $table->dropColumn(['modele', 'marque', 'categorie']);
        });
    }
};
