<?php

namespace App\Models;

use App\Enums\ModePaiement;
use App\Enums\StatutVente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vente extends Model
{
    protected $table = 'ventes';

    protected $fillable = [
        'reference', 'uuid_client', 'vendeur_id', 'client_id',
        'mode_paiement', 'reference_paiement', 'statut', 'montant_total', 'montant_paye',
    ];

    protected $casts = [
        'mode_paiement' => ModePaiement::class,
        'statut' => StatutVente::class,
        'montant_total' => 'integer',
        'montant_paye' => 'integer',
    ];

    public function reste(): int
    {
        return $this->montant_total - $this->montant_paye;
    }

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendeur_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneVente::class);
    }

    public function demandesModification(): HasMany
    {
        return $this->hasMany(DemandeModificationVente::class);
    }

    public function demandesEnAttente(): HasMany
    {
        return $this->demandesModification()->where('statut', 'en_attente');
    }
}
