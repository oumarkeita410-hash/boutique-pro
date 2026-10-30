<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Models\Devis;
use App\Models\JournalActivite;
use App\Services\VenteService;
use App\Support\Boutique;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Devis (proposition de prix). Vendeurs et administrateur peuvent en créer.
 * Un devis ne touche pas le stock ; il peut être converti en vente ensuite.
 */
class DevisController extends Controller
{
    public function __construct(private VenteService $ventes)
    {
    }

    public function index(Request $request)
    {
        return Devis::query()
            ->when($request->user()->role === Role::Vendeur, fn ($q) => $q->where('cree_par', $request->user()->id))
            ->with(['client:id,nom,prenom', 'createur:id,name,prenom'])
            ->latest()
            ->paginate(50)
            ->through(fn (Devis $d) => [
                'id' => $d->id,
                'reference' => $d->reference,
                'client' => $d->nomClient(),
                'createur' => $d->createur?->prenom.' '.$d->createur?->name,
                'montant_total' => $d->montant_total,
                'statut' => $d->statut,
                'date' => $d->created_at->toDateTimeString(),
            ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_nom' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:500'],
            'valide_jusqu' => ['nullable', 'date'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => ['nullable', 'integer', 'exists:produits,id'],
            'lignes.*.designation' => ['required', 'string', 'max:200'],
            'lignes.*.quantite' => ['required', 'integer', 'min:1'],
            'lignes.*.prix_unitaire' => ['required', 'integer', 'min:0'],
        ]);

        $devis = DB::transaction(function () use ($donnees, $request) {
            $devis = Devis::create([
                'reference' => 'DV-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'client_id' => $donnees['client_id'] ?? null,
                'client_nom' => $donnees['client_nom'] ?? null,
                'cree_par' => $request->user()->id,
                'statut' => 'brouillon',
                'note' => $donnees['note'] ?? null,
                'valide_jusqu' => $donnees['valide_jusqu'] ?? null,
                'montant_total' => 0,
            ]);

            $total = 0;
            foreach ($donnees['lignes'] as $l) {
                $sous = $l['prix_unitaire'] * $l['quantite'];
                $devis->lignes()->create([
                    'produit_id' => $l['produit_id'] ?? null,
                    'designation' => $l['designation'],
                    'quantite' => $l['quantite'],
                    'prix_unitaire' => $l['prix_unitaire'],
                    'sous_total' => $sous,
                ]);
                $total += $sous;
            }
            $devis->update(['montant_total' => $total]);
            JournalActivite::noter($request->user(), 'devis_cree', $devis, ['reference' => $devis->reference, 'montant' => $total]);

            return $devis;
        });

        return response()->json($this->formatComplet($devis->fresh('lignes')), 201);
    }

    public function show(Request $request, Devis $devis)
    {
        if ($request->user()->role === Role::Vendeur && $devis->cree_par !== $request->user()->id) {
            abort(403);
        }

        return response()->json($this->formatComplet($devis->load('lignes', 'client', 'createur')));
    }

    /**
     * POST /api/devis/{devis}/convertir  { "mode_paiement": "especes" }
     * Crée une vraie vente à partir du devis (diminue le stock).
     */
    public function convertir(Request $request, Devis $devis)
    {
        if ($devis->statut === 'converti') {
            throw ValidationException::withMessages(['devis' => 'Ce devis est déjà converti en vente.']);
        }

        $mode = $request->validate(['mode_paiement' => ['required', Rule::enum(ModePaiement::class)]])['mode_paiement'];

        $lignes = $devis->lignes()->get();
        if ($lignes->whereNull('produit_id')->isNotEmpty()) {
            throw ValidationException::withMessages([
                'devis' => 'Ce devis contient des lignes libres (sans produit du catalogue). Créez la vente manuellement.',
            ]);
        }

        $vente = $this->ventes->vendre(
            $request->user(),
            $lignes->map(fn ($l) => ['produit_id' => $l->produit_id, 'quantite' => $l->quantite])->all(),
            ModePaiement::from($mode),
            $devis->client_id,
        );

        $devis->update(['statut' => 'converti', 'vente_id' => $vente->id]);
        JournalActivite::noter($request->user(), 'devis_converti', $devis, ['vente' => $vente->reference]);

        return response()->json(['message' => 'Devis converti en vente.', 'vente_id' => $vente->id, 'reference_vente' => $vente->reference]);
    }

    public function changerStatut(Request $request, Devis $devis)
    {
        $statut = $request->validate([
            'statut' => ['required', Rule::in(['brouillon', 'envoye', 'accepte', 'refuse'])],
        ])['statut'];

        $devis->update(['statut' => $statut]);

        return response()->json(['statut' => $devis->statut]);
    }

    private function formatComplet(Devis $devis): array
    {
        return [
            'id' => $devis->id,
            'reference' => $devis->reference,
            'date' => $devis->created_at->toDateTimeString(),
            'valide_jusqu' => $devis->valide_jusqu?->toDateString(),
            'statut' => $devis->statut,
            'client' => $devis->nomClient(),
            'createur' => trim(($devis->createur?->prenom ?? '').' '.($devis->createur?->name ?? '')),
            'note' => $devis->note,
            'montant_total' => $devis->montant_total,
            'boutique' => Boutique::entete(),
            'lignes' => $devis->lignes->map(fn ($l) => [
                'designation' => $l->designation,
                'quantite' => $l->quantite,
                'prix_unitaire' => $l->prix_unitaire,
                'sous_total' => $l->sous_total,
            ]),
        ];
    }
}
