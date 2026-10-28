<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Connexion par identifiant ; l'email devient facultatif.
            $table->string('email')->nullable()->change();
            $table->string('identifiant')->unique()->after('name');
            $table->string('prenom')->nullable()->after('identifiant');
            $table->string('telephone', 30)->nullable()->after('email');
            $table->string('role', 20)->default('vendeur')->index();
            $table->boolean('est_verrouille')->default(false);
            $table->timestamp('verrouille_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['identifiant', 'prenom', 'telephone', 'role', 'est_verrouille', 'verrouille_le']);
        });
    }
};
