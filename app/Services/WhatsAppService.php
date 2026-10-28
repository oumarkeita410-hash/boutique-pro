<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de messages via l'API WhatsApp Business (Meta Cloud API).
 * Jeton et identifiant de numéro restent dans .env (config/boutique.php).
 */
class WhatsAppService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('boutique.whatsapp');
    }

    public function estActif(): bool
    {
        return (bool) $this->config['actif'] && filled($this->config['token']) && filled($this->config['phone_number_id']);
    }

    /**
     * Envoie un message texte simple. Le destinataire doit être au format
     * international sans « + » ni espaces (ex. 224621952252).
     */
    public function envoyerTexte(string $destinataire, string $message): bool
    {
        if (! $this->estActif()) {
            return false;
        }

        $numero = preg_replace('/\D+/', '', $destinataire);

        $reponse = Http::withToken($this->config['token'])
            ->acceptJson()
            ->post("{$this->config['base_url']}/{$this->config['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $numero,
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        if ($reponse->failed()) {
            Log::warning('WhatsApp : envoi échoué', ['to' => $numero, 'status' => $reponse->status()]);
            return false;
        }

        return true;
    }

    /**
     * Lien wa.me vers la boutique, utilisable comme bouton de contact côté client.
     */
    public function lienContact(string $texte = ''): string
    {
        $numero = preg_replace('/\D+/', '', config('boutique.contact.whatsapp'));

        return 'https://wa.me/'.$numero.($texte ? '?text='.rawurlencode($texte) : '');
    }
}
