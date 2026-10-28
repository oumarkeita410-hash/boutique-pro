<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $table = 'clients';

    protected $fillable = ['prenom', 'nom', 'telephone', 'email', 'pays', 'ville', 'enregistre_par'];

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistre_par');
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class);
    }
}
