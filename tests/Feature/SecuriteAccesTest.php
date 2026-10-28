<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuriteAccesTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role, bool $verrouille = false): User
    {
        return User::forceCreate([
            'name' => 'Test', 'prenom' => ucfirst($role->value), 'identifiant' => $role->value.uniqid(),
            'telephone' => '+224600000003', 'password' => 'motdepasse1',
            'role' => $role, 'est_verrouille' => $verrouille,
        ]);
    }

    public function test_le_catalogue_vendeur_ne_montre_ni_stock_ni_prix(): void
    {
        $vendeur = $this->user(Role::Vendeur);
        Produit::create([
            'nom' => 'Écran A54', 'categorie' => 'Écran de téléphone', 'modele' => 'A54',
            'prix_achat' => 100000, 'prix_unitaire' => 180000, 'stock' => 12, 'actif' => true,
        ]);

        $reponse = $this->withToken($vendeur->createToken('t')->plainTextToken)
            ->getJson('/api/catalogue')
            ->assertOk();

        $contenu = $reponse->getContent();
        $this->assertStringNotContainsString('180000', $contenu, 'Le prix unitaire ne doit pas apparaître.');
        $this->assertStringNotContainsString('100000', $contenu, 'Le prix d\'achat ne doit pas apparaître.');
        $this->assertStringNotContainsString('"stock"', $contenu, 'Le stock ne doit pas apparaître.');
    }

    public function test_un_vendeur_ne_peut_pas_acceder_a_l_espace_admin(): void
    {
        $vendeur = $this->user(Role::Vendeur);

        $this->withToken($vendeur->createToken('t')->plainTextToken)
            ->getJson('/api/admin/utilisateurs')
            ->assertForbidden();
    }

    public function test_un_compte_verrouille_est_refuse(): void
    {
        $vendeur = $this->user(Role::Vendeur, verrouille: true);

        $this->withToken($vendeur->createToken('t')->plainTextToken)
            ->getJson('/api/catalogue')
            ->assertForbidden();
    }

    public function test_mauvais_mot_de_passe_refuse_la_connexion(): void
    {
        $this->user(Role::Admin);
        $admin = User::where('role', Role::Admin)->first();

        $this->postJson('/api/connexion', ['identifiant' => $admin->identifiant, 'password' => 'faux'])
            ->assertStatus(422);
    }
}
