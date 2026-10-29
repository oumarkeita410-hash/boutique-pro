<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriseReparateur extends Model
{
    protected $table = 'prises_reparateur';

    protected $fillable = [
        'reparateur_id', 'produit_id', 'enregistre_par',
        'modele', 'marque', 'categorie',
        'quantite', 'prix_unitaire', 'montant_total', 'montant_paye',
    ];

    protected $casts = [
        'quantite' => 'integer',
        'prix_unitaire' => 'integer',
        'montant_total' => 'integer',
        'montant_paye' => 'integer',
    ];

    public function reste(): int
    {
        return $this->montant_total - $this->montant_paye;
    }

    /** Nom affiché : le produit du catalogue, sinon le modèle/marque saisis à la main. */
    public function designation(): string
    {
        if ($this->relationLoaded('produit') && $this->produit) {
            return $this->produit->nom;
        }
        if ($this->produit_id && $this->produit) {
            return $this->produit->nom;
        }
        return trim(($this->modele ?? '').' '.($this->marque ? '('.$this->marque.')' : '')) ?: 'Article';
    }

    public function reparateur(): BelongsTo
    {
        return $this->belongsTo(Reparateur::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
