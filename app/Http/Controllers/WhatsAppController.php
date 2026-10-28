<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    /**
     * GET /api/whatsapp/contact?message=... : lien wa.me vers la boutique.
     * Sert de bouton « Contacter sur WhatsApp » côté client.
     */
    public function contact(Request $request)
    {
        $message = (string) $request->query('message', '');

        return response()->json([
            'numero' => config('boutique.contact.whatsapp'),
            'lien' => $this->whatsapp->lienContact($message),
        ]);
    }

    /**
     * POST /api/admin/whatsapp/message  (admin) : envoie un message WhatsApp.
     * { "telephone": "+224...", "message": "..." }
     */
    public function envoyer(Request $request)
    {
        $donnees = $request->validate([
            'telephone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        if (! $this->whatsapp->estActif()) {
            throw ValidationException::withMessages([
                'whatsapp' => 'WhatsApp n\'est pas encore configuré (voir .env : WA_TOKEN, WA_PHONE_NUMBER_ID, WA_ACTIF).',
            ]);
        }

        $envoye = $this->whatsapp->envoyerTexte($donnees['telephone'], $donnees['message']);

        return response()->json(['envoye' => $envoye]);
    }
}
