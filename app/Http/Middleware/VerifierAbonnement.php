<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque les agences suspendues ou dont l'essai est termine.
 *
 * Renvoie 402 (Payment Required) : le front distingue ainsi un abonnement
 * expire d'un simple manque de droits (403) ou d'un jeton invalide (401).
 */
class VerifierAbonnement
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Le proprietaire de la plateforme n'est jamais bloque.
        if (! $user || $user->is_platform_admin) {
            return $next($request);
        }

        $agence = $user->agence;

        if (! $agence) {
            return $next($request);
        }

        if ($agence->estActive()) {
            return $next($request);
        }

        $motif = $agence->statut === 'suspendu'
            ? 'suspendu'
            : 'essai_termine';

        return response()->json([
            'message' => $motif === 'suspendu'
                ? "L'acces a ete suspendu. Contactez le support."
                : "Votre periode d'essai est terminee. Choisissez une formule pour continuer.",
            'motif'   => $motif,
            'agence'  => [
                'nom'              => $agence->nom,
                'plan'             => $agence->plan,
                'statut'           => $agence->statut,
                'essai_termine_le' => $agence->essai_termine_le,
            ],
        ], 402);
    }
}
