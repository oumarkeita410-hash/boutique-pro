<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une réclamation de service après-vente (SAV) : un client signale un problème
 * sur un produit acheté. Un conseiller la traite ; l'admin voit et contrôle tout.
 */
class Reclamation extends Model
{
    protected $table = 'reclamations';

    protected $fillable = [
        'numero', 'client_id', 'client_nom', 'client_tel', 'vente_ref',
        'produit', 'probleme', 'statut', 'conseiller_id', 'cree_par', 'resolue_le',
    ];

    protected $casts = [
        'numero' => 'integer',
        'resolue_le' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function conseiller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conseiller_id');
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ReclamationInteraction::class);
    }
}
