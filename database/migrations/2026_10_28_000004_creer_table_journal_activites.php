<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('derniere_connexion')->nullable();
        });

        Schema::create('journal_activites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 50)->index();
            $table->string('cible_type')->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['cible_type', 'cible_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_activites');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('derniere_connexion');
        });
    }
};
