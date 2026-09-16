<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// L'API (auth:sanctum) redirige les requetes non-authentifiees vers une route
// nommee "login" quand la requete n'envoie pas Accept: application/json (ex:
// appel direct sans ce header). Sans cette route, Laravel plante avec
// "Route [login] not defined." au lieu de renvoyer un 401 propre.
Route::get('/login', fn () => response()->json(['message' => 'Unauthenticated.'], 401))->name('login');
