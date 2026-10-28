<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoraireTravail extends Model
{
    protected $table = 'horaires_travail';

    protected $fillable = ['user_id', 'jour_semaine', 'heure_debut', 'heure_fin'];

    protected $casts = ['jour_semaine' => 'integer'];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
