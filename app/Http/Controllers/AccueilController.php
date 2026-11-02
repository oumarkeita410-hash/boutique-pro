<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Accueil;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * File d'attente d'accueil. Les conseillers (et l'admin) enregistrent les
 * clients qui arrivent ; les vendeurs voient la file et qui leur est envoyé.
 */
class AccueilController extends Controller
{
    private const MOTIFS = ['achat', 'reparation', 'renseignement', 'autre'];

    /**
     * GET /api/accueil : la file du jour (en attente + en cours), dans l'ordre d'arrivée.
     */
    public function index(Request $request)
    {
        $aujourdhui = now()->startOfDay();

        $lignes = Accueil::with(['vendeur:id,name,prenom', 'conseiller:id,name,prenom', 'prisPar:id,name,prenom'])
            ->where('created_at', '>=', $aujourdhui)
            ->whereIn('statut', ['en_attente', 'en_cours'])
            ->orderBy('ticket')
            ->get()
            ->map(fn (Accueil $a) => $this->presenter($a));

        return response()->json([
            'en_attente' => $lignes->where('statut', 'en_attente')->values(),
            'en_cours' => $lignes->where('statut', 'en_cours')->values(),
            'nombre_attente' => $lignes->where('statut', 'en_attente')->count(),
            'termines_jour' => Accueil::where('created_at', '>=', $aujourdhui)->where('statut', 'termine')->count(),
        ]);
    }

    /**
     * POST /api/accueil : ajoute un client à la file. Conseiller ou admin seulement.
     */
    public function store(Request $request)
    {
        if (! in_array($request->user()->role, [Role::Conseiller, Role::Admin], true)) {
            abort(403, "Seuls les conseillers et l'administrateur peuvent accueillir un client.");
        }

        $d = $request->validate([
            'client_nom' => ['nullable', 'string', 'max:150'],
            'client_tel' => ['nullable', 'string', 'max:30'],
            'motif' => ['required', 'in:'.implode(',', self::MOTIFS)],
            'note' => ['nullable', 'string', 'max:255'],
            'vendeur_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Numéro du jour (ordre d'arrivée) : on continue la suite du jour.
        $ticket = (int) Accueil::where('created_at', '>=', now()->startOfDay())->max('ticket') + 1;

        $accueil = Accueil::create([
            'ticket' => $ticket,
            'client_nom' => $d['client_nom'] ?? null,
            'client_tel' => $d['client_tel'] ?? null,
            'motif' => $d['motif'],
            'note' => $d['note'] ?? null,
            'statut' => 'en_attente',
            'conseiller_id' => $request->user()->id,
            'vendeur_id' => $d['vendeur_id'] ?? null,
        ]);

        return response()->json($this->presenter($accueil->load(['vendeur:id,name,prenom'])), 201);
    }

    /**
     * POST /api/accueil/{accueil}/prendre : « je m'en occupe » (passe en cours).
     */
    public function prendre(Request $request, Accueil $accueil)
    {
        $this->verifierActeur($request, $accueil);

        $accueil->update([
            'statut' => 'en_cours',
            'pris_par' => $request->user()->id,
            'pris_le' => now(),
        ]);

        return response()->json(['message' => 'Client pris en charge.', 'statut' => $accueil->statut]);
    }

    /**
     * POST /api/accueil/{accueil}/terminer : le client a été servi.
     */
    public function terminer(Request $request, Accueil $accueil)
    {
        $this->verifierActeur($request, $accueil);

        $accueil->update(['statut' => 'termine', 'termine_le' => now()]);

        return response()->json(['message' => 'Terminé.', 'statut' => $accueil->statut]);
    }

    /**
     * POST /api/accueil/{accueil}/annuler : le client est parti / annulé.
     */
    public function annuler(Request $request, Accueil $accueil)
    {
        $this->verifierActeur($request, $accueil);

        $accueil->update(['statut' => 'annule']);

        return response()->json(['message' => 'Annulé.', 'statut' => $accueil->statut]);
    }

    /**
     * GET /api/accueil/vendeurs : liste des vendeurs actifs (pour « envoyer vers »).
     */
    public function vendeurs()
    {
        return User::where('role', Role::Vendeur)
            ->where('est_verrouille', false)
            ->orderBy('prenom')
            ->get(['id', 'name', 'prenom'])
            ->map(fn (User $u) => ['id' => $u->id, 'nom' => trim($u->prenom.' '.$u->name)]);
    }

    /** Qui peut agir : conseiller, admin, ou le vendeur à qui le client est envoyé. */
    private function verifierActeur(Request $request, Accueil $accueil): void
    {
        $user = $request->user();
        $autorise = in_array($user->role, [Role::Conseiller, Role::Admin], true)
            || ($user->role === Role::Vendeur && $accueil->vendeur_id === $user->id);

        if (! $autorise) {
            abort(403, "Vous ne pouvez pas modifier cet accueil.");
        }
    }

    private function presenter(Accueil $a): array
    {
        return [
            'id' => $a->id,
            'ticket' => $a->ticket,
            'client' => $a->client_nom ?: 'Client',
            'telephone' => $a->client_tel,
            'motif' => $a->motif,
            'note' => $a->note,
            'statut' => $a->statut,
            'vendeur' => $a->vendeur ? ['id' => $a->vendeur->id, 'nom' => trim($a->vendeur->prenom.' '.$a->vendeur->name)] : null,
            'pris_par' => $a->prisPar ? trim($a->prisPar->prenom.' '.$a->prisPar->name) : null,
            // Temps d'attente en minutes depuis l'arrivée.
            'attente_min' => (int) $a->created_at->diffInMinutes(now()),
            'arrivee' => $a->created_at->format('H:i'),
        ];
    }
}
