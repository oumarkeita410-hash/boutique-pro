<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages modifiables par l'administrateur depuis l'application
 * (ex. numéro du SAV), sans toucher au code ni au fichier .env.
 * Table clé/valeur simple, réutilisable pour d'autres réglages plus tard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglages', function (Blueprint $table) {
            $table->id();
            $table->string('cle')->unique();
            $table->text('valeur')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglages');
    }
};
