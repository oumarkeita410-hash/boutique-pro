<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Models\DemandeModificationVente;
use App\Models\JournalActivite;
use App\Models\User;
use App\Models\Vente;
use App\Notifications\DemandeModificationRecue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Une vente enregistrée ne se modifie pas directement : le vendeur dépose une
 * DEMANDE, que l'administrateur approuve ou refuse. À l'approbation, on garde la
 * trace de ce qui change (avant / après). Champs modifiables : mode de paiement, client.
 * (Changer les quantités reviendrait à toucher le stock : on annule et on refait plutôt.)
 */
class ModificationVenteController extends Controller
{
    /**
     * Vendeur : POST /api/ventes/{vente}/demande-modification
     */
    public function demander(Request $request, Vente $vente)
    {
        $user = $request->user();

        if ($user->role === Role::Vendeur && $vente->vendeur_id !== $user->id) {
            abort(403, 'Cette vente n\'est pas la vôtre.');
        }

        $donnees = $request->validate([
            'mode_paiement' => ['sometimes', Rule::enum(ModePaiement::class)],
            'client_id' => ['sometimes', 'nullable', 'integer', 'exists:clients,id'],
            'motif_demande' => ['required', 'string', 'max:255'],
        ]);

        $changements = collect($donnees)->only(['mode_paiement', 'client_id'])->all();

        if (empty($changements)) {
            throw ValidationException::withMessages(['changements' => 'Indiquez au moins un champ à modifier.']);
        }

        if ($vente->demandesEnAttente()->exists()) {
            throw ValidationException::withMessages(['vente' => 'Une demande est déjà en attente pour cette vente.']);
        }

        $demande = DemandeModificationVente::create([
            'vente_id' => $vente->id,
            'demande_par' => $user->id,
            'changements' => $changements,
            'motif_demande' => $donnees['motif_demande'],
            'statut' => 'en_attente',
        ]);

        $admins = User::where('role', Role::Admin)->where('est_verrouille', false)->get();
        Notification::send($admins, new DemandeModificationRecue($demande->load('vente', 'demandeur')));

        return response()->json(['message' => 'Demande envoyée à l\'administrateur.', 'id' => $demande->id], 201);
    }

    /**
     * Admin : GET /api/admin/modifications-vente (en attente par défaut)
     */
    public function index(Request $request)
    {
        $statut = $request->query('statut', 'en_attente');

        return DemandeModificationVente::where('statut', $statut)
            ->with(['vente:id,reference,montant_total,mode_paiement,statut', 'demandeur:id,name,prenom'])
            ->latest()
            ->paginate(50);
    }

    /**
     * Admin : POST /api/admin/modifications-vente/{demande}/approuver
     */
    public function approuver(Request $request, DemandeModificationVente $demande)
    {
        if ($demande->statut !== 'en_attente') {
            throw ValidationException::withMessages(['demande' => 'Cette demande est déjà traitée.']);
        }

        DB::transaction(function () use ($request, $demande) {
            $vente = Vente::whereKey($demande->vente_id)->lockForUpdate()->firstOrFail();

            $champs = array_keys($demande->changements);
            $avant = collect($champs)->mapWithKeys(fn ($c) => [$c => $this->valeurLisible($vente->$c)])->all();

            foreach ($demande->changements as $champ => $valeur) {
                $vente->$champ = $valeur;
            }
            $vente->save();

            $apres = collect($champs)->mapWithKeys(fn ($c) => [$c => $this->valeurLisible($vente->$c)])->all();

            $demande->update([
                'statut' => 'approuve',
                'traite_par' => $request->user()->id,
                'traite_le' => now(),
                'avant' => $avant,
                'apres' => $apres,
            ]);

            // Trace visible de ce qui a été modifié après la vente.
            JournalActivite::noter($request->user(), 'vente_modifiee', $vente, [
                'reference' => $vente->reference,
                'demande_par' => $demande->demandeur->nomComplet(),
                'avant' => $avant,
                'apres' => $apres,
            ]);
        });

        return response()->json(['message' => 'Modification approuvée et appliquée.', 'demande' => $demande->fresh()]);
    }

    /**
     * Admin : POST /api/admin/modifications-vente/{demande}/refuser
     */
    public function refuser(Request $request, DemandeModificationVente $demande)
    {
        if ($demande->statut !== 'en_attente') {
            throw ValidationException::withMessages(['demande' => 'Cette demande est déjà traitée.']);
        }

        $motif = $request->validate(['motif_refus' => ['nullable', 'string', 'max:255']])['motif_refus'] ?? null;

        $demande->update([
            'statut' => 'refuse',
            'traite_par' => $request->user()->id,
            'traite_le' => now(),
            'motif_refus' => $motif,
        ]);

        JournalActivite::noter($request->user(), 'modification_refusee', $demande->vente, ['motif' => $motif]);

        return response()->json(['message' => 'Demande refusée.']);
    }

    private function valeurLisible($valeur): mixed
    {
        return $valeur instanceof \BackedEnum ? $valeur->value : $valeur;
    }
}
