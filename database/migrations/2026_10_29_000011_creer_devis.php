<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devis', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('client_nom')->nullable();   // si pas de client enregistré
            $table->foreignId('cree_par')->constrained('users')->restrictOnDelete();
            $table->string('statut', 20)->default('brouillon'); // brouillon, envoye, accepte, refuse, converti
            $table->unsignedBigInteger('montant_total')->default(0);
            $table->string('note')->nullable();
            $table->date('valide_jusqu')->nullable();
            $table->foreignId('vente_id')->nullable()->constrained('ventes')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('devis_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devis_id')->constrained('devis')->cascadeOnDelete();
            $table->foreignId('produit_id')->nullable()->constrained('produits')->nullOnDelete();
            $table->string('designation');
            $table->unsignedInteger('quantite');
            $table->unsignedBigInteger('prix_unitaire');
            $table->unsignedBigInteger('sous_total');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devis_lignes');
        Schema::dropIfExists('devis');
    }
};
