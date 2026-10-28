<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === Role::Admin;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'identifiant' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,identifiant'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'telephone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['nullable', Password::min(8)->letters()->numbers()],
            'pays' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'fuseau_horaire' => ['nullable', 'timezone'],
            'frequence_paie' => ['nullable', Rule::in(['jour', 'semaine', 'mois'])],
            'montant_paie' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
