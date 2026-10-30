<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModePaiement;
use App\Enums\StatutVente;
use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Models\MouvementCaisse;
use App\Models\Vente;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Gestion de caisse (espèces) : encaissements des ventes, apports, dépenses,
 * retraits, et le solde de la caisse. Réservé à l'administrateur.
 */
class CaisseController extends Controller
{
    /**
     * GET /api/admin/caisse?date=2026-10-30 : résumé de la caisse pour un jour.
     */
    public function jour(Request $request)
    {
        $date = $request->query('date') ? Carbon::parse($request->query('date')) : now();

        return response()->json($this->resume($date));
    }

    /**
     * POST /api/admin/caisse/mouvement
     * { "type": "depense", "montant": 50000, "description": "Achat scotch" }
     */
    public function ajouter(Request $request)
    {
        $d = $request->validate([
            'type' => ['required', Rule::in(['apport', 'depense', 'retrait'])],
            'montant' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $m = MouvementCaisse::create($d + ['user_id' => $request->user()->id]);
        JournalActivite::noter($request->user(), 'mouvement_caisse', $m, [
            'type' => $d['type'], 'montant' => $d['montant'], 'description' => $d['description'] ?? null,
        ]);

        return response()->json($this->resume(now()), 201);
    }

    /**
     * Résumé caisse d'un jour : entrées (ventes espèces + apports),
     * sorties (dépenses + retraits), solde, et la liste des mouvements manuels.
     */
    public function resume(Carbon $date): array
    {
        $debut = $date->copy()->startOfDay();
        $fin = $date->copy()->endOfDay();

        $ventesEspeces = (int) Vente::where('mode_paiement', ModePaiement::Especes)
            ->where('statut', StatutVente::Payee)
            ->whereBetween('created_at', [$debut, $fin])
            ->sum('montant_total');

        $mouv = MouvementCaisse::with('auteur:id,name,prenom')
            ->whereBetween('created_at', [$debut, $fin])
            ->latest()
            ->get();

        $apports = (int) $mouv->where('type', 'apport')->sum('montant');
        $depenses = (int) $mouv->where('type', 'depense')->sum('montant');
        $retraits = (int) $mouv->where('type', 'retrait')->sum('montant');

        return [
            'date' => $debut->toDateString(),
            'ventes_especes' => $ventesEspeces,
            'apports' => $apports,
            'depenses' => $depenses,
            'retraits' => $retraits,
            'entrees' => $ventesEspeces + $apports,
            'sorties' => $depenses + $retraits,
            'solde' => $ventesEspeces + $apports - $depenses - $retraits,
            'mouvements' => $mouv->map(fn (MouvementCaisse $m) => [
                'type' => $m->type,
                'montant' => $m->montant,
                'description' => $m->description,
                'par' => $m->auteur ? trim($m->auteur->prenom.' '.$m->auteur->name) : '',
                'date' => $m->created_at->toDateTimeString(),
            ]),
        ];
    }
}
