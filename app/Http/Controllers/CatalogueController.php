<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use Illuminate\Http\Request;

/**
 * Catalogue vu par les vendeurs : nom, catégorie et modèle seulement.
 * Ni stock, ni prix d'achat, ni prix unitaire.
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
            ->paginate(100, ['id', 'nom', 'categorie', 'modele']);

        return response()->json($produits);
    }
}
