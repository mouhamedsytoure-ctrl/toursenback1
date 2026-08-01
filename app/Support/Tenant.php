<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Retient l'agence courante pour la duree de la requete.
 *
 * Ordre de resolution :
 * 1. Une agence forcee explicitement via Tenant::pour($id) (routes publiques, console)
 * 2. Aucun filtre si l'utilisateur connecte est is_platform_admin
 * 3. L'agence de l'utilisateur connecte
 * 4. Aucune agence deduite (null = pas de filtre)
 */
class Tenant
{
    private static ?int $agenceForcee = null;
    private static bool $forcee = false;

    public static function pour(?int $agenceId): void
    {
        self::$agenceForcee = $agenceId;
        self::$forcee = true;
    }

    public static function reinitialiser(): void
    {
        self::$agenceForcee = null;
        self::$forcee = false;
    }

    public static function agenceId(): ?int
    {
        if (self::$forcee) {
            return self::$agenceForcee;
        }

        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if ($user->is_platform_admin) {
            return null;
        }

        return $user->agence_id;
    }
}
