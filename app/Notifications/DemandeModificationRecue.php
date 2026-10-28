<?php

namespace App\Notifications;

use App\Models\DemandeModificationVente;
use Illuminate\Notifications\Notification;

class DemandeModificationRecue extends Notification
{
    public function __construct(public DemandeModificationVente $demande)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $vendeur = $this->demande->demandeur?->nomComplet();
        $ref = $this->demande->vente?->reference;

        return [
            'type' => 'demande_modification_vente',
            'demande_id' => $this->demande->id,
            'vente' => $ref,
            'vendeur' => $vendeur,
            'changements' => $this->demande->changements,
            'motif' => $this->demande->motif_demande,
            'message' => "{$vendeur} demande à modifier la vente {$ref}.",
        ];
    }
}
