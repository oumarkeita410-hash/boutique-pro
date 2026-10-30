<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutVente;
use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Vente;
use App\Services\VenteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * L'administrateur voit TOUTES les ventes (tous les vendeurs), avec filtres.
 */
class VenteController extends Controller
{
    public function __construct(private VenteService $ventes)
    {
    }

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

    /**
     * GET /api/admin/ventes/export?periode=mois : télécharge les ventes en CSV (ouvert par Excel).
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Vente::query()
            ->when($request->query('periode') === 'jour', fn ($q) => $q->where('created_at', '>=', now()->startOfDay()))
            ->when($request->query('periode') === 'semaine', fn ($q) => $q->where('created_at', '>=', now()->startOfWeek()))
            ->when($request->query('periode') === 'mois', fn ($q) => $q->where('created_at', '>=', now()->startOfMonth()))
            ->with(['vendeur:id,name,prenom', 'client:id,nom,prenom'])
            ->latest();

        $nom = 'ventes-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
            fputcsv($out, ['Référence', 'Date', 'Vendeur', 'Client', 'Mode', 'Statut', 'Montant total', 'Payé', 'Reste'], ';');
            $query->chunk(500, function ($ventes) use ($out) {
                foreach ($ventes as $v) {
                    fputcsv($out, [
                        $v->reference,
                        $v->created_at->toDateTimeString(),
                        $v->vendeur ? trim($v->vendeur->prenom.' '.$v->vendeur->name) : '',
                        $v->client ? trim($v->client->prenom.' '.$v->client->nom) : '',
                        $v->mode_paiement->value,
                        $v->statut->value,
                        $v->montant_total,
                        $v->montant_paye,
                        $v->reste(),
                    ], ';');
                }
            });
            fclose($out);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * POST /api/admin/ventes/{vente}/encaisser
     * { "montant": 60000, "mode_paiement": "especes" }
     * Enregistre un paiement sur une vente à crédit : le reste à payer diminue.
     */
    public function encaisser(Request $request, Vente $vente)
    {
        $d = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'mode_paiement' => ['nullable', 'in:especes,orange_money,carte'],
        ]);

        $vente = $this->ventes->encaisserReste($vente, $request->user(), $d['montant'], $d['mode_paiement'] ?? null);

        return response()->json([
            'message' => 'Paiement enregistré.',
            'montant_paye' => $vente->montant_paye,
            'reste' => $vente->reste(),
            'statut' => $vente->statut->value,
        ]);
    }

    /**
     * POST /api/admin/ventes/{vente}/annuler : annule une vente (retour).
     * Remet les articles en stock et marque la vente « annulée ».
     */
    public function annuler(Request $request, Vente $vente)
    {
        if ($vente->statut === StatutVente::Annulee) {
            throw ValidationException::withMessages(['vente' => 'Cette vente est déjà annulée.']);
        }

        $motif = $request->validate(['motif' => ['nullable', 'string', 'max:255']])['motif'] ?? null;

        DB::transaction(function () use ($vente, $request, $motif) {
            foreach ($vente->lignes()->get() as $ligne) {
                $produit = Produit::whereKey($ligne->produit_id)->lockForUpdate()->first();
                if ($produit) {
                    $avant = $produit->stock;
                    $produit->increment('stock', $ligne->quantite);
                    MouvementStock::create([
                        'produit_id' => $produit->id,
                        'user_id' => $request->user()->id,
                        'vente_id' => $vente->id,
                        'type' => 'annulation',
                        'quantite' => $ligne->quantite, // positif = retour au stock
                        'stock_avant' => $avant,
                        'stock_apres' => $produit->stock,
                    ]);
                }
            }

            $vente->update(['statut' => StatutVente::Annulee]);
            JournalActivite::noter($request->user(), 'vente_annulee', $vente, ['reference' => $vente->reference, 'motif' => $motif]);
        });

        return response()->json(['message' => 'Vente annulée, articles remis en stock.', 'statut' => $vente->statut->value]);
    }
}
