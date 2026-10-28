<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->uuid('uuid_client')->nullable()->unique(); // anti-doublon pour la synchro hors ligne
            $table->foreignId('vendeur_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('mode_paiement', 20);
            $table->string('reference_paiement')->nullable();
            $table->string('statut', 30)->index();
            $table->unsignedBigInteger('montant_total')->default(0);
            $table->timestamps();

            $table->index(['vendeur_id', 'created_at']);
        });

        Schema::create('lignes_vente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits')->restrictOnDelete();
            $table->unsignedInteger('quantite');
            $table->unsignedBigInteger('prix_unitaire');
            $table->unsignedBigInteger('prix_achat')->default(0); // figé au moment de la vente, pour la marge
            $table->unsignedBigInteger('sous_total');
            $table->timestamps();
        });

        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vente_id')->nullable()->constrained('ventes')->nullOnDelete();
            $table->string('type', 20); // vente, approvisionnement, ajustement
            $table->integer('quantite'); // négatif = sortie
            $table->unsignedInteger('stock_avant');
            $table->unsignedInteger('stock_apres');
            $table->timestamps();

            $table->index(['produit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
        Schema::dropIfExists('lignes_vente');
        Schema::dropIfExists('ventes');
    }
};
