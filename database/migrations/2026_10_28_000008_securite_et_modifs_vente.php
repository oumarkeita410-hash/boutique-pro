<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Compteur de tentatives d'accès à une zone interdite (ex. vendeur → admin).
            $table->unsignedTinyInteger('tentatives_intrusion')->default(0);
        });

        Schema::table('produits', function (Blueprint $table) {
            // Deux produits ne peuvent pas avoir le même nom + modèle (anti-doublon).
            $table->unique(['nom', 'modele'], 'produits_nom_modele_unique');
        });

        // Demande de modification d'une vente : doit être approuvée par l'administrateur.
        Schema::create('demandes_modification_vente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->foreignId('demande_par')->constrained('users')->restrictOnDelete();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changements');            // champs demandés : {mode_paiement, client_id}
            $table->json('avant')->nullable();       // valeurs avant (rempli à l'approbation)
            $table->json('apres')->nullable();       // valeurs après (rempli à l'approbation)
            $table->string('motif_demande')->nullable();
            $table->string('motif_refus')->nullable();
            $table->string('statut', 20)->default('en_attente')->index(); // en_attente, approuve, refuse
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_modification_vente');
        Schema::table('produits', function (Blueprint $table) {
            $table->dropUnique('produits_nom_modele_unique');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tentatives_intrusion');
        });
    }
};
