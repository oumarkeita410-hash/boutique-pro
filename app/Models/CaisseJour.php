<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaisseJour extends Model
{
    protected $table = 'caisse_jours';

    protected $fillable = [
        'date', 'fond_ouverture', 'montant_reel', 'solde_theorique', 'ecart',
        'statut', 'ouvert_par', 'cloture_par', 'note',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'fond_ouverture' => 'integer',
        'montant_reel' => 'integer',
        'solde_theorique' => 'integer',
        'ecart' => 'integer',
    ];
}
