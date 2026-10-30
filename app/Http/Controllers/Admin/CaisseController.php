<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModePaiement;
use App\Enums\StatutVente;
use App\Http\Controllers\Controller;
use App\Models\CaisseJour;
use App\Models\JournalActivite;
use App\Models\MouvementCaisse;
use App\Models\Vente;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
     * POST /api/admin/caisse/ouvrir  { "fond_ouverture": 200000 }
     * Enregistre le fond de caisse du matin (argent déjà dans le tiroir).
     */
    public function ouvrir(Request $request)
    {
        $fond = $request->validate(['fond_ouverture' => ['required', 'integer', 'min:0']])['fond_ouverture'];
        $date = now()->toDateString();

        $session = CaisseJour::firstOrNew(['date' => $date]);
        if ($session->statut === 'cloturee') {
            throw ValidationException::withMessages(['caisse' => 'La caisse du jour est déjà clôturée.']);
        }
        $session->fill(['fond_ouverture' => $fond, 'statut' => 'ouverte', 'ouvert_par' => $request->user()->id])->save();

        JournalActivite::noter($request->user(), 'caisse_ouverte', $session, ['fond_ouverture' => $fond]);

        return response()->json($this->resume(now()));
    }

    /**
     * POST /api/admin/caisse/cloturer  { "montant_reel": 345000, "note": "..." }
     * Compare l'argent réel compté au solde théorique et enregistre l'écart.
     */
    public function cloturer(Request $request)
    {
        $d = $request->validate([
            'montant_reel' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $r = $this->resume(now());
        if ($r['cloturee']) {
            throw ValidationException::withMessages(['caisse' => 'La caisse du jour est déjà clôturée.']);
        }

        $ecart = $d['montant_reel'] - $r['solde_theorique'];
        $session = CaisseJour::firstOrNew(['date' => now()->toDateString()]);
        $session->fill([
            'montant_reel' => $d['montant_reel'],
            'solde_theorique' => $r['solde_theorique'],
            'ecart' => $ecart,
            'statut' => 'cloturee',
            'cloture_par' => $request->user()->id,
            'note' => $d['note'] ?? null,
        ])->save();

        JournalActivite::noter($request->user(), 'caisse_cloturee', $session, [
            'montant_reel' => $d['montant_reel'], 'solde_theorique' => $r['solde_theorique'], 'ecart' => $ecart,
        ]);

        return response()->json($this->resume(now()));
    }

    /**
     * Résumé caisse d'un jour : entrées (ventes espèces + apports),
     * sorties (dépenses + retraits), solde, et la liste des mouvements manuels.
     */
    public function resume(Carbon $date): array
    {
        $debut = $date->copy()->startOfDay();
        $fin = $date->copy()->endOfDay();

        // Argent réellement reçu en espèces aujourd'hui (on compte le payé,
        // pas le total : une vente partielle n'apporte que ce qui a été payé).
        $ventesEspeces = (int) Vente::where('mode_paiement', ModePaiement::Especes)
            ->where('statut', '!=', StatutVente::Annulee)
            ->whereBetween('created_at', [$debut, $fin])
            ->sum('montant_paye');

        $mouv = MouvementCaisse::with('auteur:id,name,prenom')
            ->whereBetween('created_at', [$debut, $fin])
            ->latest()
            ->get();

        $apports = (int) $mouv->where('type', 'apport')->sum('montant');
        $depenses = (int) $mouv->where('type', 'depense')->sum('montant');
        $retraits = (int) $mouv->where('type', 'retrait')->sum('montant');

        $session = CaisseJour::whereDate('date', $debut->toDateString())->first();
        $fond = $session?->fond_ouverture ?? 0;
        $soldeMouvements = $ventesEspeces + $apports - $depenses - $retraits;
        $soldeTheorique = $fond + $soldeMouvements;

        return [
            'date' => $debut->toDateString(),
            'ventes_especes' => $ventesEspeces,
            'apports' => $apports,
            'depenses' => $depenses,
            'retraits' => $retraits,
            'entrees' => $ventesEspeces + $apports,
            'sorties' => $depenses + $retraits,
            'fond_ouverture' => $fond,
            'solde' => $soldeMouvements,
            'solde_theorique' => $soldeTheorique,
            'ouverte' => $session !== null && $session->statut !== 'cloturee',
            'ouverture_faite' => $session !== null,
            'cloturee' => $session !== null && $session->statut === 'cloturee',
            'montant_reel' => $session?->montant_reel,
            'ecart' => $session?->ecart,
            'note_cloture' => $session?->note,
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
