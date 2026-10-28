<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Exceptions\CompteVerrouilleException;
use App\Models\JournalActivite;
use App\Models\User;
use App\Notifications\IntrusionDetectee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage : ->middleware('role:vendeur,admin')
 * - Bloque tout compte verrouillé.
 * - Si un vendeur tente d'ouvrir une zone réservée à l'administrateur, la tentative
 *   est enregistrée et signalée. Au bout de 3 tentatives, le compte est bloqué
 *   automatiquement ; seul l'administrateur peut le réactiver.
 */
class VerifierRole
{
    private const SEUIL_BLOCAGE = 3;

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->est_verrouille) {
            throw new CompteVerrouilleException();
        }

        if (! in_array($user->role->value, $roles, true)) {
            // Tentative d'accès à une zone admin par un non-admin → intrusion.
            if (in_array('admin', $roles, true) && $user->role !== Role::Admin) {
                $this->signalerIntrusion($user, $request);
            }

            abort(403, 'Accès non autorisé pour ce compte.');
        }

        return $next($request);
    }

    private function signalerIntrusion(User $user, Request $request): void
    {
        $bloque = false;

        DB::transaction(function () use ($user, $request, &$bloque) {
            // Verrouille la ligne pour compter juste, même en cas d'essais simultanés.
            $frais = User::whereKey($user->id)->lockForUpdate()->first();
            $frais->increment('tentatives_intrusion');

            JournalActivite::noter($frais, 'tentative_intrusion', $frais, [
                'chemin' => $request->path(),
                'methode' => $request->method(),
                'ip' => $request->ip(),
                'tentative' => $frais->tentatives_intrusion,
            ]);

            if ($frais->tentatives_intrusion >= self::SEUIL_BLOCAGE) {
                $frais->forceFill(['est_verrouille' => true, 'verrouille_le' => now()])->save();
                $frais->tokens()->delete();
                $bloque = true;
            }

            $user->setRawAttributes($frais->getAttributes());
        });

        $admins = User::where('role', Role::Admin)->where('est_verrouille', false)->get();
        Notification::send($admins, new IntrusionDetectee($user, $request->path(), $bloque));
    }
}
