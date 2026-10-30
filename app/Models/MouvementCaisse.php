<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementCaisse extends Model
{
    protected $table = 'mouvements_caisse';

    protected $fillable = ['user_id', 'type', 'montant', 'description'];

    protected $casts = ['montant' => 'integer'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
