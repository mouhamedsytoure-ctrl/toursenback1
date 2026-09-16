<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API pure : on rend toujours les erreurs en JSON, meme si la requete
        // n'envoie pas Accept: application/json (evite qu'une erreur de
        // validation redirige silencieusement au lieu de renvoyer le detail).
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
