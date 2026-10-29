<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Models\PriseReparateur;
use App\Models\Produit;
use App\Models\Reparateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReparateurController extends Controller
{
    /**
     * GET /api/admin/reparateurs?en_alerte=1
     * en_alerte=1 : seulement ceux qui doivent plus que le seuil (500 000 GNF).
     */
    public function index(Request $request)
    {
        $seuil = config('boutique.seuil_dette_reparateur');

        $reparateurs = Reparateur::query()
            ->avecDette()
            ->when($request->boolean('en_alerte'), fn ($q) => $q->where(Reparateur::sousRequeteDette(), '>', $seuil))
            ->orderByDesc('dette')
            ->get()
            ->map(fn (Reparateur $r) => [
                'id' => $r->id,
                'nom' => $r->nomComplet(),
                'telephone' => $r->telephone,
                'email' => $r->email,
                'actif' => $r->actif,
                'dette' => (int) $r->dette,
                'en_alerte' => $r->dette > $seuil,
            ]);

        return response()->json(['seuil' => $seuil, 'reparateurs' => $reparateurs]);
    }

    /**
     * Détail : chaque écran pris (modèle, payé, reste) et chaque remboursement.
     */
    public function show(Reparateur $reparateur)
    {
        return response()->json([
            'id' => $reparateur->id,
            'nom' => $reparateur->nomComplet(),
            'telephone' => $reparateur->telephone,
            'email' => $reparateur->email,
            'dette' => $reparateur->calculerDette(),
            'prises' => $reparateur->prises()->with('produit:id,nom,modele,categorie')->latest()->limit(200)->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'date' => $p->created_at->toDateTimeString(),
                    'article' => $p->designation(),
                    'modele' => $p->modele ?: $p->produit?->modele,
                    'marque' => $p->marque,
                    'categorie' => $p->categorie ?: $p->produit?->categorie,
                    'prix_unitaire' => $p->prix_unitaire,
                    'quantite' => $p->quantite,
                    'montant_total' => $p->montant_total,
                    'montant_paye' => $p->montant_paye,
                    'reste' => $p->reste(),
                    'manuelle' => $p->produit_id === null,
                ]),
            'paiements' => $reparateur->paiements()->latest()->limit(200)->get(['montant', 'mode_paiement', 'created_at']),
        ]);
    }

    public function store(Request $request)
    {
        $reparateur = Reparateur::create($request->validate($this->regles()));
        JournalActivite::noter($request->user(), 'reparateur_cree', $reparateur);

        return response()->json($reparateur, 201);
    }

    public function update(Request $request, Reparateur $reparateur)
    {
        $reparateur->update($request->validate($this->regles(true)));
        JournalActivite::noter($request->user(), 'reparateur_modifie', $reparateur);

        return response()->json($reparateur);
    }

    /**
     * Ajoute une ligne de dette saisie à la main (modèle, marque, prix, quantité, catégorie).
     * POST /api/admin/reparateurs/{reparateur}/dette
     */
    public function ajouterDette(Request $request, Reparateur $reparateur)
    {
        $d = $this->validerDette($request);
        $total = $d['prix'] * $d['quantite'];

        $prise = $reparateur->prises()->create([
            'produit_id' => null,
            'enregistre_par' => $request->user()->id,
            'modele' => $d['modele'],
            'marque' => $d['marque'],
            'categorie' => $d['categorie'],
            'quantite' => $d['quantite'],
            'prix_unitaire' => $d['prix'],
            'montant_total' => $total,
            'montant_paye' => min($d['montant_paye'], $total),
        ]);

        JournalActivite::noter($request->user(), 'dette_reparateur_ajoutee', $reparateur, [
            'modele' => $d['modele'], 'montant_total' => $total, 'montant_paye' => $d['montant_paye'],
        ]);

        return response()->json(['message' => 'Dette ajoutée.', 'reste' => $prise->reste(), 'dette' => $reparateur->calculerDette()], 201);
    }

    /**
     * Modifie une ligne de dette. PUT /api/admin/prises/{prise}
     * Si la prise vient du catalogue, le stock est ajusté selon le changement de quantité.
     */
    public function modifierDette(Request $request, PriseReparateur $prise)
    {
        $d = $this->validerDette($request);
        $total = $d['prix'] * $d['quantite'];

        DB::transaction(function () use ($prise, $d, $total, $request) {
            // Ajuste le stock si l'article vient du catalogue (différence de quantité).
            if ($prise->produit_id) {
                $produit = Produit::whereKey($prise->produit_id)->lockForUpdate()->first();
                if ($produit) {
                    $delta = $d['quantite'] - $prise->quantite; // positif = on prend plus
                    if ($delta !== 0) {
                        $produit->decrement('stock', $delta); // delta négatif = on rend au stock
                    }
                }
            }

            $prise->update([
                'modele' => $d['modele'],
                'marque' => $d['marque'],
                'categorie' => $d['categorie'],
                'quantite' => $d['quantite'],
                'prix_unitaire' => $d['prix'],
                'montant_total' => $total,
                'montant_paye' => min($d['montant_paye'], $total),
            ]);

            JournalActivite::noter($request->user(), 'dette_reparateur_modifiee', $prise->reparateur, [
                'prise_id' => $prise->id, 'montant_total' => $total, 'montant_paye' => $d['montant_paye'],
            ]);
        });

        return response()->json(['message' => 'Dette modifiée.', 'reste' => $prise->fresh()->reste(), 'dette' => $prise->reparateur->calculerDette()]);
    }

    /**
     * Supprime une ligne de dette. DELETE /api/admin/prises/{prise}
     * Rend l'article au stock si elle venait du catalogue.
     */
    public function supprimerDette(Request $request, PriseReparateur $prise)
    {
        $reparateur = $prise->reparateur;

        DB::transaction(function () use ($prise, $request) {
            if ($prise->produit_id) {
                Produit::whereKey($prise->produit_id)->update(['stock' => DB::raw('stock + '.(int) $prise->quantite)]);
            }
            JournalActivite::noter($request->user(), 'dette_reparateur_supprimee', $prise->reparateur, ['prise_id' => $prise->id]);
            $prise->delete();
        });

        return response()->json(['message' => 'Dette supprimée.', 'dette' => $reparateur->calculerDette()]);
    }

    private function validerDette(Request $request): array
    {
        return $request->validate([
            'modele' => ['required', 'string', 'max:150'],
            'marque' => ['nullable', 'string', 'max:100'],
            'categorie' => ['nullable', 'string', 'max:100'],
            'prix' => ['required', 'integer', 'min:0'],
            'quantite' => ['required', 'integer', 'min:1', 'max:1000'],
            'montant_paye' => ['required', 'integer', 'min:0'],
        ]);
    }

    private function regles(bool $modification = false): array
    {
        $requis = $modification ? 'sometimes' : 'required';

        return [
            'prenom' => [$requis, 'string', 'max:100'],
            'nom' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
