<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutVente;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LigneVente;
use App\Enums\ModePaiement;
use App\Models\Message;
use App\Models\MouvementCaisse;
use App\Models\MouvementStock;
use App\Models\PriseReparateur;
use App\Models\Produit;
use App\Models\Reparateur;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/admin/tableau-de-bord : tous les chiffres de l'écran d'accueil administrateur.
 */
class TableauDeBordController extends Controller
{
    public function __invoke()
    {
        $aujourdhui = now()->startOfDay();
        $semaine = now()->startOfWeek();
        $mois = now()->startOfMonth();
        $seuilDette = config('boutique.seuil_dette_reparateur');

        $payees = fn () => Vente::where('ventes.statut', StatutVente::Payee);
        $ca = fn ($depuis) => (int) $payees()->where('ventes.created_at', '>=', $depuis)->sum('montant_total');

        return response()->json([
            'chiffre_affaires' => [
                'aujourdhui' => $ca($aujourdhui),
                'semaine' => $ca($semaine),
                'mois' => $ca($mois),
            ],
            'ventes_aujourdhui' => $payees()->where('created_at', '>=', $aujourdhui)->count(),
            'paiements_en_attente' => Vente::where('statut', StatutVente::EnAttentePaiement)->count(),

            // Caisse du jour (espèces) : encaissé + apports − dépenses − retraits = solde.
            'caisse' => (function () use ($aujourdhui) {
                $especes = (int) Vente::where('mode_paiement', ModePaiement::Especes)
                    ->where('statut', '!=', StatutVente::Annulee)->where('created_at', '>=', $aujourdhui)->sum('montant_paye');
                $mouv = MouvementCaisse::where('created_at', '>=', $aujourdhui)->get();
                $apports = (int) $mouv->where('type', 'apport')->sum('montant');
                $depenses = (int) $mouv->where('type', 'depense')->sum('montant');
                $retraits = (int) $mouv->where('type', 'retrait')->sum('montant');
                return [
                    'especes_ventes' => $especes,
                    'depenses' => $depenses,
                    'solde' => $especes + $apports - $depenses - $retraits,
                ];
            })(),

            'marge_mois' => (int) LigneVente::join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
                ->where('ventes.statut', StatutVente::Payee)
                ->where('ventes.created_at', '>=', $mois)
                ->sum(DB::raw('lignes_vente.sous_total - lignes_vente.prix_achat * lignes_vente.quantite')),

            'ventes_par_jour' => $payees()
                ->where('created_at', '>=', now()->subDays(29)->startOfDay())
                ->selectRaw('DATE(created_at) as jour, COUNT(*) as nombre, SUM(montant_total) as montant')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('jour')
                ->get(),

            'top_vendeurs_mois' => $payees()
                ->where('created_at', '>=', $mois)
                ->selectRaw('vendeur_id, COUNT(*) as nombre, SUM(montant_total) as montant')
                ->groupBy('vendeur_id')
                ->orderByDesc('montant')
                ->limit(10)
                ->with('vendeur:id,name,prenom,region')
                ->get(),

            'top_produits_mois' => LigneVente::join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
                ->where('ventes.statut', StatutVente::Payee)
                ->where('ventes.created_at', '>=', $mois)
                ->selectRaw('lignes_vente.produit_id, SUM(lignes_vente.quantite) as quantite, SUM(lignes_vente.sous_total) as montant')
                ->groupBy('lignes_vente.produit_id')
                ->orderByDesc('quantite')
                ->limit(10)
                ->with('produit:id,nom,categorie')
                ->get(),

            'ca_par_region_mois' => $payees()
                ->join('users', 'users.id', '=', 'ventes.vendeur_id')
                ->where('ventes.created_at', '>=', $mois)
                ->selectRaw("COALESCE(users.region, 'Non définie') as region, COUNT(*) as nombre, SUM(ventes.montant_total) as montant")
                ->groupByRaw("COALESCE(users.region, 'Non définie')")
                ->orderByDesc('montant')
                ->get(),

            'stock' => [
                'produits_actifs' => Produit::where('actif', true)->count(),
                'en_rupture' => Produit::where('actif', true)->where('stock', 0)->count(),
                'stock_bas' => Produit::where('actif', true)->whereColumn('stock', '<=', 'seuil_alerte')->count(),
                'valeur_achat' => (int) Produit::sum(DB::raw('stock * prix_achat')),
                'a_reapprovisionner' => Produit::where('actif', true)
                    ->whereColumn('stock', '<=', 'seuil_alerte')
                    ->orderBy('stock')
                    ->limit(20)
                    ->get(['id', 'nom', 'stock', 'seuil_alerte']),
            ],

            // Mouvements de stock : entrées/sorties du jour + les plus récents.
            'mouvements_stock' => (function () use ($aujourdhui) {
                $jour = MouvementStock::where('created_at', '>=', $aujourdhui)->get(['type', 'quantite']);

                return [
                    'entrees_jour' => (int) $jour->where('quantite', '>', 0)->sum('quantite'),
                    'sorties_jour' => (int) abs($jour->where('quantite', '<', 0)->sum('quantite')),
                    'recents' => MouvementStock::with(['produit:id,nom', 'utilisateur:id,name,prenom'])
                        ->latest()
                        ->limit(15)
                        ->get()
                        ->map(fn (MouvementStock $m) => [
                            'type' => $m->type,
                            'produit' => $m->produit?->nom,
                            'quantite' => (int) $m->quantite,
                            'stock_apres' => (int) $m->stock_apres,
                            'par' => $m->utilisateur ? trim($m->utilisateur->prenom.' '.$m->utilisateur->name) : null,
                            'date' => $m->created_at->toDateTimeString(),
                        ]),
                ];
            })(),

            'reparateurs' => [
                'dette_totale' => (int) PriseReparateur::sum(DB::raw('montant_total - montant_paye')),
                'en_alerte' => Reparateur::where(Reparateur::sousRequeteDette(), '>', $seuilDette)->count(),
            ],

            'comptes' => [
                'par_role' => User::selectRaw('role, COUNT(*) as nombre')->groupBy('role')->pluck('nombre', 'role'),
                'verrouilles' => User::where('est_verrouille', true)->count(),
                'connectes_aujourdhui' => User::where('derniere_connexion', '>=', $aujourdhui)->count(),
            ],

            'clients' => [
                'total' => Client::count(),
                'nouveaux_mois' => Client::where('created_at', '>=', $mois)->count(),
            ],

            'messages_non_lus' => Message::whereNull('lu_le')->whereColumn('auteur_id', 'user_id')->count(),
        ]);
    }
}
