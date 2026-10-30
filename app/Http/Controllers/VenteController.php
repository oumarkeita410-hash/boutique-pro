<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\StatutVente;
use App\Http\Requests\StoreVenteRequest;
use App\Http\Resources\VenteVendeurResource;
use App\Models\Vente;
use App\Services\VenteService;
use Illuminate\Http\Request;

class VenteController extends Controller
{
    public function __construct(private VenteService $ventes)
    {
    }

    /**
     * GET /api/mes-ventes : les ventes du vendeur connecté, avec l'argent payé
     * par les clients et l'argent qui reste à payer. (Le vendeur ne voit que les siennes.)
     */
    public function mesVentes(Request $request)
    {
        $ventes = Vente::where('vendeur_id', $request->user()->id)
            ->with(['lignes.produit:id,nom', 'paiements' => fn ($q) => $q->latest()])
            ->latest()
            ->paginate(40);

        // Totaux (toutes les ventes du vendeur, pas seulement la page affichée).
        $base = Vente::where('vendeur_id', $request->user()->id)->where('statut', '!=', StatutVente::Annulee);
        $totalPaye = (int) (clone $base)->sum('montant_paye');
        $totalReste = (int) (clone $base)->sum(\Illuminate\Support\Facades\DB::raw('montant_total - montant_paye'));

        $ventes->getCollection()->transform(fn (Vente $v) => [
            'id' => $v->id,
            'reference' => $v->reference,
            'date' => $v->created_at->toDateTimeString(),
            'montant_total' => $v->montant_total,
            'montant_paye' => $v->montant_paye,
            'reste' => $v->reste(),
            'statut' => $v->statut->value,
            'client' => $v->client_nom ?: ($v->client_id ? 'Client enregistré' : null),
            'articles' => $v->lignes->map(fn ($l) => ($l->produit?->nom ?? 'Article').' ×'.$l->quantite),
            // Historique des versements du reste (tous les mouvements).
            'paiements' => $v->paiements->map(fn ($p) => [
                'montant' => $p->montant,
                'mode' => $p->mode_paiement,
                'date' => $p->created_at->toDateTimeString(),
            ]),
        ]);

        return response()->json(['total_paye' => $totalPaye, 'total_reste' => $totalReste, 'ventes' => $ventes]);
    }

    /**
     * POST /api/mes-ventes/{vente}/encaisser  { "montant": 50000 }
     * Le vendeur enregistre un versement quand le client vient payer son reste.
     * Il ne peut encaisser que SES ventes ; on ajoute seulement (pas de modif).
     */
    public function encaisser(Request $request, Vente $vente)
    {
        if ($request->user()->role !== Role::Admin && $vente->vendeur_id !== $request->user()->id) {
            abort(403, "Cette vente n'est pas la vôtre.");
        }

        $d = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'mode_paiement' => ['nullable', 'in:especes,orange_money,carte'],
        ]);

        $vente = $this->ventes->encaisserReste($vente, $request->user(), $d['montant'], $d['mode_paiement'] ?? null);

        return response()->json([
            'message' => 'Paiement enregistré.',
            'montant_total' => $vente->montant_total,
            'montant_paye' => $vente->montant_paye,
            'reste' => $vente->reste(),
            'statut' => $vente->statut->value,
            'paiements' => $vente->paiements()->latest()->get()->map(fn ($p) => [
                'montant' => $p->montant,
                'mode' => $p->mode_paiement,
                'date' => $p->created_at->toDateTimeString(),
            ]),
        ]);
    }

    /**
     * POST /api/ventes
     */
    public function store(StoreVenteRequest $request)
    {
        $vente = $this->ventes->vendre(
            $request->user(),
            $request->validated('lignes'),
            ModePaiement::from($request->validated('mode_paiement')),
            $request->validated('client_id'),
            $request->validated('uuid_client'),
            [
                'latitude' => $request->validated('latitude'),
                'longitude' => $request->validated('longitude'),
                'precision_m' => $request->validated('precision_m'),
                'ville' => $request->validated('ville'),
                'ip' => $request->ip(),
            ],
            $request->validated('client_nom'),
            $request->validated('client_tel'),
            $request->validated('montant_paye'),
        );

        return (new VenteVendeurResource($vente))
            ->response()
            ->setStatusCode($vente->wasRecentlyCreated ? 201 : 200);
    }
}
