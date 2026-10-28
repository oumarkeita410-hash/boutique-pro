<?php

namespace Database\Seeders;

use App\Models\Produit;
use Illuminate\Database\Seeder;

/**
 * Crée le catalogue MOK-Business. Les produits sont créés INACTIFS, prix 0, stock 0 :
 * l'administrateur fixe le prix, approvisionne, puis active chaque produit.
 * Tant qu'un produit est inactif, personne ne peut le vendre.
 */
class CatalogueSeeder extends Seeder
{
    /** Modèles de téléphone (servent pour les écrans ET les plaquettes). */
    private const MODELES_TELEPHONE = [
        // Samsung
        'A03 Core', 'A03', 'A03s', 'A04', 'A04E', 'A04s', 'A05', 'A05s', 'A06', 'A07',
        'A10', 'A10s', 'A11', 'A12', 'A13', 'A14 4G', 'A14 5G', 'A15', 'A16', 'A17',
        'A20', 'A20E', 'A20s', 'A21', 'A22', 'A23', 'A24', 'A25', 'A26', 'A30', 'A32',
        'A33', 'A34', 'A42', 'A50', 'A51 4G', 'A51 5G', 'A52', 'A53', 'A54', 'A56',
        'A58', 'A58 Lite', 'A60', 'A80', 'A82', 'A90', 'M15', 'M16', 'M20',
        'S8', 'S8+', 'S9+', 'Note 9',
        // Tecno / Infinix / itel
        'BA2', 'BB2', 'BC1', 'BC2', 'BC3', 'BD2', 'BD3', 'BD4', 'BE7', 'BE8', 'BF6', 'BF7',
        'BG6', 'BG6M', 'BG7', 'CD6', 'CD7', 'CE8', 'CE9', 'CG6', 'CG8', 'CH6', 'Ci6',
        'CK6', 'CK7N', 'CK8N', 'CL6', 'CL7K', 'CM5', 'CM6', 'F16', 'KC8', 'KD6', 'KD7',
        'KE5', 'KF6', 'KF7', 'KF8', 'KG5', 'KG5J', 'KG6', 'KG7', 'KH6', 'Ki5', 'Ki7',
        'KJ5', 'KJ6', 'KJ7', 'KL4', 'KL5', 'KL6', 'KM4', 'KM5', 'KM6', 'KN3', 'KN4', 'KN8',
        'LC6', 'S17', 'A662', 'X612', 'X624', 'X653', 'X657', 'X665', 'X680', 'X682',
        'X683', 'X688', 'X6517', 'X6525', 'X6532', 'X6725', 'X6816', 'X6823', 'X6825',
        'X6840', 'X6886',
        // Xiaomi / Redmi
        'Redmi 12', 'Redmi 13C', 'Redmi 14C', 'Redmi 15C', 'Redmi A03', 'Redmi A2', 'Redmi A5',
        // Huawei
        'Huawei P30', 'Huawei P30 Pro', 'Huawei P30L', 'Huawei P40 Pro',
        // iPhone
        'iPhone 8+', 'iPhone X OLED',
        'iPhone X IN CELL', 'iPhone X JH', 'iPhone XR IN CELL', 'iPhone XR JH',
        'iPhone 11 IN CELL', 'iPhone 11 JH', 'iPhone 11 Pro IN CELL', 'iPhone 11 Pro JH',
        'iPhone 11 Pro Max IN CELL', 'iPhone 11 Pro Max JH',
        'iPhone 12 IN CELL', 'iPhone 12 JH', 'iPhone 12 Pro IN CELL', 'iPhone 12 Pro JH',
        'iPhone 12 Pro Max IN CELL', 'iPhone 12 Pro Max JH',
        'iPhone 13 IN CELL', 'iPhone 13 JH', 'iPhone 13 Pro IN CELL', 'iPhone 13 Pro JH',
        'iPhone 13 Pro Max IN CELL', 'iPhone 13 Pro Max JH',
        'iPhone 14 IN CELL', 'iPhone 14 JH', 'iPhone 14 Pro IN CELL', 'iPhone 14 Pro JH',
        'iPhone 14 Pro Max IN CELL', 'iPhone 14 Pro Max JH',
        'iPhone 15 Pro IN CELL', 'iPhone 15 Pro JH', 'iPhone 15 Pro Max IN CELL', 'iPhone 15 Pro Max JH',
    ];

    /** catégorie => [nom => [modèles…] ou null] */
    private const AUTRES_PRODUITS = [
        'Réseau' => [
            'MikroTik hAP ax3' => null,
            'Câble RJ45' => null,
            'Switch Gigabit' => null,
            'Antenne Grandstream 7664' => null,
        ],
        'Informatique' => [
            'Ordinateur portable' => null,
            'Clavier' => null,
            'Souris' => null,
            'Disque dur externe' => null,
            'Clé USB' => null,
            'Carte mémoire' => null,
        ],
        'Téléphonie' => [
            'Téléphone' => null,
            'Chargeur' => null,
            'Câble' => null,
            'Écouteurs' => null,
            'Anticase' => null,
            'Protège-téléphone' => null,
        ],
        'Pièces téléphone' => [
            'Petit écran de téléphone' => null,
            'Batterie de téléphone' => null,
            'Ruban' => null,
            'Allumage' => null,
        ],
        'Outils et consommables' => [
            'Tournevis' => null,
            'Colle' => null,
            'Gaz' => null,
        ],
        'Solaire' => [
            'Panneau solaire' => null,
            'Régulateur MPPT' => ['30A', '40A', '50A', '60A', '80A', '100A'],
            'Régulateur PWM' => ['30A', '40A', '50A', '60A', '80A', '100A'],
            'Fil de panneau solaire' => null,
            'Batterie solaire' => null,
            'Onduleur' => ['200W', '300W', '500W', '1000W', '1500W', '2000W'],
            'Ampoule' => null,
        ],
        'Électronique et électroménager' => [
            'Caméra de surveillance' => null,
            'Radio' => null,
            'Télévision' => null,
            'Télécommande' => null,
            'Congélateur' => null,
            'Rallonge' => null,
        ],
    ];

    public function run(): void
    {
        foreach (self::MODELES_TELEPHONE as $modele) {
            $this->creer("Écran {$modele}", 'Écran de téléphone', $modele);
            $this->creer("Plaquette {$modele}", 'Plaquette de téléphone', $modele);
        }

        foreach (self::AUTRES_PRODUITS as $categorie => $produits) {
            foreach ($produits as $nom => $modeles) {
                if ($modeles === null) {
                    $this->creer($nom, $categorie, null);
                    continue;
                }

                foreach ($modeles as $modele) {
                    $this->creer("{$nom} {$modele}", $categorie, $modele);
                }
            }
        }
    }

    private function creer(string $nom, string $categorie, ?string $modele): void
    {
        Produit::firstOrCreate(
            ['nom' => $nom, 'categorie' => $categorie],
            ['modele' => $modele, 'prix_unitaire' => 0, 'stock' => 0, 'actif' => false],
        );
    }
}
