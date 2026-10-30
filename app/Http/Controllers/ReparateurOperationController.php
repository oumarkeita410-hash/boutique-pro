<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
use App\Models\Reparateur;
use App\Services\ReparateurService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Opérations faites en boutique (vendeur ou admin) :
 * un réparateur prend un écran, ou rembourse.
 */
class ReparateurOperationController extends Controller
{
    public function __construct(private ReparateurService $reparateurs)
    {
    }

    /**
     * GET /api/reparateurs : liste simple pour choisir le réparateur.
     */
    public function index()
    {
        return Reparateur::where('actif', true)
            ->orderBy('prenom')
            ->get()
            ->map(fn (Reparateur $r) => ['id' => $r->id, 'nom' => $r->nomComplet()]);
    }

    /**
     * POST /api/reparateurs/{reparateur}/prises
     */
    public function prise(Request $request, Reparateur $reparateur)
    {
        $donnees = $request->validate([
            'produit_id' => ['required', 'integer', 'exists:produits,id'],
            'quantite' => ['required', 'integer', 'min:1'],
            'montant_paye' => ['required', 'integer', 'min:0'],
        ]);

        $prise = $this->reparateurs->enregistrerPrise(
            $request->user(),
            $reparateur,
            $donnees['produit_id'],
            $donnees['quantite'],
            $donnees['montant_paye'],
        );

        return response()->json([
            'reparateur' => $reparateur->nomComplet(),
            'article' => $prise->produit->nom,
            'quantite' => $prise->quantite,
            'montant_total' => $prise->montant_total,
            'montant_paye' => $prise->montant_paye,
            'reste' => $prise->reste(),
            'date' => $prise->created_at->toDateTimeString(),
        ], 201);
    }

    /**
     * POST /api/reparateurs/{reparateur}/paiements
     */
    public function paiement(Request $request, Reparateur $reparateur)
    {
        $donnees = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'mode_paiement' => ['required', Rule::enum(ModePaiement::class)],
        ]);

        $paiement = $this->reparateurs->enregistrerPaiement(
            $request->user(),
            $reparateur,
            $donnees['montant'],
            ModePaiement::from($donnees['mode_paiement']),
        );

        return response()->json([
            'reparateur' => $reparateur->nomComplet(),
            'montant' => $paiement->montant,
            'dette_restante' => $reparateur->calculerDette(),
        ], 201);
    }
}
