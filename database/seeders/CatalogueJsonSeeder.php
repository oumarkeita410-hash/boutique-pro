<?php

namespace Database\Seeders;

use App\Models\Produit;
use Illuminate\Database\Seeder;

/**
 * Charge le catalogue réel (prix, stock) depuis database/seeders/data/produits.json.
 * Ce fichier est un export des produits de la boutique (écrans, batteries, pièces,
 * petit écran, carte SIM, cova…), pour que la version en ligne ait les mêmes données.
 * Relançable : met à jour les produits existants, ne crée pas de doublon.
 */
class CatalogueJsonSeeder extends Seeder
{
    public function run(): void
    {
        $chemin = database_path('seeders/data/produits.json');

        if (! is_file($chemin)) {
            $this->command?->warn("Fichier catalogue introuvable : {$chemin}");
            return;
        }

        $produits = json_decode(file_get_contents($chemin), true) ?: [];

        foreach ($produits as $p) {
            Produit::updateOrCreate(
                ['nom' => $p['nom'], 'categorie' => $p['categorie'] ?? null],
                [
                    'modele' => $p['modele'] ?? null,
                    'prix_achat' => (int) ($p['prix_achat'] ?? 0),
                    'prix_unitaire' => (int) ($p['prix_unitaire'] ?? 0),
                    'stock' => (int) ($p['stock'] ?? 0),
                    'seuil_alerte' => (int) ($p['seuil_alerte'] ?? 5),
                    'actif' => (bool) ($p['actif'] ?? false),
                ],
            );
        }

        $this->command?->info(count($produits).' produits chargés.');
    }
}
