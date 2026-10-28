<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionPaiement extends Model
{
    protected $table = 'transactions_paiement';

    protected $fillable = [
        'vente_id', 'canal', 'reference_interne', 'reference_operateur',
        'montant', 'statut', 'telephone_payeur', 'reponse_operateur', 'confirme_le',
    ];

    protected $casts = [
        'montant' => 'integer',
        'reponse_operateur' => 'array',
        'confirme_le' => 'datetime',
    ];

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }
}
