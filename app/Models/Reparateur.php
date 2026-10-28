<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Reparateur extends Model
{
    protected $table = 'reparateurs';

    protected $fillable = ['prenom', 'nom', 'telephone', 'email', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    // Actif par défaut dès la création, sans attendre un rechargement depuis la base.
    protected $attributes = ['actif' => true];

    public function prises(): HasMany
    {
        return $this->hasMany(PriseReparateur::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementReparateur::class);
    }

    public function nomComplet(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }

    /**
     * Ce que le réparateur doit encore à la boutique (GNF).
     */
    public function calculerDette(): int
    {
        return (int) $this->prises()->sum(DB::raw('montant_total - montant_paye'));
    }

    /**
     * Sous-requête « dette » réutilisable pour trier/filtrer en base.
     */
    public static function sousRequeteDette()
    {
        return PriseReparateur::selectRaw('COALESCE(SUM(montant_total - montant_paye), 0)')
            ->whereColumn('prises_reparateur.reparateur_id', 'reparateurs.id');
    }

    /**
     * Ajoute la colonne calculée `dette` à chaque réparateur chargé.
     */
    public function scopeAvecDette(Builder $query): void
    {
        $query->select('reparateurs.*')->selectSub(self::sousRequeteDette(), 'dette');
    }
}
