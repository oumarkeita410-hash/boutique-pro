<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class IntrusionDetectee extends Notification
{
    public function __construct(public User $vendeur, public string $chemin, public bool $bloque)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $nom = $this->vendeur->nomComplet();
        $message = $this->bloque
            ? "Compte de {$nom} BLOQUÉ : 3 tentatives d'accès à une zone réservée. Réactivez-le si nécessaire."
            : "{$nom} a tenté d'ouvrir une page administrateur ({$this->chemin}).";

        return [
            'type' => 'intrusion',
            'vendeur_id' => $this->vendeur->id,
            'vendeur' => $nom,
            'chemin' => $this->chemin,
            'tentatives' => $this->vendeur->tentatives_intrusion,
            'compte_bloque' => $this->bloque,
            'message' => $message,
        ];
    }
}
