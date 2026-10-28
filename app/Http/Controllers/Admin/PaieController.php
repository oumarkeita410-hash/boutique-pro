<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModePaiement;
use App\Enums\Role;
use App\Enums\StatutVente;
use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Models\PaiementVendeur;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Paie des vendeurs par jour, par semaine ou par mois.
 * Chaque vendeur a sa fréquence (frequence_paie) et son montant (montant_paie),
 * fixés par l'administrateur sur son compte.
 */
class PaieController extends Controller
{
    private const FREQUENCES = ['jour', 'semaine', 'mois'];

    /**
     * GET /api/admin/paie?frequence=mois&date=2026-10-01
     * Liste des vendeurs à payer pour cette période, et qui est déjà payé.
     */
    public function index(Request $request)
    {
        $donnees = $request->validate([
            'frequence' => ['required', Rule::in(self::FREQUENCES)],
            'date' => ['nullable', 'date'],
        ]);

        [$debut, $fin] = $this->bornes($donnees['frequence'], $donnees['date'] ?? null);

        $vendeurs = User::where('role', Role::Vendeur)
            ->where('frequence_paie', $donnees['frequence'])
            ->withSum(['ventes as ventes_periode' => fn ($q) => $q
                ->where('statut', StatutVente::Payee)
                ->whereBetween('created_at', [$debut, $fin])], 'montant_total')
            ->with(['paiementsRecus' => fn ($q) => $q
                ->where('frequence', $donnees['frequence'])
                ->whereDate('periode_debut', $debut)])
            ->orderBy('name')
            ->get();

        $lignes = $vendeurs->map(fn (User $v) => [
            'id' => $v->id,
            'nom' => $v->nomComplet(),
            'region' => $v->region,
            'est_verrouille' => $v->est_verrouille,
            'montant_paie' => $v->montant_paie,
            'ventes_periode' => (int) $v->ventes_periode,
            'deja_paye' => $v->paiementsRecus->isNotEmpty(),
            'paiement' => $v->paiementsRecus->first(),
        ]);

        return response()->json([
            'frequence' => $donnees['frequence'],
            'periode_debut' => $debut->toDateString(),
            'periode_fin' => $fin->toDateString(),
            'reste_a_payer' => $lignes->where('deja_paye', false)->sum('montant_paie'),
            'vendeurs' => $lignes,
        ]);
    }

    /**
     * POST /api/admin/paie : paye UN vendeur pour une période.
     * { "user_id": 12, "frequence": "semaine", "date": "2026-10-28",
     *   "mode_paiement": "orange_money", "reference_paiement": "OM…", "montant": 150000 }
     * Sans "montant", le montant de paie du vendeur est utilisé.
     */
    public function store(Request $request)
    {
        $donnees = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', Role::Vendeur->value)],
            'frequence' => ['required', Rule::in(self::FREQUENCES)],
            'date' => ['nullable', 'date'],
            'montant' => ['nullable', 'integer', 'min:1'],
            'mode_paiement' => ['required', Rule::enum(ModePaiement::class)],
            'reference_paiement' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $vendeur = User::findOrFail($donnees['user_id']);
        $paiement = $this->payer($request->user(), $vendeur, $donnees);

        if (! $paiement) {
            throw ValidationException::withMessages(['user_id' => 'Ce vendeur est déjà payé pour cette période.']);
        }

        return response()->json($paiement, 201);
    }

    /**
     * POST /api/admin/paie/tout : paye d'un coup tous les vendeurs non encore
     * payés pour la période (ceux qui ont un montant de paie défini).
     */
    public function payerTout(Request $request)
    {
        $donnees = $request->validate([
            'frequence' => ['required', Rule::in(self::FREQUENCES)],
            'date' => ['nullable', 'date'],
            'mode_paiement' => ['required', Rule::enum(ModePaiement::class)],
        ]);

        $vendeurs = User::where('role', Role::Vendeur)
            ->where('frequence_paie', $donnees['frequence'])
            ->where('montant_paie', '>', 0)
            ->get();

        $payes = $vendeurs->map(fn (User $v) => $this->payer($request->user(), $v, $donnees))->filter();

        return response()->json([
            'nombre_payes' => $payes->count(),
            'montant_total' => $payes->sum('montant'),
            'deja_payes' => $vendeurs->count() - $payes->count(),
        ]);
    }

    /**
     * GET /api/admin/utilisateurs/{utilisateur}/paie : historique d'un vendeur.
     */
    public function historique(User $utilisateur)
    {
        return $utilisateur->paiementsRecus()->latest('periode_debut')->paginate(50);
    }

    /**
     * Vendeur : GET /api/ma-paie
     */
    public function mesPaiements(Request $request)
    {
        return $request->user()->paiementsRecus()
            ->latest('periode_debut')
            ->paginate(50, ['frequence', 'periode_debut', 'periode_fin', 'montant', 'mode_paiement', 'created_at']);
    }

    /**
     * Retourne null si le vendeur est déjà payé pour cette période.
     */
    private function payer(User $admin, User $vendeur, array $donnees): ?PaiementVendeur
    {
        [$debut, $fin] = $this->bornes($donnees['frequence'], $donnees['date'] ?? null);
        $montant = $donnees['montant'] ?? $vendeur->montant_paie;

        if ($montant < 1) {
            throw ValidationException::withMessages([
                'montant' => "Aucun montant de paie défini pour {$vendeur->nomComplet()}.",
            ]);
        }

        try {
            return DB::transaction(function () use ($admin, $vendeur, $donnees, $debut, $fin, $montant) {
                $paiement = PaiementVendeur::create([
                    'user_id' => $vendeur->id,
                    'paye_par' => $admin->id,
                    'frequence' => $donnees['frequence'],
                    'periode_debut' => $debut->toDateString(),
                    'periode_fin' => $fin->toDateString(),
                    'montant' => $montant,
                    'mode_paiement' => $donnees['mode_paiement'],
                    'reference_paiement' => $donnees['reference_paiement'] ?? null,
                    'note' => $donnees['note'] ?? null,
                ]);

                JournalActivite::noter($admin, 'vendeur_paye', $vendeur, [
                    'montant' => $montant,
                    'periode' => "{$paiement->periode_debut->toDateString()} → {$paiement->periode_fin->toDateString()}",
                ]);

                return $paiement;
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function bornes(string $frequence, ?string $date): array
    {
        $jour = $date ? Carbon::parse($date) : now();

        return match ($frequence) {
            'jour' => [$jour->copy()->startOfDay(), $jour->copy()->endOfDay()],
            'semaine' => [$jour->copy()->startOfWeek(Carbon::MONDAY), $jour->copy()->endOfWeek(Carbon::SUNDAY)],
            'mois' => [$jour->copy()->startOfMonth(), $jour->copy()->endOfMonth()],
        };
    }
}
