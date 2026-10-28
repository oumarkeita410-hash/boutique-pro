<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeModificationVente extends Model
{
    protected $table = 'demandes_modification_vente';

    protected $fillable = [
        'vente_id', 'demande_par', 'traite_par',
        'changements', 'avant', 'apres', 'motif_demande', 'motif_refus', 'statut', 'traite_le',
    ];

    protected $casts = [
        'changements' => 'array',
        'avant' => 'array',
        'apres' => 'array',
        'traite_le' => 'datetime',
    ];

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demande_par');
    }
}
