<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un client qui arrive en boutique et attend d'être servi (file d'attente).
 */
class Accueil extends Model
{
    protected $table = 'accueils';

    protected $fillable = [
        'ticket', 'client_nom', 'client_tel', 'motif', 'note', 'statut',
        'conseiller_id', 'vendeur_id', 'pris_par', 'pris_le', 'termine_le',
    ];

    protected $casts = [
        'ticket' => 'integer',
        'pris_le' => 'datetime',
        'termine_le' => 'datetime',
    ];

    public function conseiller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conseiller_id');
    }

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendeur_id');
    }

    public function prisPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pris_par');
    }
}
