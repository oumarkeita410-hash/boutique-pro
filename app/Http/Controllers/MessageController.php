<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Messagerie vendeur/conseiller ↔ administrateur (ex. signaler un problème).
 */
class MessageController extends Controller
{
    /**
     * Vendeur : GET /api/messages : son fil avec l'administration.
     */
    public function monFil(Request $request)
    {
        $user = $request->user();

        Message::where('user_id', $user->id)
            ->where('auteur_id', '!=', $user->id)
            ->whereNull('lu_le')
            ->update(['lu_le' => now()]);

        return $this->fil($user->id);
    }

    /**
     * Vendeur : POST /api/messages  { "contenu": "Le client veut un écran A14 5G…" }
     */
    public function envoyer(Request $request)
    {
        $contenu = $request->validate(['contenu' => ['required', 'string', 'max:2000']])['contenu'];

        $message = Message::create([
            'user_id' => $request->user()->id,
            'auteur_id' => $request->user()->id,
            'contenu' => $contenu,
        ]);

        return response()->json($this->formater($message->load('auteur')), 201);
    }

    /**
     * Admin : GET /api/admin/messages : un fil par vendeur, le plus récent en haut.
     */
    public function boite()
    {
        return Message::query()
            ->selectRaw('user_id, MAX(created_at) as dernier_message')
            ->selectRaw('SUM(CASE WHEN lu_le IS NULL AND auteur_id = user_id THEN 1 ELSE 0 END) as non_lus')
            ->groupBy('user_id')
            ->orderByDesc('dernier_message')
            ->with('utilisateur:id,name,prenom,identifiant,role,region')
            ->paginate(50);
    }

    /**
     * Admin : GET /api/admin/messages/{utilisateur}
     */
    public function lire(User $utilisateur)
    {
        Message::where('user_id', $utilisateur->id)
            ->where('auteur_id', $utilisateur->id)
            ->whereNull('lu_le')
            ->update(['lu_le' => now()]);

        return $this->fil($utilisateur->id);
    }

    /**
     * Admin : POST /api/admin/messages/{utilisateur}  { "contenu": "…" }
     */
    public function repondre(Request $request, User $utilisateur)
    {
        $contenu = $request->validate(['contenu' => ['required', 'string', 'max:2000']])['contenu'];

        $message = Message::create([
            'user_id' => $utilisateur->id,
            'auteur_id' => $request->user()->id,
            'contenu' => $contenu,
        ]);

        return response()->json($this->formater($message->load('auteur')), 201);
    }

    private function fil(int $userId)
    {
        return Message::where('user_id', $userId)
            ->with('auteur:id,name,prenom,role')
            ->latest()
            ->limit(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $m) => $this->formater($m));
    }

    private function formater(Message $message): array
    {
        return [
            'id' => $message->id,
            'auteur' => $message->auteur->nomComplet(),
            'role' => $message->auteur->role->value,
            'contenu' => $message->contenu,
            'date' => $message->created_at->toDateTimeString(),
            'lu' => $message->lu_le !== null,
        ];
    }
}
