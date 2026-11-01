<?php

namespace App\Services\Paiement;

use App\Enums\StatutVente;
use App\Models\TransactionPaiement;
use App\Models\Vente;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Paiement en ligne Orange Money Guinée (API marchand « Web Payment »).
 *
 * Principe sécurisé :
 *  - Le montant vient TOUJOURS de la vente en base, jamais du téléphone du client.
 *  - On ne fait JAMAIS confiance au navigateur pour dire « c'est payé » : seule
 *    la notification serveur-à-serveur d'Orange (webhook) confirme le paiement.
 *  - Les clés marchand restent dans .env (config/boutique.php), hors du code.
 */
class OrangeMoneyService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('boutique.orange_money');
    }

    public function estConfigure(): bool
    {
        return filled($this->config['client_id']) && filled($this->config['merchant_key']);
    }

    /**
     * Démarre un paiement pour une vente. Retourne l'URL Orange Money vers
     * laquelle envoyer le client, et notre transaction en attente.
     *
     * @return array{transaction: TransactionPaiement, url_paiement: string}
     */
    public function initier(Vente $vente): array
    {
        if (! $this->estConfigure()) {
            throw new RuntimeException('Orange Money n\'est pas configuré (voir .env).');
        }

        $reference = 'OM-'.$vente->id.'-'.Str::upper(Str::random(8));

        $transaction = TransactionPaiement::create([
            'vente_id' => $vente->id,
            'canal' => 'orange_money',
            'reference_interne' => $reference,
            'montant' => $vente->montant_total,
            'statut' => 'en_attente',
        ]);

        $reponse = Http::withToken($this->jeton())
            ->acceptJson()
            ->post("{$this->config['base_url']}/orange-money-webpay/gn/v1/webpayment", [
                'merchant_key' => $this->config['merchant_key'],
                'currency' => $this->config['devise'],
                'order_id' => $reference,
                'amount' => $vente->montant_total,
                'return_url' => $this->config['return_url'],
                'cancel_url' => $this->config['cancel_url'],
                'notif_url' => $this->config['notif_url'],
                'lang' => 'fr',
            ]);

        if ($reponse->failed()) {
            $transaction->update(['statut' => 'echoue', 'reponse_operateur' => ['http' => $reponse->status()]]);
            Log::error('Orange Money : échec initiation', ['vente' => $vente->id, 'status' => $reponse->status()]);
            throw new RuntimeException('Impossible de démarrer le paiement Orange Money.');
        }

        $data = $reponse->json();

        $transaction->update([
            'reference_operateur' => $data['pay_token'] ?? ($data['payment_url'] ?? null),
            // On ne stocke que des champs non sensibles pour la trace.
            'reponse_operateur' => ['notif_token' => $data['notif_token'] ?? null],
        ]);

        return [
            'transaction' => $transaction,
            'url_paiement' => $data['payment_url'] ?? throw new RuntimeException('Réponse Orange Money invalide.'),
        ];
    }

    /**
     * Traite la notification serveur-à-serveur d'Orange (webhook).
     * C'est le SEUL endroit qui marque une vente comme payée.
     */
    public function traiterNotification(array $charge): void
    {
        $reference = $charge['order_id'] ?? null;
        $statut = strtoupper($charge['status'] ?? '');

        $transaction = TransactionPaiement::where('reference_interne', $reference)->first();

        if (! $transaction) {
            Log::warning('Orange Money : notification pour une transaction inconnue', ['order_id' => $reference]);
            return;
        }

        // Déjà confirmée : on ne rejoue pas (notifications envoyées plusieurs fois).
        if ($transaction->statut === 'reussi') {
            return;
        }

        // Sécurité anti-fraude : le webhook est public, donc on vérifie que le
        // jeton de notification correspond à celui reçu à l'initiation, ET que
        // le montant annoncé est bien celui de notre transaction. Sinon on ignore :
        // personne ne peut marquer une vente « payée » en envoyant un faux message.
        $notifTokenAttendu = $transaction->reponse_operateur['notif_token'] ?? null;
        $notifTokenRecu = $charge['notif_token'] ?? null;
        $montantRecu = isset($charge['amount']) ? (int) $charge['amount'] : null;

        if ($notifTokenAttendu && (! $notifTokenRecu || ! hash_equals((string) $notifTokenAttendu, (string) $notifTokenRecu))) {
            Log::warning('Orange Money : jeton de notification invalide', ['order_id' => $reference]);
            return;
        }
        if ($montantRecu !== null && $montantRecu !== (int) $transaction->montant) {
            Log::warning('Orange Money : montant de notification incohérent', ['order_id' => $reference]);
            return;
        }

        DB::transaction(function () use ($transaction, $statut, $charge) {
            if ($statut === 'SUCCESS') {
                $transaction->update([
                    'statut' => 'reussi',
                    'reference_operateur' => $charge['txnid'] ?? $transaction->reference_operateur,
                    'confirme_le' => now(),
                ]);
                $transaction->vente()->update(['statut' => StatutVente::Payee]);
            } elseif (in_array($statut, ['FAILED', 'EXPIRED', 'CANCELLED'], true)) {
                $transaction->update(['statut' => $statut === 'EXPIRED' ? 'expire' : 'echoue']);
            }
        });
    }

    /**
     * Jeton OAuth Orange, mis en cache jusqu'à son expiration.
     */
    private function jeton(): string
    {
        return Cache::remember('om_token', now()->addMinutes(55), function () {
            $reponse = Http::asForm()
                ->withBasicAuth($this->config['client_id'], $this->config['client_secret'])
                ->post("{$this->config['base_url']}/oauth/v3/token", ['grant_type' => 'client_credentials']);

            if ($reponse->failed()) {
                throw new RuntimeException('Orange Money : authentification refusée.');
            }

            return $reponse->json('access_token');
        });
    }
}
