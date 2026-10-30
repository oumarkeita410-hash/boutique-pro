<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des paiements du reste d'une vente : chaque fois qu'un client
 * revient payer une partie de ce qu'il doit, un versement est enregistré ici.
 * On garde la trace (qui, combien, quand) sans jamais modifier les versements
 * précédents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements_vente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users'); // qui a encaissé
            $table->unsignedBigInteger('montant');
            $table->string('mode_paiement')->default('especes');
            $table->timestamps();

            $table->index(['vente_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_vente');
    }
};
