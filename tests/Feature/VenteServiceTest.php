<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\StatutVente;
use App\Exceptions\CompteVerrouilleException;
use App\Exceptions\StockInsuffisantException;
use App\Models\Produit;
use App\Models\User;
use App\Models\Vente;
use App\Services\VenteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenteServiceTest extends TestCase
{
    use RefreshDatabase;

    private function vendeur(array $extra = []): User
    {
        return User::forceCreate(array_merge([
            'name' => 'Diallo', 'prenom' => 'Mamadou',
            'identifiant' => 'vendeur'.uniqid(), 'telephone' => '+224600000000',
            'password' => 'motdepasse1', 'role' => Role::Vendeur, 'est_verrouille' => false,
        ], $extra));
    }

    private function produit(int $stock = 10, int $prix = 50000): Produit
    {
        return Produit::create([
            'nom' => 'Écran A14 4G', 'categorie' => 'Écran de téléphone', 'modele' => 'A14 4G',
            'prix_achat' => 30000, 'prix_unitaire' => $prix, 'stock' => $stock, 'actif' => true,
        ]);
    }

    public function test_vente_diminue_le_stock_et_enregistre_la_vente(): void
    {
        $vendeur = $this->vendeur();
        $produit = $this->produit(stock: 10, prix: 50000);

        $vente = app(VenteService::class)->vendreArticle($vendeur, $produit->id, 3, ModePaiement::Especes);

        $this->assertSame(150000, $vente->montant_total);
        $this->assertSame(StatutVente::Payee, $vente->statut);
        $this->assertSame(7, $produit->fresh()->stock);
        $this->assertDatabaseHas('mouvements_stock', [
            'produit_id' => $produit->id, 'quantite' => -3, 'stock_avant' => 10, 'stock_apres' => 7,
        ]);
    }

    public function test_stock_insuffisant_bloque_la_vente_et_ne_touche_pas_au_stock(): void
    {
        $vendeur = $this->vendeur();
        $produit = $this->produit(stock: 2);

        $this->expectException(StockInsuffisantException::class);

        try {
            app(VenteService::class)->vendreArticle($vendeur, $produit->id, 5, ModePaiement::Especes);
        } finally {
            $this->assertSame(2, $produit->fresh()->stock);
            $this->assertSame(0, Vente::count());
        }
    }

    public function test_compte_verrouille_ne_peut_pas_vendre(): void
    {
        $vendeur = $this->vendeur(['est_verrouille' => true]);
        $produit = $this->produit();

        $this->expectException(CompteVerrouilleException::class);
        app(VenteService::class)->vendreArticle($vendeur, $produit->id, 1, ModePaiement::Especes);
    }

    public function test_orange_money_reste_en_attente_de_paiement(): void
    {
        $vendeur = $this->vendeur();
        $produit = $this->produit();

        $vente = app(VenteService::class)->vendreArticle($vendeur, $produit->id, 1, ModePaiement::OrangeMoney);

        $this->assertSame(StatutVente::EnAttentePaiement, $vente->statut);
    }

    public function test_vente_hors_ligne_renvoyee_deux_fois_ne_compte_quune_fois(): void
    {
        $vendeur = $this->vendeur();
        $produit = $this->produit(stock: 10);
        $uuid = '11111111-1111-1111-1111-111111111111';

        $service = app(VenteService::class);
        $v1 = $service->vendre($vendeur, [['produit_id' => $produit->id, 'quantite' => 2]], ModePaiement::Especes, null, $uuid);
        $v2 = $service->vendre($vendeur, [['produit_id' => $produit->id, 'quantite' => 2]], ModePaiement::Especes, null, $uuid);

        $this->assertSame($v1->id, $v2->id);
        $this->assertSame(1, Vente::count());
        $this->assertSame(8, $produit->fresh()->stock); // diminué une seule fois
    }
}
