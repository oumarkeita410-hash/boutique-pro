<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service après-vente (SAV) : un client a un problème avec un produit acheté.
 * Une réclamation est confiée à un conseiller ; chaque appel / message est tracé.
 * L'administrateur voit tout et est le seul à pouvoir modifier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reclamations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero');                 // numéro visible (REC n°…)
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('client_nom', 150)->nullable();     // copie du nom (reste même si le client est supprimé)
            $table->string('client_tel', 30)->nullable();      // numéro à appeler
            $table->string('vente_ref', 60)->nullable();       // référence de la vente concernée (facultatif)
            $table->string('produit', 180);                    // produit concerné
            $table->text('probleme');                          // description du problème
            $table->string('statut', 20)->default('ouverte');  // ouverte / en_cours / resolue / fermee
            $table->foreignId('conseiller_id')->nullable()->constrained('users')->nullOnDelete(); // qui traite
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolue_le')->nullable();
            $table->timestamps();
            $table->index(['statut', 'created_at']);
        });

        Schema::create('reclamation_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reclamation_id')->constrained('reclamations')->cascadeOnDelete();
            $table->string('type', 20);                        // appel / message / note
            $table->text('resume')->nullable();                // résumé écrit de l'échange
            $table->string('audio_chemin')->nullable();        // note vocale (fichier son)
            $table->string('audio_type', 60)->nullable();      // type du fichier son
            $table->unsignedInteger('audio_duree')->nullable();// durée en secondes
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('reclamation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamation_interactions');
        Schema::dropIfExists('reclamations');
    }
};
