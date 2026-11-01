<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Models\Produit;
use App\Models\Reparateur;
use App\Models\User;
use App\Notifications\DetteReparateurDepassee;
use App\Services\ReparateurService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReparateurServiceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'MOK', 'prenom' => 'Admin', 'identifiant' => 'admin'.uniqid(),
            'telephone' => '+224621952252', 'password' => 'motdepasse1',
            'role' => Role::Admin, 'est_verrouille' => false,
        ]);
    }

    private function vendeur(): User
    {
        return User::forceCreate([
            'name' => 'Bah', 'prenom' => 'Ali', 'identifiant' => 'v'.uniqid(),
            'telephone' => '+224600000001', 'password' => 'motdepasse1',
            'role' => Role::Vendeur, 'est_verrouille' => false,
        ]);
    }

    private function ecran(int $prix): Produit
    {
        return Produit::create([
            'nom' => 'Écran iPhone 13 JH', 'categorie' => 'Écran de téléphone', 'modele' => 'iPhone 13 JH',
            'prix_achat' => 200000, 'prix_unitaire' => $prix, 'stock' => 50, 'actif' => true,
        ]);
    }

    public function test_alerte_quand_la_dette_depasse_le_seuil(): void
    {
        config(['boutique.seuil_dette_reparateur' => 500000]);
        Notification::fake();

        $admin = $this->admin();
        $reparateur = Reparateur::create(['prenom' => 'Kaliou', 'nom' => 'Condé']);
        $ecran = $this->ecran(300000);

        $service = app(ReparateurService::class);

        // 1er écran pris sans payer (crédit accordé par l'admin) : dette 300 000, sous le seuil → pas d'alerte.
        $service->enregistrerPrise($admin, $reparateur, $ecran->id, 1, 0);
        Notification::assertNothingSent();

        // 2e écran : dette 600 000 > 500 000 → alerte envoyée à l'admin.
        $service->enregistrerPrise($admin, $reparateur, $ecran->id, 1, 0);
        Notification::assertSentTo(
            User::where('role', Role::Admin)->get(),
            DetteReparateurDepassee::class,
        );

        $this->assertSame(600000, $reparateur->calculerDette());
    }

    public function test_un_vendeur_ne_peut_pas_accorder_de_credit(): void
    {
        $vendeur = $this->vendeur();
        $reparateur = \App\Models\Reparateur::create(['prenom' => 'Sory']);
        $ecran = $this->ecran(300000);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            // Paiement partiel = crédit → interdit au vendeur.
            app(ReparateurService::class)->enregistrerPrise($vendeur, $reparateur, $ecran->id, 1, 100000);
        } finally {
            $this->assertSame(0, $reparateur->prises()->count());
            $this->assertSame(50, $ecran->fresh()->stock);
        }
    }

    public function test_admin_peut_accorder_un_credit(): void
    {
        $admin = $this->admin();
        $reparateur = \App\Models\Reparateur::create(['prenom' => 'Amara']);
        $ecran = $this->ecran(300000);

        $prise = app(ReparateurService::class)->enregistrerPrise($admin, $reparateur, $ecran->id, 1, 100000);

        $this->assertSame(200000, $prise->reste());
    }

    public function test_paiement_reduit_la_dette(): void
    {
        $admin = $this->admin();
        $vendeur = $this->vendeur();
        $reparateur = Reparateur::create(['prenom' => 'Bamba']);
        $ecran = $this->ecran(400000);

        $service = app(ReparateurService::class);
        $service->enregistrerPrise($admin, $reparateur, $ecran->id, 1, 100000); // crédit admin : dette 300 000
        // Le remboursement, lui, peut être encaissé par un vendeur.
        $service->enregistrerPaiement($vendeur, $reparateur, 250000, ModePaiement::Especes);

        $this->assertSame(50000, $reparateur->calculerDette());
    }

    public function test_dette_manuelle_par_type_sans_modele(): void
    {
        $admin = $this->admin();
        $reparateur = Reparateur::create(['prenom' => 'Sékou']);

        // On choisit juste le type (batterie) et le prix, sans modèle.
        $this->withToken($admin->createToken('t')->plainTextToken)
            ->postJson("/api/admin/reparateurs/{$reparateur->id}/dette", [
                'categorie' => 'Batterie', 'prix' => 75000, 'quantite' => 1, 'montant_paye' => 0,
            ])->assertCreated();

        $prise = $reparateur->prises()->first();
        $this->assertSame('Batterie', $prise->categorie);
        $this->assertSame('Batterie', $prise->designation());
        $this->assertSame(75000, $reparateur->calculerDette());
    }

    public function test_dette_manuelle_refusee_sans_type(): void
    {
        $admin = $this->admin();
        $reparateur = Reparateur::create(['prenom' => 'Lamine']);

        $this->withToken($admin->createToken('t')->plainTextToken)
            ->postJson("/api/admin/reparateurs/{$reparateur->id}/dette", [
                'prix' => 50000, 'quantite' => 1, 'montant_paye' => 0,
            ])->assertStatus(422);
    }
}
