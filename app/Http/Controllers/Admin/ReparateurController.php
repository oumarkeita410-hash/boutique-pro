<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Models\Reparateur;
use Illuminate\Http\Request;

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
            'prises' => $reparateur->prises()->with('produit:id,nom,modele')->latest()->limit(200)->get()
                ->map(fn ($p) => [
                    'date' => $p->created_at->toDateTimeString(),
                    'article' => $p->produit->nom,
                    'modele' => $p->produit->modele,
                    'quantite' => $p->quantite,
                    'montant_total' => $p->montant_total,
                    'montant_paye' => $p->montant_paye,
                    'reste' => $p->reste(),
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
