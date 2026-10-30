<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une séance de caisse par jour : ouverture (fond) et clôture (comptage réel + écart).
        Schema::create('caisse_jours', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedBigInteger('fond_ouverture')->default(0);
            $table->unsignedBigInteger('montant_reel')->nullable();     // argent compté dans le tiroir
            $table->bigInteger('solde_theorique')->nullable();          // ce que l'appli attend
            $table->bigInteger('ecart')->nullable();                    // réel − théorique (négatif = manquant)
            $table->string('statut', 20)->default('ouverte');           // ouverte, cloturee
            $table->foreignId('ouvert_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cloture_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caisse_jours');
    }
};
