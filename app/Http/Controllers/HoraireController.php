<?php

namespace App\Http\Controllers;

use App\Models\JournalActivite;
use App\Models\User;
use App\Services\HoraireService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HoraireController extends Controller
{
    public function __construct(private HoraireService $horaires)
    {
    }

    /**
     * Admin : GET /api/admin/utilisateurs/{utilisateur}/horaires
     */
    public function show(User $utilisateur)
    {
        return response()->json([
            'fuseau_horaire' => $utilisateur->fuseau_horaire,
            'jours' => $utilisateur->horaires()->orderBy('jour_semaine')->get(['jour_semaine', 'heure_debut', 'heure_fin']),
        ]);
    }

    /**
     * Admin : PUT /api/admin/utilisateurs/{utilisateur}/horaires
     * { "fuseau_horaire": "Africa/Conakry",
     *   "jours": [{"jour_semaine": 1, "heure_debut": "08:00", "heure_fin": "18:00"}, …] }
     * Remplace toute la semaine. Un jour absent de la liste = jour de repos.
     */
    public function update(Request $request, User $utilisateur)
    {
        $donnees = $request->validate([
            'fuseau_horaire' => ['sometimes', 'timezone'],
            'jours' => ['present', 'array', 'max:7'],
            'jours.*.jour_semaine' => ['required', 'integer', 'between:1,7', 'distinct'],
            'jours.*.heure_debut' => ['required', 'date_format:H:i'],
            'jours.*.heure_fin' => ['required', 'date_format:H:i', 'after:jours.*.heure_debut'],
        ]);

        DB::transaction(function () use ($request, $utilisateur, $donnees) {
            if (isset($donnees['fuseau_horaire'])) {
                $utilisateur->update(['fuseau_horaire' => $donnees['fuseau_horaire']]);
            }

            $utilisateur->horaires()->delete();
            $utilisateur->horaires()->createMany($donnees['jours']);

            JournalActivite::noter($request->user(), 'horaires_modifies', $utilisateur);
        });

        return $this->show($utilisateur);
    }

    /**
     * Vendeur : GET /api/mon-horaire : sa semaine + où il en est maintenant.
     */
    public function moi(Request $request)
    {
        $user = $request->user()->load('horaires');

        return response()->json([
            'fuseau_horaire' => $user->fuseau_horaire,
            'jours' => $user->horaires->sortBy('jour_semaine')->values()
                ->map->only(['jour_semaine', 'heure_debut', 'heure_fin']),
            'maintenant' => $this->horaires->statut($user),
        ]);
    }
}
