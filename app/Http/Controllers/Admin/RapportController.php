<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutVente;
use App\Http\Controllers\Controller;
use App\Models\Vente;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rapport de fin de journée pour l'administrateur : tout ce qui s'est passé
 * dans la journée en un coup d'œil (ventes, encaissements, crédits, caisse).
 */
class RapportController extends Controller
{
    public function __construct(private CaisseController $caisse)
    {
    }

    /**
     * GET /api/admin/rapport-jour?date=2026-10-30
     */
    public function jour(Request $request)
    {
        $date = $request->query('date') ? Carbon::parse($request->query('date')) : now();
        $debut = $date->copy()->startOfDay();
        $fin = $date->copy()->endOfDay();

        // Ventes de la journée (hors ventes annulées).
        // Colonnes qualifiées (ventes.*) car certaines requêtes joignent users.
        $ventes = Vente::whereBetween('ventes.created_at', [$debut, $fin])
            ->where('ventes.statut', '!=', StatutVente::Annulee);

        $nombre = (clone $ventes)->count();
        $totalVendu = (int) (clone $ventes)->sum('montant_total');
        $totalEncaisse = (int) (clone $ventes)->sum('montant_paye');
        $totalReste = (int) (clone $ventes)->sum(DB::raw('montant_total - montant_paye'));
        $annulees = (int) Vente::whereBetween('created_at', [$debut, $fin])->where('statut', StatutVente::Annulee)->count();

        // Encaissé par mode de paiement.
        $parMode = (clone $ventes)
            ->select('mode_paiement', DB::raw('COUNT(*) as nombre'), DB::raw('SUM(montant_paye) as encaisse'), DB::raw('SUM(montant_total) as total'))
            ->groupBy('mode_paiement')
            ->get()
            ->map(fn ($r) => [
                'mode' => $r->mode_paiement->value,
                'nombre' => (int) $r->nombre,
                'encaisse' => (int) $r->encaisse,
                'total' => (int) $r->total,
            ]);

        // Crédits accordés aujourd'hui (ventes qui restent à payer).
        $creditsNombre = (int) (clone $ventes)->where('statut', StatutVente::ACredit)->count();

        // Top produits vendus (par quantité) de la journée.
        $topProduits = DB::table('lignes_vente')
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->join('produits', 'produits.id', '=', 'lignes_vente.produit_id')
            ->whereBetween('ventes.created_at', [$debut, $fin])
            ->where('ventes.statut', '!=', StatutVente::Annulee->value)
            ->groupBy('produits.id', 'produits.nom')
            ->select('produits.nom', DB::raw('SUM(lignes_vente.quantite) as quantite'), DB::raw('SUM(lignes_vente.sous_total) as montant'))
            ->orderByDesc('quantite')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['nom' => $r->nom, 'quantite' => (int) $r->quantite, 'montant' => (int) $r->montant]);

        // Ventes par vendeur.
        $parVendeur = (clone $ventes)
            ->join('users', 'users.id', '=', 'ventes.vendeur_id')
            ->groupBy('users.id', 'users.name', 'users.prenom')
            ->select('users.name', 'users.prenom', DB::raw('COUNT(*) as nombre'), DB::raw('SUM(ventes.montant_total) as total'))
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['vendeur' => trim(($r->prenom ?? '').' '.($r->name ?? '')), 'nombre' => (int) $r->nombre, 'total' => (int) $r->total]);

        return response()->json([
            'date' => $debut->toDateString(),
            'ventes' => [
                'nombre' => $nombre,
                'annulees' => $annulees,
                'total_vendu' => $totalVendu,
                'total_encaisse' => $totalEncaisse,
                'total_reste' => $totalReste,
                'credits_accordes' => $creditsNombre,
            ],
            'par_mode' => $parMode,
            'top_produits' => $topProduits,
            'par_vendeur' => $parVendeur,
            'caisse' => $this->caisse->resume($date),
        ]);
    }
}
