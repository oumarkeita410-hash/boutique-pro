<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Position extends Model
{
    protected $table = 'positions';

    protected $fillable = [
        'user_id', 'vente_id', 'contexte',
        'latitude', 'longitude', 'precision_m', 'ville', 'region', 'pays', 'ip',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'precision_m' => 'integer',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
