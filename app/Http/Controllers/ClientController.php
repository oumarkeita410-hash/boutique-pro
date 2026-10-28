<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\JournalActivite;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Vendeurs et conseillers enregistrent les clients venus en boutique.
 * Seul l'administrateur peut les modifier (route sous /admin).
 */
class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()
            ->when($request->query('recherche'), function ($q, $texte) {
                $q->where(fn ($q) => $q
                    ->where('nom', 'like', "%{$texte}%")
                    ->orWhere('prenom', 'like', "%{$texte}%")
                    ->orWhere('telephone', 'like', "%{$texte}%"));
            })
            ->when($request->query('ville'), fn ($q, $ville) => $q->where('ville', $ville))
            ->latest()
            ->paginate(50);

        return response()->json($clients);
    }

    public function show(Client $client)
    {
        return response()->json($client);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate($this->regles());

        $client = Client::create($donnees + ['enregistre_par' => $request->user()->id]);
        JournalActivite::noter($request->user(), 'client_cree', $client);

        return response()->json($client, 201);
    }

    public function update(Request $request, Client $client)
    {
        $donnees = $request->validate($this->regles($client));

        $client->fill($donnees);
        $champs = array_keys($client->getDirty());
        $client->save();

        JournalActivite::noter($request->user(), 'client_modifie', $client, ['champs' => $champs]);

        return response()->json($client);
    }

    private function regles(?Client $client = null): array
    {
        $requis = $client ? 'sometimes' : 'required';

        return [
            'prenom' => [$requis, 'string', 'max:100'],
            'nom' => [$requis, 'string', 'max:100'],
            'telephone' => [$requis, 'string', 'max:30', 'regex:/^\+?[0-9 ]{8,20}$/', Rule::unique('clients', 'telephone')->ignore($client?->id)],
            'email' => ['nullable', 'email', 'max:150'],
            'pays' => [$requis, 'string', 'max:100'],
            'ville' => [$requis, 'string', 'max:100'],
        ];
    }
}
