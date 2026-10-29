<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Vente;
use App\Support\Boutique;
use Illuminate\Http\Request;

/**
 * Facture imprimable d'une vente. L'administrateur voit toutes les factures ;
 * un vendeur voit celles de ses propres ventes.
 */
class FactureController extends Controller
{
    public function facture(Request $request, Vente $vente)
    {
        if ($request->user()->role === Role::Vendeur && $vente->vendeur_id !== $request->user()->id) {
            abort(403);
        }

        $vente->load('lignes.produit:id,nom', 'client:id,nom,prenom,telephone', 'vendeur:id,name,prenom');

        return response()->json([
            'reference' => $vente->reference,
            'date' => $vente->created_at->toDateTimeString(),
            'statut' => $vente->statut->value,
            'mode_paiement' => $vente->mode_paiement->value,
            'client' => $vente->client ? trim($vente->client->prenom.' '.$vente->client->nom) : 'Client',
            'client_tel' => $vente->client?->telephone,
            'vendeur' => $vente->vendeur ? trim($vente->vendeur->prenom.' '.$vente->vendeur->name) : null,
            'montant_total' => $vente->montant_total,
            'montant_paye' => $vente->montant_paye,
            'reste' => $vente->reste(),
            'boutique' => Boutique::entete(),
            'lignes' => $vente->lignes->map(fn ($l) => [
                'designation' => $l->produit?->nom ?? 'Article',
                'quantite' => $l->quantite,
                'prix_unitaire' => $l->prix_unitaire,
                'sous_total' => $l->sous_total,
            ]),
        ]);
    }
}
