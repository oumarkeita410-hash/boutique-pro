<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un versement du reste d'une vente (paiement partiel apporté plus tard).
 */
class PaiementVente extends Model
{
    protected $table = 'paiements_vente';

    protected $fillable = ['vente_id', 'user_id', 'montant', 'mode_paiement'];

    protected $casts = ['montant' => 'integer'];

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
