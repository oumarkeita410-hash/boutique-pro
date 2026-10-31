<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\StatutVente;
use App\Models\Produit;
use App\Models\TransactionPaiement;
use App\Models\User;
use App\Models\Vente;
use App\Services\Paiement\CinetPayService;
use App\Services\VenteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CinetPayPaiementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Compte marchand « configuré » pour les tests (valeurs factices).
        config()->set('boutique.cinetpay', [
            'base_url' => 'https://api-checkout.cinetpay.com/v2',
            'apikey' => 'cle-test', 'site_id' => '123456', 'secret_key' => 'secret',
            'devise' => 'GNF', 'canaux' => 'ALL',
            'return_url' => 'https://exemple.gn/app/', 'notify_url' => 'https://exemple.gn/api/webhooks/cinetpay',
        ]);
    }

    private function vendeur(): User
    {
        return User::forceCreate([
            'name' => 'Diallo', 'prenom' => 'Mamadou', 'identifiant' => 'vendeur'.uniqid(),
            'telephone' => '+224600000000', 'password' => 'motdepasse1',
            'role' => Role::Vendeur, 'est_verrouille' => false,
        ]);
    }

    private function venteEnAttente(User $vendeur, int $prix = 50000): Vente
    {
        $produit = Produit::create([
            'nom' => 'Écran A14', 'categorie' => 'Écran de téléphone', 'modele' => 'A14',
            'prix_achat' => 30000, 'prix_unitaire' => $prix, 'stock' => 10, 'actif' => true,
        ]);

        return app(VenteService::class)->vendreArticle($vendeur, $produit->id, 1, ModePaiement::OrangeMoney);
    }

    public function test_initier_cree_une_transaction_et_renvoie_le_lien(): void
    {
        Http::fake(['*/v2/payment' => Http::response([
            'code' => '201', 'message' => 'CREATED',
            'data' => ['payment_token' => 'tok_abc', 'payment_url' => 'https://checkout.cinetpay.com/pay/tok_abc'],
        ])]);

        $vente = $this->venteEnAttente($this->vendeur());
        $resultat = app(CinetPayService::class)->initier($vente);

        $this->assertSame('https://checkout.cinetpay.com/pay/tok_abc', $resultat['url_paiement']);
        $this->assertDatabaseHas('transactions_paiement', [
            'vente_id' => $vente->id, 'canal' => 'cinetpay', 'statut' => 'en_attente', 'montant' => 50000,
        ]);
    }

    public function test_montant_non_multiple_de_5_est_refuse(): void
    {
        $vente = $this->venteEnAttente($this->vendeur(), prix: 50001);

        $this->expectException(RuntimeException::class);
        app(CinetPayService::class)->initier($vente);
    }

    public function test_verifier_marque_la_vente_payee_si_accepted_et_montant_identique(): void
    {
        $vente = $this->venteEnAttente($this->vendeur());
        $trx = TransactionPaiement::create([
            'vente_id' => $vente->id, 'canal' => 'cinetpay', 'reference_interne' => 'CP-X',
            'montant' => 50000, 'statut' => 'en_attente',
        ]);

        Http::fake(['*/v2/payment/check' => Http::response([
            'code' => '00', 'message' => 'SUCCES',
            'data' => ['status' => 'ACCEPTED', 'amount' => 50000, 'currency' => 'GNF', 'operator_id' => 'OP1'],
        ])]);

        app(CinetPayService::class)->verifier('CP-X');

        $this->assertSame('reussi', $trx->fresh()->statut);
        $this->assertSame(StatutVente::Payee, $vente->fresh()->statut);
    }

    public function test_verifier_ne_paye_pas_si_montant_different(): void
    {
        $vente = $this->venteEnAttente($this->vendeur());
        TransactionPaiement::create([
            'vente_id' => $vente->id, 'canal' => 'cinetpay', 'reference_interne' => 'CP-Y',
            'montant' => 50000, 'statut' => 'en_attente',
        ]);

        // CinetPay dit « accepté » mais pour un montant qui ne correspond pas : on refuse.
        Http::fake(['*/v2/payment/check' => Http::response([
            'code' => '00', 'data' => ['status' => 'ACCEPTED', 'amount' => 10000, 'currency' => 'GNF'],
        ])]);

        app(CinetPayService::class)->verifier('CP-Y');

        $this->assertNotSame(StatutVente::Payee, $vente->fresh()->statut);
    }

    public function test_le_webhook_confirme_la_vente_apres_re_verification(): void
    {
        $vente = $this->venteEnAttente($this->vendeur());
        TransactionPaiement::create([
            'vente_id' => $vente->id, 'canal' => 'cinetpay', 'reference_interne' => 'CP-Z',
            'montant' => 50000, 'statut' => 'en_attente',
        ]);

        Http::fake(['*/v2/payment/check' => Http::response([
            'code' => '00', 'data' => ['status' => 'ACCEPTED', 'amount' => 50000, 'currency' => 'GNF'],
        ])]);

        // Le webhook n'envoie que l'identifiant ; le service re-vérifie auprès de CinetPay.
        $this->postJson('/api/webhooks/cinetpay', ['cpm_trans_id' => 'CP-Z', 'cpm_site_id' => '123456'])
            ->assertOk()
            ->assertJson(['recu' => true]);

        $this->assertSame(StatutVente::Payee, $vente->fresh()->statut);
    }

    public function test_le_vendeur_demarre_le_paiement_en_ligne_de_sa_vente(): void
    {
        Http::fake(['*/v2/payment' => Http::response([
            'code' => '201', 'message' => 'CREATED',
            'data' => ['payment_token' => 'tok_1', 'payment_url' => 'https://checkout.cinetpay.com/pay/tok_1'],
        ])]);

        $vendeur = $this->vendeur();
        $vente = $this->venteEnAttente($vendeur);

        $this->withToken($vendeur->createToken('t')->plainTextToken)
            ->postJson('/api/ventes/'.$vente->id.'/payer-en-ligne', ['telephone' => '620000000'])
            ->assertOk()
            ->assertJsonStructure(['url_paiement', 'reference', 'message']);
    }

    public function test_un_vendeur_ne_peut_pas_payer_la_vente_d_un_autre(): void
    {
        $vente = $this->venteEnAttente($this->vendeur());
        $autre = $this->vendeur();

        $this->withToken($autre->createToken('t')->plainTextToken)
            ->postJson('/api/ventes/'.$vente->id.'/payer-en-ligne')
            ->assertForbidden();
    }
}
