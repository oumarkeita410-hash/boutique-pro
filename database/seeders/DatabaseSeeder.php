<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            ReparateurSeeder::class,
            // Catalogue réel de la boutique (export JSON). Remplace le catalogue vide de départ.
            CatalogueJsonSeeder::class,
        ]);
    }
}
