<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
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
            ->with('lignes.produit:id,nom')
            ->latest()
            ->paginate(40);

        // Totaux (toutes les ventes du vendeur, pas seulement la page affichée).
        $base = Vente::where('vendeur_id', $request->user()->id)->where('statut', '!=', StatutVente::Annulee);
        $totalPaye = (int) (clone $base)->sum('montant_paye');
        $totalReste = (int) (clone $base)->sum(\Illuminate\Support\Facades\DB::raw('montant_total - montant_paye'));

        $ventes->getCollection()->transform(fn (Vente $v) => [
            'reference' => $v->reference,
            'date' => $v->created_at->toDateTimeString(),
            'montant_total' => $v->montant_total,
            'montant_paye' => $v->montant_paye,
            'reste' => $v->reste(),
            'statut' => $v->statut->value,
            'client' => $v->client_nom ?: ($v->client_id ? 'Client enregistré' : null),
            'articles' => $v->lignes->map(fn ($l) => ($l->produit?->nom ?? 'Article').' ×'.$l->quantite),
        ]);

        return response()->json(['total_paye' => $totalPaye, 'total_reste' => $totalReste, 'ventes' => $ventes]);
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
