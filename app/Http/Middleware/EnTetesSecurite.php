<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ajoute des en-têtes de sécurité à chaque réponse du serveur.
 * Protège contre : affichage du site dans un cadre piégé (clickjacking),
 * devinette du type de fichier (MIME sniffing), fuite d'adresses dans le
 * référent, et force le navigateur à rester en HTTPS (en production).
 */
class EnTetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->headers->set('X-Frame-Options', 'DENY');
        $reponse->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $reponse->headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), payment=()');
        $reponse->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // En production (HTTPS), on force le navigateur à ne plus jamais utiliser http.
        if (app()->environment('production') && $request->secure()) {
            $reponse->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $reponse;
    }
}
