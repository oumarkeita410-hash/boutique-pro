<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === Role::Admin;
    }

    public function rules(): array
    {
        $id = $this->route('utilisateur')->id;

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'prenom' => ['sometimes', 'string', 'max:100'],
            'identifiant' => ['sometimes', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'identifiant')->ignore($id)],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'telephone' => ['sometimes', 'string', 'max:30', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'role' => ['sometimes', Rule::enum(Role::class)],
            'password' => ['sometimes', Password::min(8)->letters()->numbers()],
            'pays' => ['sometimes', 'nullable', 'string', 'max:100'],
            'region' => ['sometimes', 'nullable', 'string', 'max:100'],
            'fuseau_horaire' => ['sometimes', 'timezone'],
            'frequence_paie' => ['sometimes', 'nullable', Rule::in(['jour', 'semaine', 'mois'])],
            'montant_paie' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
