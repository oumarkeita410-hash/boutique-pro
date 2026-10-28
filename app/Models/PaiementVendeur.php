<?php

namespace App\Models;

use App\Enums\ModePaiement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementVendeur extends Model
{
    protected $table = 'paiements_vendeur';

    protected $fillable = [
        'user_id', 'paye_par', 'frequence', 'periode_debut', 'periode_fin',
        'montant', 'mode_paiement', 'reference_paiement', 'note',
    ];

    protected $casts = [
        'periode_debut' => 'date:Y-m-d',
        'periode_fin' => 'date:Y-m-d',
        'montant' => 'integer',
        'mode_paiement' => ModePaiement::class,
    ];

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
