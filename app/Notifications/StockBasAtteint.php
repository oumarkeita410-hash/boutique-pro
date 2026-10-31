<?php

namespace App\Notifications;

use App\Models\Produit;
use Illuminate\Notifications\Notification;

/**
 * Prévient l'administrateur dès qu'un produit passe sous son seuil d'alerte
 * (au moment d'une vente). But : recommander à temps chez DL-Service.
 */
class StockBasAtteint extends Notification
{
    public function __construct(public Produit $produit)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'stock_bas',
            'produit_id' => $this->produit->id,
            'produit' => $this->produit->nom,
            'stock' => (int) $this->produit->stock,
            'seuil' => (int) $this->produit->seuil_alerte,
            'message' => "Stock bas : {$this->produit->nom} — il reste {$this->produit->stock} (seuil {$this->produit->seuil_alerte}). À recommander.",
        ];
    }
}
