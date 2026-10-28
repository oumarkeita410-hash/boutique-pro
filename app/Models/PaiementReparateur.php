<?php

namespace App\Models;

use App\Enums\ModePaiement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementReparateur extends Model
{
    protected $table = 'paiements_reparateur';

    protected $fillable = ['reparateur_id', 'recu_par', 'montant', 'mode_paiement'];

    protected $casts = [
        'montant' => 'integer',
        'mode_paiement' => ModePaiement::class,
    ];

    public function reparateur(): BelongsTo
    {
        return $this->belongsTo(Reparateur::class);
    }
}
