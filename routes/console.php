<?php

use App\Enums\Role;
use App\Models\Reparateur;
use App\Models\User;
use App\Notifications\RappelTravail;
use App\Services\HoraireService;
use App\Services\ReparateurService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Chaque matin, rappelle à l'administrateur les réparateurs qui doivent plus que le seuil.
Artisan::command('reparateurs:rappel-dettes', function (ReparateurService $service) {
    $seuil = config('boutique.seuil_dette_reparateur');
    $envoyes = 0;

    foreach (Reparateur::avecDette()->where('actif', true)->get() as $reparateur) {
        if ($reparateur->dette > $seuil) {
            $service->alerterAdministrateurs($reparateur, (int) $reparateur->dette);
            $envoyes++;
        }
    }

    $this->info("{$envoyes} rappel(s) envoyé(s).");
})->purpose('Alerte l\'administrateur des dettes réparateurs au-dessus du seuil');

// « Il est l'heure de travailler » : envoyé une fois par jour à chaque vendeur,
// dans les 15 minutes avant le début de son service (à l'heure de son pays).
Artisan::command('vendeurs:rappel-travail', function (HoraireService $horaires) {
    $envoyes = 0;

    User::where('role', Role::Vendeur)
        ->where('est_verrouille', false)
        ->whereHas('horaires')
        ->with('horaires')
        ->chunk(200, function ($vendeurs) use ($horaires, &$envoyes) {
            foreach ($vendeurs as $vendeur) {
                $statut = $horaires->statut($vendeur);

                $aRappeler = $statut['statut'] === 'en_service'
                    || ($statut['statut'] === 'avant_service' && $statut['minutes_avant_debut'] <= 15);

                $jour = now($vendeur->fuseau_horaire)->toDateString();

                // Cache::add ne réussit qu'une fois par clé : un seul rappel par jour.
                if ($aRappeler && Cache::add("rappel-travail:{$vendeur->id}:{$jour}", true, now()->addDay())) {
                    $vendeur->notify(new RappelTravail("Il est l'heure de travailler ! {$statut['message']}"));
                    $envoyes++;
                }
            }
        });

    $this->info("{$envoyes} rappel(s) envoyé(s).");
})->purpose('Rappelle aux vendeurs que leur service commence');

Schedule::command('reparateurs:rappel-dettes')->dailyAt('08:00')->timezone('Africa/Conakry');
Schedule::command('vendeurs:rappel-travail')->everyFiveMinutes();
