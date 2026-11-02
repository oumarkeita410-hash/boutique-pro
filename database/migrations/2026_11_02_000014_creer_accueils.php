<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // File d'attente d'accueil : quand la boutique est pleine, le conseiller
        // enregistre chaque client qui arrive ; vendeurs et admin voient la file.
        Schema::create('accueils', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ticket');            // numéro du jour (ordre d'arrivée)
            $table->string('client_nom', 150)->nullable();
            $table->string('client_tel', 30)->nullable();
            $table->string('motif', 20);                  // achat, reparation, renseignement, autre
            $table->string('note', 255)->nullable();
            $table->string('statut', 15)->default('en_attente'); // en_attente, en_cours, termine, annule
            $table->foreignId('conseiller_id')->nullable()->constrained('users')->nullOnDelete(); // qui a enregistré
            $table->foreignId('vendeur_id')->nullable()->constrained('users')->nullOnDelete();     // envoyé vers ce vendeur
            $table->foreignId('pris_par')->nullable()->constrained('users')->nullOnDelete();        // qui a pris en charge
            $table->timestamp('pris_le')->nullable();
            $table->timestamp('termine_le')->nullable();
            $table->timestamps();

            $table->index(['statut', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accueils');
    }
};
