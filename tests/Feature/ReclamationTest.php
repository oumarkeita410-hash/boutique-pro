<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Reclamation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReclamationTest extends TestCase
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

    private function client(): Client
    {
        return Client::forceCreate([
            'prenom' => 'Fatou', 'nom' => 'Camara', 'telephone' => '+224620000000',
            'pays' => 'Guinée', 'ville' => 'Conakry',
        ]);
    }

    public function test_le_conseiller_ouvre_une_reclamation_et_se_l_attribue(): void
    {
        $conseiller = $this->user(Role::Conseiller);
        $client = $this->client();

        $this->withToken($conseiller->createToken('t')->plainTextToken)
            ->postJson('/api/reclamations', [
                'client_id' => $client->id,
                'produit' => 'Téléphone X',
                'probleme' => "L'écran s'éteint tout seul.",
            ])
            ->assertCreated()
            ->assertJsonFragment(['numero' => 1, 'produit' => 'Téléphone X', 'statut' => 'ouverte']);

        $this->assertDatabaseHas('reclamations', [
            'client_id' => $client->id, 'conseiller_id' => $conseiller->id, 'statut' => 'ouverte',
        ]);
    }

    public function test_un_vendeur_n_a_pas_acces_au_sav(): void
    {
        $vendeur = $this->user(Role::Vendeur);

        $this->withToken($vendeur->createToken('t')->plainTextToken)
            ->getJson('/api/reclamations')
            ->assertForbidden();
    }

    public function test_ajouter_un_resume_passe_la_reclamation_en_cours(): void
    {
        $conseiller = $this->user(Role::Conseiller);
        $r = Reclamation::create(['numero' => 1, 'produit' => 'Chargeur', 'probleme' => 'Ne charge plus', 'statut' => 'ouverte', 'conseiller_id' => $conseiller->id]);

        $this->withToken($conseiller->createToken('t')->plainTextToken)
            ->postJson("/api/reclamations/{$r->id}/interactions", ['type' => 'appel', 'resume' => 'Client rappelé, panne confirmée.'])
            ->assertCreated()
            ->assertJsonFragment(['type' => 'appel', 'resume' => 'Client rappelé, panne confirmée.']);

        $this->assertSame('en_cours', $r->fresh()->statut);
        $this->assertDatabaseHas('reclamation_interactions', ['reclamation_id' => $r->id, 'auteur_id' => $conseiller->id]);
    }

    public function test_une_interaction_vide_est_refusee(): void
    {
        $conseiller = $this->user(Role::Conseiller);
        $r = Reclamation::create(['numero' => 1, 'produit' => 'Chargeur', 'probleme' => 'Panne', 'statut' => 'ouverte']);

        $this->withToken($conseiller->createToken('t')->plainTextToken)
            ->postJson("/api/reclamations/{$r->id}/interactions", ['type' => 'appel'])
            ->assertStatus(422);
    }

    public function test_une_note_vocale_est_enregistree_et_relisible(): void
    {
        Storage::fake('local');
        $conseiller = $this->user(Role::Conseiller);
        $r = Reclamation::create(['numero' => 1, 'produit' => 'Batterie', 'probleme' => 'Gonflée', 'statut' => 'ouverte', 'conseiller_id' => $conseiller->id]);

        $audio = UploadedFile::fake()->create('note.webm', 40, 'audio/webm');

        // Un fichier s'envoie en multipart (post), pas en JSON.
        $rep = $this->withToken($conseiller->createToken('t')->plainTextToken)
            ->post("/api/reclamations/{$r->id}/interactions",
                ['type' => 'appel', 'duree' => 12, 'audio' => $audio],
                ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonFragment(['a_audio' => true, 'duree' => 12]);

        $interaction = $r->interactions()->first();
        $this->assertNotNull($interaction->audio_chemin);
        Storage::disk('local')->assertExists($interaction->audio_chemin);

        // La note vocale se relit (même origine, avec le jeton).
        $this->withToken($conseiller->createToken('t2')->plainTextToken)
            ->get($rep->json('audio_url') ? '/api'.$rep->json('audio_url') : "/api/reclamations/{$r->id}/interactions/{$interaction->id}/audio")
            ->assertOk();
    }

    public function test_seul_l_admin_peut_modifier_ou_supprimer(): void
    {
        $conseiller = $this->user(Role::Conseiller);
        $admin = $this->user(Role::Admin);
        $r = Reclamation::create(['numero' => 1, 'produit' => 'Écran', 'probleme' => 'Fissuré', 'statut' => 'ouverte']);

        // Le conseiller ne peut PAS modifier…
        Sanctum::actingAs($conseiller);
        $this->putJson("/api/reclamations/{$r->id}", ['produit' => 'Écran AMOLED'])
            ->assertForbidden();

        // …mais l'admin oui.
        Sanctum::actingAs($admin);
        $this->putJson("/api/reclamations/{$r->id}", ['produit' => 'Écran AMOLED'])
            ->assertOk()
            ->assertJsonFragment(['produit' => 'Écran AMOLED']);

        $this->deleteJson("/api/reclamations/{$r->id}")->assertOk();
        $this->assertDatabaseMissing('reclamations', ['id' => $r->id]);
    }

    public function test_changer_le_statut_en_resolue_date_la_resolution(): void
    {
        $conseiller = $this->user(Role::Conseiller);
        $r = Reclamation::create(['numero' => 1, 'produit' => 'Écran', 'probleme' => 'Fissuré', 'statut' => 'en_cours']);

        $this->withToken($conseiller->createToken('t')->plainTextToken)
            ->postJson("/api/reclamations/{$r->id}/statut", ['statut' => 'resolue'])
            ->assertOk()
            ->assertJsonFragment(['statut' => 'resolue']);

        $this->assertNotNull($r->fresh()->resolue_le);
    }
}
