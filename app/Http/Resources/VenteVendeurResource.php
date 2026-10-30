<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ce que le vendeur reçoit après une vente : ni le stock restant,
 * ni le prix unitaire, ni le prix d'achat. Seulement le montant à encaisser.
 */
class VenteVendeurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'date' => $this->created_at->toDateTimeString(),
            'articles' => $this->lignes->map(fn ($ligne) => [
                'nom' => $ligne->produit->nom,
                'quantite' => $ligne->quantite,
            ]),
            'montant_a_encaisser' => $this->montant_total,
            'montant_paye' => $this->montant_paye,
            'reste' => $this->reste(),
            'devise' => 'GNF',
            'mode_paiement' => $this->mode_paiement->value,
            'statut' => $this->statut->value,
        ];
    }
}
