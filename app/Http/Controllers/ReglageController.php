<?php

namespace App\Http\Controllers;

use App\Models\Reglage;
use Illuminate\Http\Request;

/**
 * Réglages de l'application modifiables par l'administrateur.
 * Pour l'instant : le numéro du service après-vente (SAV).
 */
class ReglageController extends Controller
{
    /** GET /api/admin/reglages : les réglages actuels. */
    public function index()
    {
        return response()->json([
            'sav_numero' => Reglage::valeur('sav_numero', config('boutique.sav.numero')),
        ]);
    }

    /** PUT /api/admin/reglages : modifier un réglage (admin seul). */
    public function update(Request $request)
    {
        $d = $request->validate([
            'sav_numero' => ['sometimes', 'required', 'string', 'max:40'],
        ]);

        if (array_key_exists('sav_numero', $d)) {
            Reglage::definir('sav_numero', trim($d['sav_numero']));
        }

        return response()->json([
            'message' => 'Réglages mis à jour.',
            'sav_numero' => Reglage::valeur('sav_numero', config('boutique.sav.numero')),
        ]);
    }
}
