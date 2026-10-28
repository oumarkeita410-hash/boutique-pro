<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Crée le compte administrateur MOK-Business.
 * Le mot de passe vient du fichier .env (ADMIN_PASSWORD), jamais du code.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $motDePasse = env('ADMIN_PASSWORD');

        if (! $motDePasse || strlen($motDePasse) < 10) {
            throw new RuntimeException('Définissez ADMIN_PASSWORD (10 caractères minimum) dans le fichier .env.');
        }

        $admin = User::firstOrNew(['identifiant' => env('ADMIN_IDENTIFIANT', 'admin')]);
        $admin->fill([
            'name' => 'MOK-Business',
            'prenom' => 'Administrateur',
            'email' => env('ADMIN_EMAIL'),
            'telephone' => env('ADMIN_TELEPHONE'),
        ]);
        $admin->role = Role::Admin;

        if (! $admin->exists) {
            $admin->password = $motDePasse;
        }

        $admin->save();
    }
}
