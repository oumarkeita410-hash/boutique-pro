<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('prenom', 100);
            $table->string('nom', 100);
            $table->string('telephone', 30)->unique();
            $table->string('email', 150)->nullable();
            $table->string('pays', 100);
            $table->string('ville', 100);
            $table->foreignId('enregistre_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['nom', 'prenom']);
        });

        Schema::create('reparateurs', function (Blueprint $table) {
            $table->id();
            $table->string('prenom', 100);
            $table->string('nom', 100)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Écran (ou autre pièce) pris par un réparateur, payé en partie ou à crédit.
        Schema::create('prises_reparateur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reparateur_id')->constrained('reparateurs')->restrictOnDelete();
            $table->foreignId('produit_id')->constrained('produits')->restrictOnDelete();
            $table->foreignId('enregistre_par')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('quantite');
            $table->unsignedBigInteger('prix_unitaire');
            $table->unsignedBigInteger('montant_total');
            $table->unsignedBigInteger('montant_paye')->default(0);
            $table->timestamps();

            $table->index(['reparateur_id', 'created_at']);
        });

        Schema::create('paiements_reparateur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reparateur_id')->constrained('reparateurs')->restrictOnDelete();
            $table->foreignId('recu_par')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->string('mode_paiement', 20);
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('paiements_reparateur');
        Schema::dropIfExists('prises_reparateur');
        Schema::dropIfExists('reparateurs');
        Schema::dropIfExists('clients');
    }
};
