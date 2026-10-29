<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Devis extends Model
{
    protected $table = 'devis';

    protected $fillable = [
        'reference', 'client_id', 'client_nom', 'cree_par',
        'statut', 'montant_total', 'note', 'valide_jusqu', 'vente_id',
    ];

    protected $casts = [
        'montant_total' => 'integer',
        'valide_jusqu' => 'date:Y-m-d',
    ];

    public function lignes(): HasMany
    {
        return $this->hasMany(DevisLigne::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function nomClient(): string
    {
        return $this->client ? trim($this->client->prenom.' '.$this->client->nom) : ($this->client_nom ?: 'Client');
    }
}
