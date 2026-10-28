<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    protected $table = 'produits';

    protected $fillable = [
        'nom', 'categorie', 'modele', 'reference',
        'prix_achat', 'prix_unitaire', 'stock', 'seuil_alerte', 'actif',
    ];

    protected $casts = [
        'prix_achat' => 'integer',
        'prix_unitaire' => 'integer',
        'stock' => 'integer',
        'seuil_alerte' => 'integer',
        'actif' => 'boolean',
    ];
}
