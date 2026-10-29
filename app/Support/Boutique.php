<?php

namespace App\Support;

class Boutique
{
    /** En-tête affiché en haut des devis et des factures. */
    public static function entete(): array
    {
        $c = config('boutique.contact');

        return [
            'nom' => config('boutique.nom'),
            'ville' => $c['ville'] ?? null,
            'telephone' => $c['telephone'] ?? null,
            'whatsapp' => $c['whatsapp'] ?? null,
            'email' => $c['email'] ?? null,
            'orange_money' => $c['orange_money'] ?? null,
            'banque' => $c['banque'] ?? null,
            'rccm' => $c['rccm'] ?? null,
            'nif' => $c['nif'] ?? null,
            'devise' => config('boutique.devise'),
        ];
    }
}
