<?php

namespace App\Notifications;

use App\Models\Reparateur;
use Illuminate\Notifications\Notification;

class DetteReparateurDepassee extends Notification
{
    public function __construct(public Reparateur $reparateur, public int $dette)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $nom = $this->reparateur->nomComplet();
        $montant = number_format($this->dette, 0, ',', ' ');

        return [
            'type' => 'dette_reparateur',
            'reparateur_id' => $this->reparateur->id,
            'reparateur' => $nom,
            'telephone' => $this->reparateur->telephone,
            'dette' => $this->dette,
            'seuil' => config('boutique.seuil_dette_reparateur'),
            'message' => "Il est l'heure de faire payer {$nom} : {$montant} GNF dus.",
        ];
    }
}
