<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReglageTest extends TestCase
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

    public function test_l_admin_change_le_numero_du_sav(): void
    {
        $admin = $this->user(Role::Admin);
        Sanctum::actingAs($admin);

        // Avant : la valeur par défaut (config / .env).
        $this->getJson('/api/admin/reglages')
            ->assertOk()
            ->assertJsonFragment(['sav_numero' => config('boutique.sav.numero')]);

        // On change le numéro.
        $this->putJson('/api/admin/reglages', ['sav_numero' => '+224611223344'])
            ->assertOk()
            ->assertJsonFragment(['sav_numero' => '+224611223344']);

        $this->assertDatabaseHas('reglages', ['cle' => 'sav_numero', 'valeur' => '+224611223344']);
    }

    public function test_le_nouveau_numero_apparait_dans_le_sav(): void
    {
        $admin = $this->user(Role::Admin);
        Sanctum::actingAs($admin);
        $this->putJson('/api/admin/reglages', ['sav_numero' => '+224611223344'])->assertOk();

        // Le conseiller voit le numéro à jour dans la liste des réclamations.
        $conseiller = $this->user(Role::Conseiller);
        Sanctum::actingAs($conseiller);
        $this->getJson('/api/reclamations')
            ->assertOk()
            ->assertJsonFragment(['sav_numero' => '+224611223344']);
    }

    public function test_un_conseiller_ne_peut_pas_changer_les_reglages(): void
    {
        $conseiller = $this->user(Role::Conseiller);
        Sanctum::actingAs($conseiller);

        $this->putJson('/api/admin/reglages', ['sav_numero' => '+224600000000'])
            ->assertForbidden();
    }
}
