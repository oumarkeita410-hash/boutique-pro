<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UtilisateurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->name,
            'prenom' => $this->prenom,
            'identifiant' => $this->identifiant,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'role' => $this->role->value,
            'pays' => $this->pays,
            'region' => $this->region,
            'fuseau_horaire' => $this->fuseau_horaire,
            'frequence_paie' => $this->frequence_paie,
            'montant_paie' => $this->montant_paie,
            'est_verrouille' => $this->est_verrouille,
            'tentatives_intrusion' => $this->tentatives_intrusion,
            'verrouille_le' => $this->verrouille_le?->toDateTimeString(),
            'derniere_connexion' => $this->derniere_connexion?->toDateTimeString(),
            'cree_le' => $this->created_at?->toDateTimeString(),
        ];
    }
}
