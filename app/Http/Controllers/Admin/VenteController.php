<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutVente;
use App\Http\Controllers\Controller;
use App\Models\Vente;
use Illuminate\Http\Request;

/**
 * L'administrateur voit TOUTES les ventes (tous les vendeurs), avec filtres.
 */
class VenteController extends Controller
{
    /**
     * GET /api/admin/ventes?statut=&vendeur_id=&periode=jour&recherche=
     */
    public function index(Request $request)
    {
        $ventes = Vente::query()
            ->when($request->query('statut'), fn ($q, $s) => $q->where('statut', $s))
            ->when($request->query('vendeur_id'), fn ($q, $v) => $q->where('vendeur_id', $v))
            ->when($request->query('recherche'), fn ($q, $r) => $q->where('reference', 'like', "%{$r}%"))
            ->when($request->query('periode') === 'jour', fn ($q) => $q->where('created_at', '>=', now()->startOfDay()))
            ->when($request->query('periode') === 'semaine', fn ($q) => $q->where('created_at', '>=', now()->startOfWeek()))
            ->when($request->query('periode') === 'mois', fn ($q) => $q->where('created_at', '>=', now()->startOfMonth()))
            ->with(['vendeur:id,name,prenom', 'client:id,nom,prenom,telephone', 'lignes.produit:id,nom'])
            ->latest()
            ->paginate(40);

        $ventes->getCollection()->transform(fn (Vente $v) => [
            'id' => $v->id,
            'reference' => $v->reference,
            'date' => $v->created_at->toDateTimeString(),
            'vendeur' => $v->vendeur ? trim($v->vendeur->prenom.' '.$v->vendeur->name) : null,
            'client' => $v->client ? trim($v->client->prenom.' '.$v->client->nom) : null,
            'client_tel' => $v->client?->telephone,
            'mode_paiement' => $v->mode_paiement->value,
            'statut' => $v->statut->value,
            'montant_total' => $v->montant_total,
            'montant_paye' => $v->montant_paye,
            'reste' => $v->reste(),
            'reference_paiement' => $v->reference_paiement,
            'articles' => $v->lignes->map(fn ($l) => $l->produit->nom.' ×'.$l->quantite),
            'latitude' => $v->latitude,
            'longitude' => $v->longitude,
        ]);

        return response()->json($ventes);
    }

    /**
     * GET /api/admin/ventes-resume : compteurs rapides par statut.
     */
    public function resume()
    {
        return response()->json([
            'aujourdhui' => Vente::where('created_at', '>=', now()->startOfDay())->count(),
            'a_credit' => Vente::where('statut', StatutVente::ACredit)->count(),
            'en_attente' => Vente::where('statut', StatutVente::EnAttentePaiement)->count(),
        ]);
    }
}
