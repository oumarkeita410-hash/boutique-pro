<?php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\StatutVente;
use App\Exceptions\CompteVerrouilleException;
use App\Exceptions\StockInsuffisantException;
use App\Models\MouvementStock;
use App\Models\Position;
use App\Models\Produit;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class VenteService
{
    /**
     * Vend un seul article (raccourci de vendre()).
     */
    public function vendreArticle(
        User $vendeur,
        int $produitId,
        int $quantite,
        ModePaiement $modePaiement,
        ?int $clientId = null,
        ?string $uuidClient = null,
        ?array $position = null,
    ): Vente {
        return $this->vendre(
            $vendeur,
            [['produit_id' => $produitId, 'quantite' => $quantite]],
            $modePaiement,
            $clientId,
            $uuidClient,
            $position,
        );
    }

    /**
     * Enregistre une vente : vérifie le stock, crée la vente et ses lignes,
     * diminue le stock et trace chaque mouvement. Tout se fait dans une seule
     * transaction : soit tout est enregistré, soit rien.
     *
     * $uuidClient est généré par l'appareil du vendeur. Il permet de renvoyer
     * une vente faite hors connexion sans risquer de l'enregistrer deux fois.
     *
     * @param  array<int, array{produit_id: int, quantite: int}>  $lignes
     */
    public function vendre(
        User $vendeur,
        array $lignes,
        ModePaiement $modePaiement,
        ?int $clientId = null,
        ?string $uuidClient = null,
        ?array $position = null,
        ?string $clientNom = null,
        ?string $clientTel = null,
    ): Vente {
        if ($vendeur->est_verrouille) {
            throw new CompteVerrouilleException();
        }

        if (! in_array($vendeur->role, [Role::Vendeur, Role::Admin], true)) {
            throw new AuthorizationException("Ce compte n'est pas autorisé à vendre.");
        }

        // Vente à crédit : réservée à l'administrateur, et toujours rattachée à un client.
        if ($modePaiement === ModePaiement::Credit) {
            if ($vendeur->role !== Role::Admin) {
                throw ValidationException::withMessages([
                    'mode_paiement' => "Seul l'administrateur peut vendre à crédit.",
                ]);
            }
            if (! $clientId) {
                throw ValidationException::withMessages([
                    'client_id' => 'Un client est obligatoire pour une vente à crédit.',
                ]);
            }
        }

        if ($uuidClient && $existante = Vente::where('uuid_client', $uuidClient)->first()) {
            return $existante->load('lignes.produit');
        }

        $quantites = $this->regrouperQuantites($lignes);

        try {
            return DB::transaction(function () use ($vendeur, $quantites, $modePaiement, $clientId, $uuidClient, $position, $clientNom, $clientTel) {
                // Verrouille les lignes produits jusqu'à la fin de la transaction :
                // deux vendeurs ne peuvent pas vendre le même dernier article.
                $produits = Produit::whereIn('id', array_keys($quantites))
                    ->where('actif', true)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($quantites as $produitId => $quantite) {
                    $produit = $produits->get($produitId)
                        ?? throw (new ModelNotFoundException())->setModel(Produit::class, [$produitId]);

                    if ($produit->stock < $quantite) {
                        throw new StockInsuffisantException($produit, $quantite);
                    }
                }

                $vente = Vente::create([
                    'reference' => 'V-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'uuid_client' => $uuidClient,
                    'vendeur_id' => $vendeur->id,
                    'client_id' => $clientId,
                    'client_nom' => $clientNom,
                    'client_tel' => $clientTel,
                    'mode_paiement' => $modePaiement,
                    // Espèces : payée tout de suite. Crédit : à rembourser.
                    // Orange Money / carte : confirmée plus tard par le retour de l'opérateur.
                    'statut' => match ($modePaiement) {
                        ModePaiement::Especes => StatutVente::Payee,
                        ModePaiement::Credit => StatutVente::ACredit,
                        default => StatutVente::EnAttentePaiement,
                    },
                    'montant_total' => 0,
                ]);

                $total = 0;

                foreach ($quantites as $produitId => $quantite) {
                    $produit = $produits->get($produitId);
                    // Le prix vient toujours de la base, jamais de l'appareil du vendeur.
                    $sousTotal = $produit->prix_unitaire * $quantite;

                    $vente->lignes()->create([
                        'produit_id' => $produit->id,
                        'quantite' => $quantite,
                        'prix_unitaire' => $produit->prix_unitaire,
                        'prix_achat' => $produit->prix_achat,
                        'sous_total' => $sousTotal,
                    ]);

                    $stockAvant = $produit->stock;
                    $produit->decrement('stock', $quantite);

                    MouvementStock::create([
                        'produit_id' => $produit->id,
                        'user_id' => $vendeur->id,
                        'vente_id' => $vente->id,
                        'type' => 'vente',
                        'quantite' => -$quantite,
                        'stock_avant' => $stockAvant,
                        'stock_apres' => $produit->stock,
                    ]);

                    $total += $sousTotal;
                }

                $champsVente = ['montant_total' => $total];

                // Localisation de la vente (visible, communiquée au vendeur) si fournie.
                if ($position && isset($position['latitude'], $position['longitude'])) {
                    $champsVente['latitude'] = $position['latitude'];
                    $champsVente['longitude'] = $position['longitude'];

                    Position::create([
                        'user_id' => $vendeur->id,
                        'vente_id' => $vente->id,
                        'contexte' => 'vente',
                        'latitude' => $position['latitude'],
                        'longitude' => $position['longitude'],
                        'precision_m' => $position['precision_m'] ?? null,
                        'ville' => $position['ville'] ?? $vendeur->region,
                        'region' => $vendeur->region,
                        'pays' => $vendeur->pays,
                        'ip' => $position['ip'] ?? null,
                    ]);
                }

                $vente->update($champsVente);

                return $vente->load('lignes.produit');
            });
        } catch (UniqueConstraintViolationException $e) {
            // La même vente hors connexion a été envoyée deux fois en même temps.
            if ($uuidClient && $existante = Vente::where('uuid_client', $uuidClient)->first()) {
                return $existante->load('lignes.produit');
            }

            throw $e;
        }
    }

    /**
     * Additionne les quantités d'un même produit et trie par id
     * (ordre de verrouillage constant pour éviter les interblocages).
     *
     * @return array<int, int> produit_id => quantité
     */
    private function regrouperQuantites(array $lignes): array
    {
        if ($lignes === []) {
            throw new InvalidArgumentException('La vente doit contenir au moins un article.');
        }

        $quantites = [];

        foreach ($lignes as $ligne) {
            $quantite = (int) $ligne['quantite'];

            if ($quantite < 1) {
                throw new InvalidArgumentException('La quantité doit être au moins 1.');
            }

            $id = (int) $ligne['produit_id'];
            $quantites[$id] = ($quantites[$id] ?? 0) + $quantite;
        }

        ksort($quantites);

        return $quantites;
    }
}
