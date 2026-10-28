<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'prenom', 'identifiant', 'email', 'telephone', 'password',
        'pays', 'region', 'fuseau_horaire', 'frequence_paie', 'montant_paie',
    ];

    // role et est_verrouille ne sont pas "fillable" : seul l'administrateur
    // les modifie, explicitement, jamais depuis un formulaire envoyé en masse.

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'est_verrouille' => 'boolean',
            'verrouille_le' => 'datetime',
            'derniere_connexion' => 'datetime',
            'montant_paie' => 'integer',
        ];
    }

    public function nomComplet(): string
    {
        return trim("{$this->prenom} {$this->name}");
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class, 'vendeur_id');
    }

    public function horaires(): HasMany
    {
        return $this->hasMany(HoraireTravail::class);
    }

    public function paiementsRecus(): HasMany
    {
        return $this->hasMany(PaiementVendeur::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
