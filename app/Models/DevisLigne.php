<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevisLigne extends Model
{
    protected $table = 'devis_lignes';

    protected $fillable = ['devis_id', 'produit_id', 'designation', 'quantite', 'prix_unitaire', 'sous_total'];

    protected $casts = [
        'quantite' => 'integer',
        'prix_unitaire' => 'integer',
        'sous_total' => 'integer',
    ];

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }
}
