<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une trace d'échange sur une réclamation : un appel (résumé + note vocale),
 * un message ou une note. Chaque entrée garde qui l'a écrite et quand.
 */
class ReclamationInteraction extends Model
{
    protected $table = 'reclamation_interactions';

    protected $fillable = [
        'reclamation_id', 'type', 'resume', 'audio_chemin', 'audio_type', 'audio_duree', 'auteur_id',
    ];

    protected $casts = [
        'audio_duree' => 'integer',
    ];

    public function reclamation(): BelongsTo
    {
        return $this->belongsTo(Reclamation::class, 'reclamation_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
