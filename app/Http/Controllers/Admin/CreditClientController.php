<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModePaiement;
use App\Enums\StatutVente;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\JournalActivite;
use App\Models\Vente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crédits clients : ventes faites à crédit (autorisées par l'administrateur),
 * et remboursements. Tout est réservé à l'administrateur.
 */
class CreditClientController extends Controller
{
    /**
     * GET /api/admin/credits : clients qui doivent de l'argent, avec le total dû.
     */
    public function index()
    {
        $clients = Client::query()
            ->whereHas('ventes', fn ($q) => $q->where('mode_paiement', ModePaiement::Credit)->where('statut', StatutVente::ACredit))
            ->withSum(['ventes as dette' => fn ($q) => $q
                ->where('mode_paiement', ModePaiement::Credit)
                ->where('statut', StatutVente::ACredit)], DB::raw('montant_total - montant_paye'))
            ->orderByDesc('dette')
            ->get();

        return response()->json($clients->map(fn (Client $c) => [
            'client_id' => $c->id,
            'nom' => trim($c->prenom.' '.$c->nom),
            'telephone' => $c->telephone,
            'ville' => $c->ville,
            'dette' => (int) $c->dette,
        ]));
    }

    /**
     * GET /api/admin/credits/{client} : détail des ventes à crédit du client.
     */
    public function show(Client $client)
    {
        $ventes = Vente::where('client_id', $client->id)
            ->where('mode_paiement', ModePaiement::Credit)
            ->with('lignes.produit:id,nom')
            ->latest()
            ->get();

        return response()->json([
            'client' => ['id' => $client->id, 'nom' => trim($client->prenom.' '.$client->nom), 'telephone' => $client->telephone],
            'dette' => (int) $ventes->where('statut', StatutVente::ACredit)->sum(fn ($v) => $v->reste()),
            'ventes' => $ventes->map(fn (Vente $v) => [
                'reference' => $v->reference,
                'date' => $v->created_at->toDateTimeString(),
                'montant_total' => $v->montant_total,
                'montant_paye' => $v->montant_paye,
                'reste' => $v->reste(),
                'statut' => $v->statut->value,
                'articles' => $v->lignes->map(fn ($l) => $l->produit->nom.' ×'.$l->quantite),
            ]),
        ]);
    }

    /**
     * POST /api/admin/credits/{client}/remboursement  { montant, mode_paiement }
     * Le montant règle les ventes à crédit les plus anciennes d'abord.
     */
    public function rembourser(Request $request, Client $client)
    {
        $donnees = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'mode_paiement' => ['required', Rule::in(['especes', 'orange_money', 'carte'])],
        ]);

        return DB::transaction(function () use ($request, $client, $donnees) {
            $ventes = Vente::where('client_id', $client->id)
                ->where('mode_paiement', ModePaiement::Credit)
                ->where('statut', StatutVente::ACredit)
                ->orderBy('created_at')->orderBy('id')
                ->lockForUpdate()
                ->get();

            $dette = $ventes->sum(fn ($v) => $v->reste());

            if ($donnees['montant'] > $dette) {
                throw ValidationException::withMessages([
                    'montant' => "Le montant dépasse la dette du client ({$dette} GNF).",
                ]);
            }

            $restant = $donnees['montant'];
            foreach ($ventes as $vente) {
                if ($restant <= 0) break;
                $part = min($restant, $vente->reste());
                $vente->montant_paye += $part;
                if ($vente->reste() === 0) {
                    $vente->statut = StatutVente::Payee; // entièrement remboursée
                }
                $vente->save();
                $restant -= $part;
            }

            JournalActivite::noter($request->user(), 'remboursement_client', $client, [
                'montant' => $donnees['montant'],
                'mode' => $donnees['mode_paiement'],
            ]);

            return response()->json([
                'message' => 'Remboursement enregistré.',
                'dette_restante' => $dette - $donnees['montant'],
            ]);
        });
    }
}
