<?php

namespace App\Exceptions;

use App\Models\Produit;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class StockInsuffisantException extends Exception
{
    public function __construct(public readonly Produit $produit, public readonly int $quantiteDemandee)
    {
        parent::__construct("Stock insuffisant pour « {$produit->nom} ».");
    }

    /**
     * Le détail du stock reste dans les logs (pour l'administrateur),
     * jamais dans la réponse envoyée au vendeur.
     */
    public function report(): void
    {
        Log::warning('Vente refusée : stock insuffisant', [
            'produit_id' => $this->produit->id,
            'stock' => $this->produit->stock,
            'demande' => $this->quantiteDemandee,
        ]);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
