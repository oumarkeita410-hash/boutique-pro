<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pays', 100)->nullable();
            $table->string('region', 100)->nullable()->index(); // préfecture ou région
            $table->string('fuseau_horaire', 64)->default('Africa/Conakry');
            $table->string('frequence_paie', 10)->nullable(); // jour, semaine, mois
            $table->unsignedBigInteger('montant_paie')->default(0); // GNF par période
        });

        // Un jour de travail par ligne : 1 = lundi … 7 = dimanche.
        Schema::create('horaires_travail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('jour_semaine');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->timestamps();

            $table->unique(['user_id', 'jour_semaine']);
        });

        Schema::create('paiements_vendeur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('paye_par')->constrained('users')->restrictOnDelete();
            $table->string('frequence', 10);
            $table->date('periode_debut');
            $table->date('periode_fin');
            $table->unsignedBigInteger('montant');
            $table->string('mode_paiement', 20);
            $table->string('reference_paiement')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            // Un vendeur ne peut pas être payé deux fois pour la même période.
            $table->unique(['user_id', 'frequence', 'periode_debut']);
        });

        // Un fil de discussion par compte : user_id = le vendeur/conseiller concerné,
        // auteur_id = qui a écrit (lui-même ou l'administrateur).
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->cascadeOnDelete();
            $table->text('contenu');
            $table->timestamp('lu_le')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('paiements_vendeur');
        Schema::dropIfExists('horaires_travail');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pays', 'region', 'fuseau_horaire', 'frequence_paie', 'montant_paie']);
        });
    }
};
