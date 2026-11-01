<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\StatutVente;
use App\Models\Produit;
use App\Models\TransactionPaiement;
use App\Models\User;
use App\Models\Vente;
use App\Services\VenteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuriteEnTetesTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_reponses_portent_les_entetes_de_securite(): void
    {
        $reponse = $this->postJson('/api/connexion', ['identifiant' => 'x', 'password' => 'y']);

        $reponse->assertHeader('X-Content-Type-Options', 'nosniff');
        $reponse->assertHeader('X-Frame-Options', 'DENY');
        $reponse->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    private function venteEnAttente(): Vente
    {
        $vendeur = User::forceCreate([
            'name' => 'Diallo', 'prenom' => 'M', 'identifiant' => 'v'.uniqid(),
            'telephone' => '+224600000000', 'password' => 'motdepasse1',
            'role' => Role::Vendeur, 'est_verrouille' => false,
        ]);
        $produit = Produit::create([
            'nom' => 'Écran A14', 'categorie' => 'Écran de téléphone', 'modele' => 'A14',
            'prix_achat' => 30000, 'prix_unitaire' => 50000, 'stock' => 10, 'actif' => true,
        ]);

        return app(VenteService::class)->vendreArticle($vendeur, $produit->id, 1, ModePaiement::OrangeMoney);
    }

    public function test_un_faux_webhook_orange_money_ne_paye_pas_la_vente(): void
    {
        $vente = $this->venteEnAttente();
        // Transaction initiée avec un jeton de notification secret.
        TransactionPaiement::create([
            'vente_id' => $vente->id, 'canal' => 'orange_money', 'reference_interne' => 'OM-FAUX',
            'montant' => 50000, 'statut' => 'en_attente',
            'reponse_operateur' => ['notif_token' => 'secret-attendu'],
        ]);

        // Un attaquant envoie un faux « SUCCESS » SANS le bon jeton : doit être ignoré.
        $this->postJson('/api/webhooks/orange-money', [
            'order_id' => 'OM-FAUX', 'status' => 'SUCCESS', 'amount' => 50000, 'notif_token' => 'mauvais',
        ])->assertOk();

        $this->assertNotSame(StatutVente::Payee, $vente->fresh()->statut);
    }

    public function test_un_vrai_webhook_orange_money_avec_le_bon_jeton_paye_la_vente(): void
    {
        $vente = $this->venteEnAttente();
        TransactionPaiement::create([
            'vente_id' => $vente->id, 'canal' => 'orange_money', 'reference_interne' => 'OM-VRAI',
            'montant' => 50000, 'statut' => 'en_attente',
            'reponse_operateur' => ['notif_token' => 'secret-attendu'],
        ]);

        $this->postJson('/api/webhooks/orange-money', [
            'order_id' => 'OM-VRAI', 'status' => 'SUCCESS', 'amount' => 50000, 'notif_token' => 'secret-attendu',
        ])->assertOk();

        $this->assertSame(StatutVente::Payee, $vente->fresh()->statut);
    }
}
