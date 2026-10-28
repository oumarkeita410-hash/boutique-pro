<?php

namespace Database\Seeders;

use App\Models\Reparateur;
use Illuminate\Database\Seeder;

class ReparateurSeeder extends Seeder
{
    /** [prénom, nom] : téléphone et email à compléter par l'administrateur. */
    private const REPARATEURS = [
        ['Kaliou', 'Condé'],
        ['Youssouf', null],
        ['Bandjan', null],
        ['Mohamed', 'Konaté'],
        ['Ousmane', null],
        ['Sory', null],
        ['Adama', 'Diakité'],
        ['Mohamed', 'Salah'],
        ['Bamba', null],
        ['Hamidou', null],
        ['Mara', null],
        ['Amara', null],
        ['Boubacar', 'Mara'],
    ];

    public function run(): void
    {
        foreach (self::REPARATEURS as [$prenom, $nom]) {
            Reparateur::firstOrCreate(['prenom' => $prenom, 'nom' => $nom]);
        }
    }
}
