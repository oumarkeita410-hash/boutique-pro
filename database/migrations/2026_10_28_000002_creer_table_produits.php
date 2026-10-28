<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('categorie')->nullable()->index(); // écran, plaquette, solaire, réseau...
            $table->string('modele')->nullable()->index();    // A14 4G, iphone 13 JH, KF8...
            $table->string('reference')->nullable()->unique();
            // Montants en GNF (pas de centimes).
            $table->unsignedBigInteger('prix_achat')->default(0);
            $table->unsignedBigInteger('prix_unitaire');
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('seuil_alerte')->default(5);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
