<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Models\Produit;
use App\Models\User;
use App\Models\Vente;
use App\Services\VenteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecuriteAvanceeTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role, array $extra = []): User
    {
        return User::forceCreate(array_merge([
            'name' => 'Test', 'prenom' => ucfirst($role->value), 'identifiant' => $role->value.uniqid(),
            'telephone' => '+2246'.random_int(10000000, 99999999), 'password' => 'motdepasse1',
            'role' => $role, 'est_verrouille' => false,
        ], $extra));
    }

    public function test_connexion_possible_par_telephone(): void
    {
        $this->user(Role::Vendeur, ['identifiant' => 'fatou', 'telephone' => '+224611111111', 'password' => 'secret123']);

        $this->postJson('/api/connexion', ['identifiant' => '+224611111111', 'password' => 'secret123'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_trois_intrusions_bloquent_le_compte_vendeur(): void
    {
        $vendeur = $this->user(Role::Vendeur);
        $token = $vendeur->createToken('t')->plainTextToken;

        for ($i = 1; $i <= 2; $i++) {
            $this->withToken($token)->getJson('/api/admin/utilisateurs')->assertForbidden();
            $this->assertFalse($vendeur->fresh()->est_verrouille, "Pas encore bloqué à l'essai $i");
        }

        // 3e tentative : blocage automatique.
        $this->withToken($token)->getJson('/api/admin/utilisateurs')->assertForbidden();
        $this->assertTrue($vendeur->fresh()->est_verrouille);
        $this->assertSame(3, $vendeur->fresh()->tentatives_intrusion);
    }

    public function test_reactivation_admin_remet_le_compteur_a_zero(): void
    {
        $admin = $this->user(Role::Admin);
        $vendeur = $this->user(Role::Vendeur, ['est_verrouille' => true, 'tentatives_intrusion' => 3]);

        $this->withToken($admin->createToken('t')->plainTextToken)
            ->postJson("/api/admin/utilisateurs/{$vendeur->id}/deverrouiller")
            ->assertOk();

        $this->assertFalse($vendeur->fresh()->est_verrouille);
        $this->assertSame(0, $vendeur->fresh()->tentatives_intrusion);
    }

    public function test_produit_en_double_refuse(): void
    {
        $admin = $this->user(Role::Admin);
        $token = $admin->createToken('t')->plainTextToken;
        $p = ['nom' => 'Écran A14 5G', 'modele' => 'A14 5G', 'categorie' => 'Écran de téléphone', 'prix_unitaire' => 150000];

        $this->withToken($token)->postJson('/api/admin/produits', $p)->assertCreated();
        $this->withToken($token)->postJson('/api/admin/produits', $p)->assertStatus(422);
    }

    public function test_vente_orange_money_confirmee_par_admin(): void
    {
        $vendeur = $this->user(Role::Vendeur);
        $admin = $this->user(Role::Admin);
        $produit = Produit::create(['nom' => 'Écran A16', 'modele' => 'A16', 'categorie' => 'Écran de téléphone',
            'prix_achat' => 90000, 'prix_unitaire' => 160000, 'stock' => 10, 'actif' => true]);

        // Vente Orange Money : reste "en attente de paiement".
        $vente = app(VenteService::class)->vendreArticle($vendeur, $produit->id, 1, ModePaiement::OrangeMoney);
        $this->assertSame(\App\Enums\StatutVente::EnAttentePaiement, $vente->fresh()->statut);

        // Le vendeur enregistre la référence de la transaction.
        Sanctum::actingAs($vendeur);
        $this->postJson("/api/ventes/{$vente->id}/reference-paiement", ['reference_paiement' => 'OM-TEST-123'])->assertOk();

        // L'administrateur confirme → payée.
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/ventes/{$vente->id}/confirmer-paiement")->assertOk();

        $this->assertSame(\App\Enums\StatutVente::Payee, $vente->fresh()->statut);
        $this->assertSame('OM-TEST-123', $vente->fresh()->reference_paiement);
        $this->assertDatabaseHas('journal_activites', ['action' => 'paiement_confirme']);
    }

    public function test_un_vendeur_ne_peut_pas_vendre_a_credit(): void
    {
        $vendeur = $this->user(Role::Vendeur);
        $client = \App\Models\Client::create(['prenom' => 'Fode', 'nom' => 'Bah', 'telephone' => '+224655000111', 'pays' => 'Guinée', 'ville' => 'Conakry']);
        $produit = Produit::create(['nom' => 'Écran A17', 'modele' => 'A17', 'categorie' => 'Écran de téléphone', 'prix_achat' => 90000, 'prix_unitaire' => 170000, 'stock' => 10, 'actif' => true]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            app(VenteService::class)->vendre($vendeur, [['produit_id' => $produit->id, 'quantite' => 1]], ModePaiement::Credit, $client->id);
        } finally {
            $this->assertSame(0, Vente::count());
            $this->assertSame(10, $produit->fresh()->stock);
        }
    }

    public function test_admin_vend_a_credit_puis_remboursement(): void
    {
        $admin = $this->user(Role::Admin);
        $client = \App\Models\Client::create(['prenom' => 'Fode', 'nom' => 'Bah', 'telephone' => '+224655000222', 'pays' => 'Guinée', 'ville' => 'Conakry']);
        $produit = Produit::create(['nom' => 'Écran A24', 'modele' => 'A24', 'categorie' => 'Écran de téléphone', 'prix_achat' => 90000, 'prix_unitaire' => 200000, 'stock' => 10, 'actif' => true]);

        // Admin vend 2 écrans à crédit : dette 400 000.
        $vente = app(VenteService::class)->vendre($admin, [['produit_id' => $produit->id, 'quantite' => 2]], ModePaiement::Credit, $client->id);
        $this->assertSame(\App\Enums\StatutVente::ACredit, $vente->statut);
        $this->assertSame(8, $produit->fresh()->stock);

        // Le client doit 400 000.
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/credits')->assertOk()->assertJsonFragment(['client_id' => $client->id, 'dette' => 400000]);

        // Remboursement de 250 000 → reste 150 000.
        $this->postJson("/api/admin/credits/{$client->id}/remboursement", ['montant' => 250000, 'mode_paiement' => 'especes'])
            ->assertOk()->assertJsonFragment(['dette_restante' => 150000]);

        // Solde du reste → vente payée.
        $this->postJson("/api/admin/credits/{$client->id}/remboursement", ['montant' => 150000, 'mode_paiement' => 'orange_money'])->assertOk();
        $this->assertSame(\App\Enums\StatutVente::Payee, $vente->fresh()->statut);
    }

    public function test_vente_non_modifiable_sans_accord_admin(): void
    {
        $vendeur = $this->user(Role::Vendeur);
        $admin = $this->user(Role::Admin);
        $produit = Produit::create(['nom' => 'Écran A54', 'modele' => 'A54', 'categorie' => 'Écran de téléphone',
            'prix_achat' => 100000, 'prix_unitaire' => 180000, 'stock' => 10, 'actif' => true]);

        $vente = app(VenteService::class)->vendreArticle($vendeur, $produit->id, 1, ModePaiement::Especes);

        // Le vendeur demande une modification : la vente ne change pas encore.
        Sanctum::actingAs($vendeur);
        $this->postJson("/api/ventes/{$vente->id}/demande-modification", [
            'mode_paiement' => 'orange_money', 'motif_demande' => 'Le client a payé par Orange Money',
        ])->assertCreated();

        $this->assertSame(ModePaiement::Especes, $vente->fresh()->mode_paiement);

        // L'admin approuve : la vente change et la trace est conservée.
        $demandeId = \App\Models\DemandeModificationVente::first()->id;
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/modifications-vente/{$demandeId}/approuver")
            ->assertOk();

        $this->assertSame(ModePaiement::OrangeMoney, $vente->fresh()->mode_paiement);
        $this->assertDatabaseHas('journal_activites', ['action' => 'vente_modifiee']);
    }
}
