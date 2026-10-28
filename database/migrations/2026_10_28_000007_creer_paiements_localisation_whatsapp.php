<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Suivi des paiements en ligne (Orange Money, carte via agrégateur).
        // La vente reste la source du montant ; cette table trace l'échange avec l'opérateur.
        Schema::create('transactions_paiement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->string('canal', 20);                 // orange_money, carte
            $table->string('reference_interne')->unique(); // notre id envoyé à l'opérateur
            $table->string('reference_operateur')->nullable()->index(); // id renvoyé par l'opérateur
            $table->unsignedBigInteger('montant');
            $table->string('statut', 20)->index();        // en_attente, reussi, echoue, expire
            $table->string('telephone_payeur', 30)->nullable();
            $table->json('reponse_operateur')->nullable(); // trace brute (sans données secrètes)
            $table->timestamp('confirme_le')->nullable();
            $table->timestamps();
        });

        // Localisation VISIBLE : enregistrée à la connexion et à chaque vente.
        // Le vendeur en est informé à la connexion (voir AuthController).
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vente_id')->nullable()->constrained('ventes')->nullOnDelete();
            $table->string('contexte', 20);               // connexion, vente
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('precision_m')->nullable(); // précision GPS en mètres
            $table->string('ville', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('pays', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('ventes', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('statut');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
        Schema::dropIfExists('positions');
        Schema::dropIfExists('transactions_paiement');
    }
};
