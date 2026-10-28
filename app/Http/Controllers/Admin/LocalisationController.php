<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * L'administrateur suit où travaillent les vendeurs (33 préfectures, étranger).
 * La position est enregistrée à chaque connexion et à chaque vente, et le vendeur
 * en est informé à la connexion.
 */
class LocalisationController extends Controller
{
    /**
     * GET /api/admin/localisation : dernière position connue de chaque vendeur.
     */
    public function index()
    {
        // Dernière position par utilisateur.
        $dernieres = Position::query()
            ->whereIn('id', fn ($q) => $q->selectRaw('MAX(id)')->from('positions')->groupBy('user_id'))
            ->with('utilisateur:id,name,prenom,region,pays,est_verrouille,derniere_connexion')
            ->get();

        return $dernieres->map(fn (Position $p) => [
            'user_id' => $p->user_id,
            'vendeur' => $p->utilisateur?->nomComplet(),
            'region' => $p->region,
            'pays' => $p->pays,
            'latitude' => $p->latitude,
            'longitude' => $p->longitude,
            'precision_m' => $p->precision_m,
            'ip' => $p->ip,
            'contexte' => $p->contexte,
            'vu_le' => $p->created_at->toDateTimeString(),
        ]);
    }

    /**
     * GET /api/admin/localisation/{utilisateur} : historique des positions d'un vendeur.
     */
    public function historique(Request $request, User $utilisateur)
    {
        return $utilisateur->load([])->positions()
            ->latest()
            ->paginate(100, ['contexte', 'latitude', 'longitude', 'precision_m', 'ville', 'region', 'pays', 'ip', 'created_at']);
    }
}
