<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUtilisateurRequest;
use App\Http\Requests\Admin\UpdateUtilisateurRequest;
use App\Http\Resources\UtilisateurResource;
use App\Models\JournalActivite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gestion de tous les comptes (vendeurs, conseillers, administrateurs).
 * Accessible uniquement par l'administrateur (voir routes/api.php).
 */
class UtilisateurController extends Controller
{
    /**
     * GET /api/admin/utilisateurs?role=vendeur&verrouille=1&recherche=kon
     */
    public function index(Request $request)
    {
        $utilisateurs = User::query()
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->when($request->has('verrouille'), fn ($q) => $q->where('est_verrouille', $request->boolean('verrouille')))
            ->when($request->query('recherche'), function ($q, $texte) {
                $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$texte}%")
                    ->orWhere('prenom', 'like', "%{$texte}%")
                    ->orWhere('identifiant', 'like', "%{$texte}%")
                    ->orWhere('telephone', 'like', "%{$texte}%")
                    ->orWhere('email', 'like', "%{$texte}%"));
            })
            ->orderBy('name')
            ->paginate(50);

        return UtilisateurResource::collection($utilisateurs);
    }

    public function show(User $utilisateur)
    {
        return new UtilisateurResource($utilisateur);
    }

    /**
     * Crée un compte. Si l'administrateur ne fournit pas de mot de passe,
     * un mot de passe est généré. Il est renvoyé UNE SEULE FOIS dans la réponse :
     * en base, il est chiffré (haché) et personne ne peut le relire.
     */
    public function store(StoreUtilisateurRequest $request)
    {
        $donnees = $request->validated();
        $motDePasse = $donnees['password'] ?? Str::password(10, symbols: false);

        $utilisateur = DB::transaction(function () use ($donnees, $motDePasse, $request) {
            $utilisateur = new User(collect($donnees)->except(['password', 'role'])->all());
            $utilisateur->password = $motDePasse;
            $utilisateur->role = Role::from($donnees['role']);
            $utilisateur->save();

            JournalActivite::noter($request->user(), 'compte_cree', $utilisateur);

            return $utilisateur;
        });

        return (new UtilisateurResource($utilisateur))
            ->additional(['mot_de_passe_initial' => $motDePasse])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Modifie nom, prénom, identifiant, email, téléphone, rôle et/ou mot de passe.
     */
    public function update(UpdateUtilisateurRequest $request, User $utilisateur)
    {
        $donnees = $request->validated();

        if ($utilisateur->is($request->user()) && isset($donnees['role']) && $donnees['role'] !== Role::Admin->value) {
            throw ValidationException::withMessages(['role' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.']);
        }

        DB::transaction(function () use ($donnees, $utilisateur, $request) {
            $utilisateur->fill(collect($donnees)->except(['password', 'role'])->all());

            if (isset($donnees['role'])) {
                $utilisateur->role = Role::from($donnees['role']);
            }

            $motDePasseChange = isset($donnees['password']);

            if ($motDePasseChange) {
                $utilisateur->password = $donnees['password'];
            }

            $champs = array_keys($utilisateur->getDirty());
            $utilisateur->save();

            // Nouveau mot de passe : déconnecte tous les appareils de ce compte.
            if ($motDePasseChange) {
                $utilisateur->tokens()->delete();
            }

            JournalActivite::noter($request->user(), 'compte_modifie', $utilisateur, [
                'champs' => array_values(array_diff($champs, ['password'])),
                'mot_de_passe_change' => $motDePasseChange,
            ]);
        });

        return new UtilisateurResource($utilisateur->fresh());
    }

    /**
     * POST /api/admin/utilisateurs/{id}/verrouiller
     * Le vendeur est déconnecté immédiatement sur tous ses appareils.
     */
    public function verrouiller(Request $request, User $utilisateur)
    {
        if ($utilisateur->is($request->user())) {
            abort(422, 'Vous ne pouvez pas verrouiller votre propre compte.');
        }

        $request->validate(['motif' => ['nullable', 'string', 'max:255']]);

        DB::transaction(function () use ($request, $utilisateur) {
            $utilisateur->forceFill(['est_verrouille' => true, 'verrouille_le' => now()])->save();
            $utilisateur->tokens()->delete();

            JournalActivite::noter($request->user(), 'compte_verrouille', $utilisateur, [
                'motif' => $request->input('motif'),
            ]);
        });

        return new UtilisateurResource($utilisateur);
    }

    public function deverrouiller(Request $request, User $utilisateur)
    {
        DB::transaction(function () use ($request, $utilisateur) {
            // Réactivation : on remet aussi à zéro le compteur de tentatives d'intrusion.
            $utilisateur->forceFill([
                'est_verrouille' => false,
                'verrouille_le' => null,
                'tentatives_intrusion' => 0,
            ])->save();

            JournalActivite::noter($request->user(), 'compte_deverrouille', $utilisateur);
        });

        return new UtilisateurResource($utilisateur);
    }

    /**
     * POST /api/admin/utilisateurs/{id}/reinitialiser-mot-de-passe
     * Génère un nouveau mot de passe, affiché une seule fois à l'administrateur.
     */
    public function reinitialiserMotDePasse(Request $request, User $utilisateur)
    {
        $motDePasse = Str::password(10, symbols: false);

        DB::transaction(function () use ($request, $utilisateur, $motDePasse) {
            $utilisateur->password = $motDePasse;
            $utilisateur->save();
            $utilisateur->tokens()->delete();

            JournalActivite::noter($request->user(), 'mot_de_passe_reinitialise', $utilisateur);
        });

        return response()->json([
            'identifiant' => $utilisateur->identifiant,
            'nouveau_mot_de_passe' => $motDePasse,
        ]);
    }
}
