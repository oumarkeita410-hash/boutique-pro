<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneVente extends Model
{
    protected $table = 'lignes_vente';

    protected $fillable = ['vente_id', 'produit_id', 'quantite', 'prix_unitaire', 'prix_achat', 'sous_total'];

    protected $casts = [
        'quantite' => 'integer',
        'prix_unitaire' => 'integer',
        'prix_achat' => 'integer',
        'sous_total' => 'integer',
    ];

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
