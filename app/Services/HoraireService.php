<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;

class HoraireService
{
    /**
     * Où en est le vendeur dans sa journée, à l'heure de SON pays
     * (Conakry, Bamako, Paris…) : repos, avant le service, en service ou terminé.
     */
    public function statut(User $user, ?CarbonInterface $maintenant = null): array
    {
        $maintenant = ($maintenant ?? now())->copy()->setTimezone($user->fuseau_horaire ?: config('app.timezone'));
        $horaire = $user->horaires->firstWhere('jour_semaine', $maintenant->dayOfWeekIso);

        if (! $horaire) {
            return ['statut' => 'repos', 'message' => "Aujourd'hui est un jour de repos."];
        }

        $debut = $maintenant->copy()->setTimeFromTimeString($horaire->heure_debut);
        $fin = $maintenant->copy()->setTimeFromTimeString($horaire->heure_fin);

        if ($maintenant->lt($debut)) {
            return [
                'statut' => 'avant_service',
                'message' => "Votre service commence à {$debut->format('H:i')}.",
                'minutes_avant_debut' => (int) $maintenant->diffInMinutes($debut, true),
            ];
        }

        if ($maintenant->lte($fin)) {
            return [
                'statut' => 'en_service',
                'message' => "Il est l'heure de travailler ! Service jusqu'à {$fin->format('H:i')}.",
            ];
        }

        return ['statut' => 'termine', 'message' => "Votre service d'aujourd'hui est terminé."];
    }
}
