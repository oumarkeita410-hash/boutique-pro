<?php

namespace App\Http\Requests;

use App\Enums\ModePaiement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'lignes' => ['required', 'array', 'min:1', 'max:50'],
            'lignes.*.produit_id' => ['required', 'integer', 'exists:produits,id'],
            'lignes.*.quantite' => ['required', 'integer', 'min:1', 'max:1000'],
            'mode_paiement' => ['required', Rule::enum(ModePaiement::class)],
            'client_id' => ['required_if:mode_paiement,credit', 'nullable', 'integer', 'exists:clients,id'],
            'client_nom' => ['nullable', 'string', 'max:150'],
            'client_tel' => ['nullable', 'string', 'max:30'],
            'uuid_client' => ['nullable', 'uuid'],
            // Position GPS de l'appareil (visible), facultative.
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'precision_m' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'ville' => ['nullable', 'string', 'max:100'],
        ];
    }
}
