<?php

namespace App\Services\Paiement;

use App\Enums\StatutVente;
use App\Models\TransactionPaiement;
use App\Models\Vente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Paiement en ligne tout-en-un via CinetPay (Guinée, GNF).
 * Un seul compte marchand accepte Orange Money + MTN Mobile Money + carte bancaire.
 *
 * Principes de sécurité (identiques à l'Orange Money marchand) :
 *  - Le montant vient TOUJOURS de la vente en base, jamais du téléphone du client.
 *  - On ne fait JAMAIS confiance au navigateur ni au contenu du webhook pour dire
 *    « c'est payé » : on RE-INTERROGE CinetPay (/payment/check) qui est la seule
 *    source de vérité avant de marquer une vente payée.
 *  - Les clés marchand restent dans .env (config/boutique.php), hors du code.
 */
class CinetPayService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('boutique.cinetpay');
    }

    public function estConfigure(): bool
    {
        return filled($this->config['apikey']) && filled($this->config['site_id']);
    }

    /**
     * Démarre un paiement en ligne pour une vente et renvoie l'URL de paiement
     * CinetPay (où le client choisit Orange Money / MTN / carte).
     *
     * @return array{transaction: TransactionPaiement, url_paiement: string}
     */
    public function initier(Vente $vente, ?string $telephone = null, array $client = []): array
    {
        if (! $this->estConfigure()) {
            throw new RuntimeException("Le paiement en ligne n'est pas configuré (voir .env : CINETPAY_APIKEY / CINETPAY_SITE_ID).");
        }

        $montant = (int) $vente->montant_total;

        // CinetPay exige un montant multiple de 5 pour le GNF (et XOF/XAF/CDF).
        if (in_array($this->config['devise'], ['GNF', 'XOF', 'XAF', 'CDF'], true) && $montant % 5 !== 0) {
            throw new RuntimeException("Le montant ($montant GNF) doit être un multiple de 5 pour le paiement en ligne.");
        }
        if ($montant < 100) {
            throw new RuntimeException('Le montant minimum du paiement en ligne est de 100 GNF.');
        }

        $reference = 'CP-'.$vente->id.'-'.Str::upper(Str::random(8));

        $transaction = TransactionPaiement::create([
            'vente_id' => $vente->id,
            'canal' => 'cinetpay',
            'reference_interne' => $reference,
            'montant' => $montant,
            'statut' => 'en_attente',
            'telephone_payeur' => $telephone,
        ]);

        $reponse = Http::acceptJson()->post("{$this->config['base_url']}/payment", [
            'apikey' => $this->config['apikey'],
            'site_id' => $this->config['site_id'],
            'transaction_id' => $reference,
            'amount' => $montant,
            'currency' => $this->config['devise'],
            'description' => 'Vente '.$vente->reference.' — '.config('boutique.nom'),
            'notify_url' => $this->config['notify_url'],
            'return_url' => $this->config['return_url'],
            'channels' => $this->config['canaux'] ?: 'ALL',
            'lang' => 'fr',
            'customer_name' => $client['nom'] ?? 'Client',
            'customer_surname' => $client['prenom'] ?? '',
            'customer_phone_number' => $telephone ?? '',
            'metadata' => 'vente:'.$vente->id,
        ]);

        $data = $reponse->json();

        // CinetPay renvoie code "201" + data.payment_url en cas de succès.
        if ($reponse->failed() || ($data['code'] ?? null) !== '201' || empty($data['data']['payment_url'])) {
            $transaction->update([
                'statut' => 'echoue',
                'reponse_operateur' => ['code' => $data['code'] ?? $reponse->status(), 'message' => $data['message'] ?? null],
            ]);
            Log::error('CinetPay : échec initiation', ['vente' => $vente->id, 'code' => $data['code'] ?? $reponse->status()]);
            throw new RuntimeException('Impossible de démarrer le paiement en ligne. '.($data['description'] ?? ''));
        }

        $transaction->update([
            'reference_operateur' => $data['data']['payment_token'] ?? null,
            'reponse_operateur' => ['code' => $data['code'], 'message' => $data['message']],
        ]);

        return [
            'transaction' => $transaction,
            'url_paiement' => $data['data']['payment_url'],
        ];
    }

    /**
     * Traite la notification serveur-à-serveur de CinetPay (webhook).
     * On NE croit PAS le contenu du webhook : on ne garde que l'identifiant de
     * transaction et on re-vérifie l'état réel auprès de CinetPay.
     */
    public function traiterNotification(array $charge): void
    {
        $reference = $charge['cpm_trans_id'] ?? null;

        if (! $reference) {
            Log::warning('CinetPay : notification sans cpm_trans_id');
            return;
        }

        $this->verifier($reference);
    }

    /**
     * Interroge CinetPay sur l'état réel d'une transaction et met à jour la base.
     * C'est le SEUL endroit qui marque une vente comme payée. Idempotent.
     *
     * @return TransactionPaiement|null La transaction mise à jour (null si inconnue).
     */
    public function verifier(string $reference): ?TransactionPaiement
    {
        $transaction = TransactionPaiement::where('reference_interne', $reference)->first();

        if (! $transaction) {
            Log::warning('CinetPay : vérification pour une transaction inconnue', ['ref' => $reference]);
            return null;
        }

        // Déjà confirmée : on ne rejoue pas (le webhook peut arriver plusieurs fois).
        if ($transaction->statut === 'reussi') {
            return $transaction;
        }

        if (! $this->estConfigure()) {
            return $transaction;
        }

        $reponse = Http::acceptJson()->post("{$this->config['base_url']}/payment/check", [
            'apikey' => $this->config['apikey'],
            'site_id' => $this->config['site_id'],
            'transaction_id' => $reference,
        ]);

        $data = $reponse->json();
        $statutOperateur = strtoupper($data['data']['status'] ?? '');
        $montantPaye = (int) ($data['data']['amount'] ?? 0);

        DB::transaction(function () use ($transaction, $data, $statutOperateur, $montantPaye) {
            // Succès UNIQUEMENT si CinetPay dit ACCEPTED ET que le montant payé
            // correspond bien au montant de notre transaction (anti-fraude).
            if (($data['code'] ?? null) === '00' && $statutOperateur === 'ACCEPTED' && $montantPaye === (int) $transaction->montant) {
                $transaction->update([
                    'statut' => 'reussi',
                    'reference_operateur' => $data['data']['operator_id'] ?? $transaction->reference_operateur,
                    'telephone_payeur' => $data['data']['phone_number'] ?? $transaction->telephone_payeur,
                    'confirme_le' => now(),
                ]);
                // On ne « rétrograde » jamais une vente déjà payée/annulée.
                if (! in_array($transaction->vente->statut, [StatutVente::Payee, StatutVente::Annulee], true)) {
                    $transaction->vente()->update([
                        'statut' => StatutVente::Payee,
                        'montant_paye' => $transaction->vente->montant_total,
                    ]);
                }
            } elseif (in_array($statutOperateur, ['REFUSED', 'CANCELED', 'CANCELLED'], true)) {
                $transaction->update(['statut' => 'echoue']);
            }
            // Sinon : PENDING / WAITING → on laisse « en_attente ».
        });

        return $transaction->refresh();
    }
}
