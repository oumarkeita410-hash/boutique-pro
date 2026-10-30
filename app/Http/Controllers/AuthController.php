<?php

namespace App\Http\Controllers;

use App\Http\Resources\UtilisateurResource;
use App\Models\JournalActivite;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * GET /api/comptes-connexion?role=vendeur  (public)
     * Liste les comptes d'un rôle (nom + identifiant) pour l'écran de connexion.
     * Le mot de passe reste obligatoire pour se connecter.
     */
    public function comptesConnexion(Request $request)
    {
        $role = $request->query('role');
        if (! in_array($role, ['vendeur', 'conseiller', 'admin'], true)) {
            return response()->json([]);
        }

        return User::where('role', $role)
            ->where('est_verrouille', false)
            ->orderBy('prenom')->orderBy('name')
            ->get(['name', 'prenom', 'identifiant'])
            ->map(fn (User $u) => [
                'nom' => trim($u->prenom.' '.$u->name),
                'identifiant' => $u->identifiant,
            ]);
    }

    /**
     * POST /api/connexion
     * Tous les comptes (admin, vendeur, conseiller) se connectent ici avec
     * l'identifiant et le mot de passe fournis par l'administrateur.
     */
    public function connexion(Request $request)
    {
        $donnees = $request->validate([
            // L'utilisateur peut se connecter avec son identifiant, son téléphone ou son email.
            'identifiant' => ['required', 'string'],
            'password' => ['required', 'string'],
            'appareil' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'precision_m' => ['nullable', 'integer', 'min:0', 'max:50000'],
        ]);

        $saisie = trim($donnees['identifiant']);
        $utilisateur = User::where('identifiant', $saisie)
            ->orWhere('telephone', $saisie)
            ->orWhere('email', $saisie)
            ->first();

        // Même message si l'identifiant ou le mot de passe est faux :
        // on ne révèle pas quels identifiants existent.
        if (! $utilisateur || ! Hash::check($donnees['password'], $utilisateur->password)) {
            throw ValidationException::withMessages(['identifiant' => 'Identifiant ou mot de passe incorrect.']);
        }

        if ($utilisateur->est_verrouille) {
            throw ValidationException::withMessages(['identifiant' => "Votre compte est verrouillé. Contactez l'administrateur."]);
        }

        $utilisateur->forceFill(['derniere_connexion' => now()])->save();
        JournalActivite::noter($utilisateur, 'connexion', $utilisateur, ['ip' => $request->ip()]);

        // Localisation enregistrée à la connexion (le vendeur en est informé, voir "avis" ci-dessous).
        Position::create([
            'user_id' => $utilisateur->id,
            'contexte' => 'connexion',
            'latitude' => $donnees['latitude'] ?? null,
            'longitude' => $donnees['longitude'] ?? null,
            'precision_m' => $donnees['precision_m'] ?? null,
            'region' => $utilisateur->region,
            'pays' => $utilisateur->pays,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'token' => $utilisateur->createToken($donnees['appareil'] ?? 'appareil')->plainTextToken,
            'utilisateur' => new UtilisateurResource($utilisateur),
            'avis' => 'Votre position et votre appareil sont enregistrés par MOK-Business pendant l\'utilisation de l\'application.',
        ]);
    }

    /**
     * POST /api/deconnexion
     */
    public function deconnexion(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * GET /api/moi
     */
    public function moi(Request $request)
    {
        return new UtilisateurResource($request->user());
    }
}
