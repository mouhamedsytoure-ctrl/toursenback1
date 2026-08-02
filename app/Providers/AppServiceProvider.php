<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Limite generale par defaut sur toute l'API : par utilisateur connecte,
        // sinon par IP (visiteurs anonymes, vitrine publique).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Limite stricte anti brute-force : connexion et reinitialisation de mot de passe.
        // Cle IP + email tente, pour ne pas bloquer tout le monde a cause d'un seul attaquant.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip() . '|' . $request->input('email'));
        });
    }
}
