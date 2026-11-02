<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Accueil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccueilTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role): User
    {
        return User::forceCreate([
            'name' => 'Test', 'prenom' => ucfirst($role->value), 'identifiant' => $role->value.uniqid(),
            'telephone' => '+2246'.random_int(10000000, 99999999), 'password' => 'motdepasse1',
            'role' => $role, 'est_verrouille' => false,
        ]);
    }

    public function test_le_conseiller_ajoute_un_client_a_la_file(): void
    {
        $conseiller = $this->user(Role::Conseiller);

        $this->withToken($conseiller->createToken('t')->plainTextToken)
            ->postJson('/api/accueil', ['client_nom' => 'Fatou', 'motif' => 'achat'])
            ->assertCreated()
            ->assertJsonFragment(['ticket' => 1, 'client' => 'Fatou', 'motif' => 'achat', 'statut' => 'en_attente']);

        $this->assertDatabaseHas('accueils', ['client_nom' => 'Fatou', 'conseiller_id' => $conseiller->id]);
    }

    public function test_le_numero_de_ticket_augmente(): void
    {
        $conseiller = $this->user(Role::Conseiller);
        $token = $conseiller->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/accueil', ['motif' => 'achat'])->assertJsonFragment(['ticket' => 1]);
        $this->withToken($token)->postJson('/api/accueil', ['motif' => 'reparation'])->assertJsonFragment(['ticket' => 2]);
    }

    public function test_un_vendeur_ne_peut_pas_ajouter_a_la_file(): void
    {
        $vendeur = $this->user(Role::Vendeur);

        $this->withToken($vendeur->createToken('t')->plainTextToken)
            ->postJson('/api/accueil', ['motif' => 'achat'])
            ->assertForbidden();
    }

    public function test_prendre_puis_terminer(): void
    {
        $conseiller = $this->user(Role::Conseiller);
        $token = $conseiller->createToken('t')->plainTextToken;
        $a = Accueil::create(['ticket' => 1, 'motif' => 'achat', 'statut' => 'en_attente', 'conseiller_id' => $conseiller->id]);

        $this->withToken($token)->postJson("/api/accueil/{$a->id}/prendre")->assertOk()->assertJsonFragment(['statut' => 'en_cours']);
        $this->assertSame($conseiller->id, $a->fresh()->pris_par);

        $this->withToken($token)->postJson("/api/accueil/{$a->id}/terminer")->assertOk()->assertJsonFragment(['statut' => 'termine']);
        $this->assertNotNull($a->fresh()->termine_le);
    }

    public function test_le_vendeur_agit_seulement_sur_le_client_qu_on_lui_envoie(): void
    {
        $conseiller = $this->user(Role::Conseiller);
        $vendeur = $this->user(Role::Vendeur);
        $autre = $this->user(Role::Vendeur);

        $pourLui = Accueil::create(['ticket' => 1, 'motif' => 'achat', 'statut' => 'en_attente', 'vendeur_id' => $vendeur->id]);
        $pourAutre = Accueil::create(['ticket' => 2, 'motif' => 'achat', 'statut' => 'en_attente', 'vendeur_id' => $autre->id]);

        $token = $vendeur->createToken('t')->plainTextToken;
        // Il peut prendre le client qu'on lui a envoyé…
        $this->withToken($token)->postJson("/api/accueil/{$pourLui->id}/prendre")->assertOk();
        // …mais pas celui envoyé à un autre vendeur.
        $this->withToken($token)->postJson("/api/accueil/{$pourAutre->id}/prendre")->assertForbidden();
    }

    public function test_la_file_liste_attente_et_en_cours(): void
    {
        $conseiller = $this->user(Role::Conseiller);
        Accueil::create(['ticket' => 1, 'motif' => 'achat', 'statut' => 'en_attente']);
        Accueil::create(['ticket' => 2, 'motif' => 'reparation', 'statut' => 'en_cours', 'pris_par' => $conseiller->id, 'pris_le' => now()]);
        Accueil::create(['ticket' => 3, 'motif' => 'achat', 'statut' => 'termine', 'termine_le' => now()]);

        $this->withToken($conseiller->createToken('t')->plainTextToken)
            ->getJson('/api/accueil')
            ->assertOk()
            ->assertJsonCount(1, 'en_attente')
            ->assertJsonCount(1, 'en_cours')
            ->assertJsonFragment(['nombre_attente' => 1, 'termines_jour' => 1]);
    }
}
