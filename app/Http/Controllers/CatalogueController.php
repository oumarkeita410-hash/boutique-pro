<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use Illuminate\Http\Request;

/**
 * Catalogue vu par les vendeurs : nom, catégorie, modèle, PRIX DE VENTE et STOCK.
 * Jamais le prix d'achat ni la marge.
 */
class CatalogueController extends Controller
{
    public function index(Request $request)
    {
        $produits = Produit::query()
            ->where('actif', true)
            ->when($request->query('categorie'), fn ($q, $c) => $q->where('categorie', $c))
            ->when($request->query('recherche'), fn ($q, $t) => $q->where(fn ($q) => $q
                ->where('nom', 'like', "%{$t}%")
                ->orWhere('modele', 'like', "%{$t}%")))
            ->orderBy('categorie')
            ->orderBy('nom')
            // Le vendeur voit le PRIX DE VENTE et le STOCK, mais jamais le prix
            // d'achat ni la marge.
            ->paginate(100, ['id', 'nom', 'categorie', 'modele', 'prix_unitaire', 'stock']);

        return response()->json($produits);
    }
}
