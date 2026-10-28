<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaieVendeurTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'MOK', 'prenom' => 'Admin', 'identifiant' => 'admin',
            'telephone' => '+224621952252', 'password' => 'motdepasse1',
            'role' => Role::Admin, 'est_verrouille' => false,
        ]);
    }

    private function vendeur(): User
    {
        return User::forceCreate([
            'name' => 'Sylla', 'prenom' => 'Fanta', 'identifiant' => 'fanta',
            'telephone' => '+224600000002', 'password' => 'motdepasse1',
            'role' => Role::Vendeur, 'est_verrouille' => false,
            'frequence_paie' => 'mois', 'montant_paie' => 1500000,
        ]);
    }

    public function test_un_vendeur_ne_peut_pas_etre_paye_deux_fois_pour_la_meme_periode(): void
    {
        $admin = $this->admin();
        $vendeur = $this->vendeur();
        $token = $admin->createToken('test')->plainTextToken;

        $payload = ['user_id' => $vendeur->id, 'frequence' => 'mois', 'date' => '2026-10-15', 'mode_paiement' => 'orange_money'];

        $this->withToken($token)->postJson('/api/admin/paie', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/admin/paie', $payload)->assertStatus(422);

        $this->assertDatabaseCount('paiements_vendeur', 1);
    }
}
