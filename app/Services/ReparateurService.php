<?php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Exceptions\CompteVerrouilleException;
use App\Exceptions\StockInsuffisantException;
use App\Models\MouvementStock;
use App\Models\PaiementReparateur;
use App\Models\PriseReparateur;
use App\Models\Produit;
use App\Models\Reparateur;
use App\Models\User;
use App\Notifications\DetteReparateurDepassee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ReparateurService
{
    /**
     * Un réparateur prend un écran (ou une autre pièce) : vérifie le stock,
     * enregistre le montant payé et le reste, diminue le stock.
     * Si sa dette passe au-dessus du seuil (500 000 GNF), l'administrateur est alerté.
     */
    public function enregistrerPrise(
        User $auteur,
        Reparateur $reparateur,
        int $produitId,
        int $quantite,
        int $montantPaye,
    ): PriseReparateur {
        if ($auteur->est_verrouille) {
            throw new CompteVerrouilleException();
        }

        if (! $reparateur->actif) {
            throw ValidationException::withMessages(['reparateur' => 'Ce réparateur est désactivé.']);
        }

        if ($quantite < 1 || $montantPaye < 0) {
            throw ValidationException::withMessages(['quantite' => 'Quantité ou montant invalide.']);
        }

        [$prise, $detteAvant, $detteApres] = DB::transaction(function () use ($auteur, $reparateur, $produitId, $quantite, $montantPaye) {
            // Verrouille le réparateur : deux prises simultanées ne faussent pas sa dette.
            Reparateur::whereKey($reparateur->id)->lockForUpdate()->first();

            $produit = Produit::whereKey($produitId)->where('actif', true)->lockForUpdate()->firstOrFail();

            if ($produit->stock < $quantite) {
                throw new StockInsuffisantException($produit, $quantite);
            }

            $total = $produit->prix_unitaire * $quantite;

            if ($montantPaye > $total) {
                throw ValidationException::withMessages(['montant_paye' => 'Le montant payé dépasse le prix total.']);
            }

            // Accorder un crédit (laisser un reste à payer) est réservé à l'administrateur.
            if ($montantPaye < $total && $auteur->role !== Role::Admin) {
                throw ValidationException::withMessages([
                    'montant_paye' => "Seul l'administrateur peut accorder un crédit. Enregistrez le paiement complet ou prévenez l'administrateur.",
                ]);
            }

            $detteAvant = $reparateur->calculerDette();

            $prise = $reparateur->prises()->create([
                'produit_id' => $produit->id,
                'enregistre_par' => $auteur->id,
                'quantite' => $quantite,
                'prix_unitaire' => $produit->prix_unitaire,
                'montant_total' => $total,
                'montant_paye' => $montantPaye,
            ]);

            $stockAvant = $produit->stock;
            $produit->decrement('stock', $quantite);

            MouvementStock::create([
                'produit_id' => $produit->id,
                'user_id' => $auteur->id,
                'type' => 'reparateur',
                'quantite' => -$quantite,
                'stock_avant' => $stockAvant,
                'stock_apres' => $produit->stock,
            ]);

            return [$prise, $detteAvant, $detteAvant + $total - $montantPaye];
        });

        $seuil = config('boutique.seuil_dette_reparateur');

        if ($detteAvant <= $seuil && $detteApres > $seuil) {
            $this->alerterAdministrateurs($reparateur, $detteApres);
        }

        return $prise->load('produit', 'reparateur');
    }

    /**
     * Le réparateur rembourse. Le montant est réparti sur ses prises
     * les plus anciennes d'abord.
     */
    public function enregistrerPaiement(
        User $auteur,
        Reparateur $reparateur,
        int $montant,
        ModePaiement $modePaiement,
    ): PaiementReparateur {
        if ($auteur->est_verrouille) {
            throw new CompteVerrouilleException();
        }

        return DB::transaction(function () use ($auteur, $reparateur, $montant, $modePaiement) {
            Reparateur::whereKey($reparateur->id)->lockForUpdate()->first();

            $dette = $reparateur->calculerDette();

            if ($montant < 1 || $montant > $dette) {
                throw ValidationException::withMessages([
                    'montant' => "Le montant doit être compris entre 1 et {$dette} GNF (dette actuelle).",
                ]);
            }

            $paiement = $reparateur->paiements()->create([
                'recu_par' => $auteur->id,
                'montant' => $montant,
                'mode_paiement' => $modePaiement,
            ]);

            $restant = $montant;
            $prises = $reparateur->prises()
                ->whereColumn('montant_paye', '<', 'montant_total')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($prises as $prise) {
                if ($restant === 0) {
                    break;
                }

                $part = min($restant, $prise->reste());
                $prise->increment('montant_paye', $part);
                $restant -= $part;
            }

            return $paiement;
        });
    }

    public function alerterAdministrateurs(Reparateur $reparateur, int $dette): void
    {
        $admins = User::where('role', Role::Admin)->where('est_verrouille', false)->get();

        Notification::send($admins, new DetteReparateurDepassee($reparateur, $dette));
    }
}
