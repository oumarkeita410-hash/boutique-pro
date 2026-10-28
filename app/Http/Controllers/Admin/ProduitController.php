<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Models\MouvementStock;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Produits, prix et stock : visibles et modifiables par l'administrateur seul.
 * Le stock ne change que par une vente, une prise réparateur ou un approvisionnement,
 * pour que chaque mouvement soit tracé.
 */
class ProduitController extends Controller
{
    /**
     * GET /api/admin/produits?categorie=…&recherche=…&stock_bas=1&inactifs=1
     */
    public function index(Request $request)
    {
        $produits = Produit::query()
            ->when($request->query('categorie'), fn ($q, $c) => $q->where('categorie', $c))
            ->when($request->query('recherche'), fn ($q, $t) => $q->where(fn ($q) => $q
                ->where('nom', 'like', "%{$t}%")
                ->orWhere('modele', 'like', "%{$t}%")))
            ->when($request->boolean('stock_bas'), fn ($q) => $q->whereColumn('stock', '<=', 'seuil_alerte'))
            ->when($request->boolean('inactifs'), fn ($q) => $q->where('actif', false))
            ->orderBy('categorie')
            ->orderBy('nom')
            ->paginate(100);

        return response()->json($produits);
    }

    public function show(Produit $produit)
    {
        return response()->json($produit);
    }

    public function store(Request $request)
    {
        $produit = Produit::create($request->validate($this->regles()));
        JournalActivite::noter($request->user(), 'produit_cree', $produit);

        return response()->json($produit, 201);
    }

    public function update(Request $request, Produit $produit)
    {
        $produit->fill($request->validate($this->regles($produit)));
        $changements = $produit->getDirty();
        $produit->save();

        JournalActivite::noter($request->user(), 'produit_modifie', $produit, ['changements' => $changements]);

        return response()->json($produit);
    }

    /**
     * POST /api/admin/produits/{produit}/approvisionner  { "quantite": 20 }
     * Ex. réception d'une commande DL-Service (Bamako).
     */
    public function approvisionner(Request $request, Produit $produit)
    {
        $quantite = $request->validate(['quantite' => ['required', 'integer', 'min:1', 'max:100000']])['quantite'];

        $produit = DB::transaction(function () use ($request, $produit, $quantite) {
            $produit = Produit::whereKey($produit->id)->lockForUpdate()->first();
            $stockAvant = $produit->stock;
            $produit->increment('stock', $quantite);

            MouvementStock::create([
                'produit_id' => $produit->id,
                'user_id' => $request->user()->id,
                'type' => 'approvisionnement',
                'quantite' => $quantite,
                'stock_avant' => $stockAvant,
                'stock_apres' => $produit->stock,
            ]);

            return $produit;
        });

        return response()->json($produit);
    }

    private function regles(?Produit $produit = null): array
    {
        $requis = $produit ? 'sometimes' : 'required';

        return [
            // Un même nom + modèle ne peut exister qu'une fois (pas de produit en double).
            'nom' => [$requis, 'string', 'max:150', Rule::unique('produits', 'nom')
                ->where(fn ($q) => $q->where('modele', request()->input('modele')))
                ->ignore($produit?->id)],
            'categorie' => ['nullable', 'string', 'max:100'],
            'modele' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('produits', 'reference')->ignore($produit?->id)],
            'prix_achat' => ['sometimes', 'integer', 'min:0'],
            'prix_unitaire' => [$requis, 'integer', 'min:0'],
            'seuil_alerte' => ['sometimes', 'integer', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
