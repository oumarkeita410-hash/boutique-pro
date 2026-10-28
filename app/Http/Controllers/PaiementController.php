<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
use App\Enums\StatutVente;
use App\Models\Vente;
use App\Services\Paiement\OrangeMoneyService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaiementController extends Controller
{
    public function __construct(private OrangeMoneyService $orangeMoney)
    {
    }

    /**
     * POST /api/ventes/{vente}/payer-orange-money
     * Démarre le paiement et renvoie l'URL Orange Money vers laquelle
     * envoyer le client. Le vendeur ne peut payer que ses propres ventes.
     */
    public function orangeMoney(Request $request, Vente $vente)
    {
        if ($request->user()->role === \App\Enums\Role::Vendeur && $vente->vendeur_id !== $request->user()->id) {
            abort(403);
        }

        if ($vente->statut === StatutVente::Payee) {
            throw ValidationException::withMessages(['vente' => 'Cette vente est déjà payée.']);
        }

        if ($vente->mode_paiement !== ModePaiement::OrangeMoney) {
            throw ValidationException::withMessages(['vente' => 'Cette vente n\'est pas en mode Orange Money.']);
        }

        $resultat = $this->orangeMoney->initier($vente);

        return response()->json([
            'url_paiement' => $resultat['url_paiement'],
            'reference' => $resultat['transaction']->reference_interne,
            'message' => 'Ouvrez ce lien pour valider le paiement Orange Money.',
        ]);
    }

    /**
     * POST /api/webhooks/orange-money  (appelé par Orange, PAS par le navigateur)
     * Route publique : la confiance vient de la vérification côté service,
     * pas d'une session. On répond toujours 200 pour qu'Orange ne réessaie pas sans fin.
     */
    public function webhookOrangeMoney(Request $request)
    {
        $this->orangeMoney->traiterNotification($request->all());

        return response()->json(['recu' => true]);
    }

    /**
     * GET /api/ventes/{vente}/statut-paiement : le vendeur interroge l'état réel.
     */
    public function statut(Vente $vente)
    {
        return response()->json([
            'reference' => $vente->reference,
            'statut' => $vente->statut->value,
            'paye' => $vente->statut === StatutVente::Payee,
        ]);
    }

    /* ============================================================
       Orange Money SANS compte marchand (compte personnel) :
       le client paie sur le numéro de la boutique, le vendeur note la
       référence, l'ADMINISTRATEUR confirme la réception.
       ============================================================ */

    /**
     * GET /api/ventes/{vente}/instructions-paiement
     * Numéro à faire payer et montant.
     */
    public function instructions(Request $request, Vente $vente)
    {
        if ($request->user()->role === \App\Enums\Role::Vendeur && $vente->vendeur_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json([
            'reference' => $vente->reference,
            'montant' => $vente->montant_total,
            'mode' => $vente->mode_paiement->value,
            'numero_orange_money' => config('boutique.orange_money.numero'),
            'statut' => $vente->statut->value,
            'instruction' => 'Faites payer le client sur Orange Money au '
                .config('boutique.orange_money.numero').', puis saisissez la référence de la transaction.',
        ]);
    }

    /**
     * POST /api/ventes/{vente}/reference-paiement  { "reference_paiement": "OM123..." }
     * Le vendeur enregistre la référence donnée par le client. Ne confirme PAS
     * le paiement : seul l'administrateur confirme (il vérifie sur son compte).
     */
    public function enregistrerReference(Request $request, Vente $vente)
    {
        if ($request->user()->role === \App\Enums\Role::Vendeur && $vente->vendeur_id !== $request->user()->id) {
            abort(403);
        }

        $donnees = $request->validate(['reference_paiement' => ['required', 'string', 'max:100']]);
        $vente->update(['reference_paiement' => $donnees['reference_paiement']]);

        return response()->json(['message' => 'Référence enregistrée. En attente de confirmation par l\'administrateur.']);
    }

    /**
     * Admin : GET /api/admin/paiements-a-confirmer
     */
    public function aConfirmer()
    {
        return Vente::where('statut', StatutVente::EnAttentePaiement)
            ->with('vendeur:id,name,prenom')
            ->latest()
            ->paginate(50, ['id', 'reference', 'vendeur_id', 'mode_paiement', 'montant_total', 'reference_paiement', 'created_at']);
    }

    /**
     * Admin : POST /api/admin/ventes/{vente}/confirmer-paiement
     * { "reference_paiement": "OM123..." }  (facultatif si déjà saisie)
     * L'administrateur a vérifié sur son compte → la vente passe à « payée ».
     */
    public function confirmer(Request $request, Vente $vente)
    {
        if ($vente->statut === StatutVente::Payee) {
            throw ValidationException::withMessages(['vente' => 'Cette vente est déjà payée.']);
        }

        $ref = $request->validate(['reference_paiement' => ['nullable', 'string', 'max:100']])['reference_paiement'] ?? null;

        $vente->update([
            'statut' => StatutVente::Payee,
            'reference_paiement' => $ref ?: $vente->reference_paiement,
        ]);

        \App\Models\JournalActivite::noter($request->user(), 'paiement_confirme', $vente, [
            'reference' => $vente->reference,
            'mode' => $vente->mode_paiement->value,
            'montant' => $vente->montant_total,
            'reference_paiement' => $vente->reference_paiement,
        ]);

        return response()->json(['message' => 'Paiement confirmé.', 'statut' => $vente->statut->value]);
    }
}
