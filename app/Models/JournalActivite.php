<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace de chaque action sensible : qui a fait quoi, sur quel compte, quand.
 */
class JournalActivite extends Model
{
    protected $table = 'journal_activites';

    protected $fillable = ['auteur_id', 'action', 'cible_type', 'cible_id', 'details'];

    protected $casts = ['details' => 'array'];

    public static function noter(User $auteur, string $action, ?Model $cible = null, array $details = []): self
    {
        return static::create([
            'auteur_id' => $auteur->id,
            'action' => $action,
            'cible_type' => $cible ? $cible::class : null,
            'cible_id' => $cible?->getKey(),
            'details' => $details ?: null,
        ]);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
