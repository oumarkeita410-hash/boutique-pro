<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
use App\Http\Requests\StoreVenteRequest;
use App\Http\Resources\VenteVendeurResource;
use App\Services\VenteService;

class VenteController extends Controller
{
    public function __construct(private VenteService $ventes)
    {
    }

    /**
     * POST /api/ventes
     */
    public function store(StoreVenteRequest $request)
    {
        $vente = $this->ventes->vendre(
            $request->user(),
            $request->validated('lignes'),
            ModePaiement::from($request->validated('mode_paiement')),
            $request->validated('client_id'),
            $request->validated('uuid_client'),
            [
                'latitude' => $request->validated('latitude'),
                'longitude' => $request->validated('longitude'),
                'precision_m' => $request->validated('precision_m'),
                'ville' => $request->validated('ville'),
                'ip' => $request->ip(),
            ],
            $request->validated('client_nom'),
            $request->validated('client_tel'),
        );

        return (new VenteVendeurResource($vente))
            ->response()
            ->setStatusCode($vente->wasRecentlyCreated ? 201 : 200);
    }
}
