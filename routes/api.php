<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LocataireController;
use App\Http\Controllers\Api\TerrainController;
use App\Http\Controllers\Api\ImmeubleController;
use App\Http\Controllers\Api\LogementController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\ReclamationController;
use App\Http\Controllers\Api\VitrineController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EspaceLocataireController;
use App\Http\Controllers\Api\ContratController;
use App\Http\Controllers\Api\ProfilController;
use App\Http\Controllers\Api\PlateformeController;
use App\Http\Controllers\Api\AgenceController;
use App\Http\Controllers\Api\AbonnementController;


// ---------- PUBLIC ----------
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/mot-de-passe/oublie', [AuthController::class, 'motDePasseOublie'])->middleware('throttle:auth');
Route::post('/mot-de-passe/reinitialiser', [AuthController::class, 'reinitialiserMotDePasse'])->middleware('throttle:auth');

Route::get('/public/{slug}/immeubles', [VitrineController::class, 'immeubles']);
Route::get('/public/{slug}/immeubles/{immeuble}', [VitrineController::class, 'show']);

// Appele par les serveurs PayDunya (jamais par un navigateur) : pas de jeton disponible ici.
Route::post('/abonnement/webhook', [AbonnementController::class, 'webhook'])->name('paydunya.webhook');

// ---------- PROTEGE (jeton requis) ----------
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profil de l'agence connectee (coordonnees, whatsapp, branding)
    Route::get('/agence', [AgenceController::class, 'show']);
    Route::put('/agence', [AgenceController::class, 'update']);

    // Paiement d'abonnement : volontairement HORS du groupe "abonnement" plus bas,
    // sinon une agence bloquee ne pourrait jamais payer pour se debloquer.
    Route::post('/abonnement/payer', [AbonnementController::class, 'payer']);

    // ---------- CONSOLE PLATEFORME (proprietaire uniquement) ----------
    Route::middleware('plateforme')->prefix('plateforme')->group(function () {
        Route::get('/agences', [PlateformeController::class, 'agences']);
        Route::get('/stats',   [PlateformeController::class, 'stats']);
        Route::put('/agences/{agence}', [PlateformeController::class, 'modifier']);
        Route::post('/agences/{agence}/prolonger', [PlateformeController::class, 'prolonger']);
    });

    // ---------- TOUT LE RESTE EXIGE UN ABONNEMENT VALIDE ----------
    Route::middleware('abonnement')->group(function () {

    // Immeubles
    Route::get('/immeubles', [ImmeubleController::class, 'index']);
    Route::get('/immeubles/{immeuble}', [ImmeubleController::class, 'show']);
    Route::post('/immeubles', [ImmeubleController::class, 'store']);
    Route::put('/immeubles/{immeuble}', [ImmeubleController::class, 'update']);
    Route::delete('/immeubles/{immeuble}', [ImmeubleController::class, 'destroy']);

    // Logements
    Route::get('/logements', [LogementController::class, 'index']);
    Route::get('/logements/{logement}', [LogementController::class, 'show']);
    Route::post('/logements', [LogementController::class, 'store']);
    Route::put('/logements/{logement}', [LogementController::class, 'update']);
    Route::delete('/logements/{logement}', [LogementController::class, 'destroy']);

    // Paiements
    Route::get('/paiements', [PaiementController::class, 'index']);
    Route::post('/paiements', [PaiementController::class, 'store']);
    Route::get('/paiements/{paiement}/recu', [PaiementController::class, 'recu']);

    // Reclamations
    Route::get('/reclamations', [ReclamationController::class, 'index']);
    Route::post('/reclamations', [ReclamationController::class, 'store']);
    Route::put('/reclamations/{reclamation}', [ReclamationController::class, 'update']);

    // Medias
    Route::post('/medias', [MediaController::class, 'store']);
    Route::put('/medias/{media}/couverture', [MediaController::class, 'setCouverture']);
    Route::delete('/medias/{media}', [MediaController::class, 'destroy']);

    // Tableau de bord
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Terrains
    Route::get('/terrains', [TerrainController::class, 'index']);
    Route::post('/terrains', [TerrainController::class, 'store']);
    Route::put('/terrains/{terrain}', [TerrainController::class, 'update']);
    Route::delete('/terrains/{terrain}', [TerrainController::class, 'destroy']);

    // Locataires
    Route::get('/locataires', [LocataireController::class, 'index']);
    Route::post('/locataires', [LocataireController::class, 'store']);

    // Gestion des admins
    Route::get('/admins', [AdminController::class, 'index']);
    Route::post('/admins', [AdminController::class, 'store']);
    Route::put('/admins/{user}/permissions', [AdminController::class, 'updatePermissions']);

    // ⭐ Espace locataire : il voit son loyer et paie lui-meme
    Route::get('/locataire/contrat', [EspaceLocataireController::class, 'contrat']);
    Route::post('/locataire/payer', [EspaceLocataireController::class, 'payer']);

    // Contrats detailles (figes) + pieces + signature + archivage
    Route::get('/contrats', [ContratController::class, 'index']);
    Route::get('/contrats/{contrat}', [ContratController::class, 'show']);
    Route::post('/contrats', [ContratController::class, 'store']);
    Route::get('/contrats/{contrat}/texte', [ContratController::class, 'texte']);
    Route::put('/contrats/{contrat}/resilier', [ContratController::class, 'resilier']);
    Route::put('/contrats/{contrat}/bloquer', [ContratController::class, 'bloquer']);
    Route::put('/contrats/{contrat}/archiver', [ContratController::class, 'archiver']);

    // Modifier son propre profil (email / mot de passe / nom / telephone)
    Route::put('/profil', [ProfilController::class, 'update']);

    Route::get('/contrats/{contrat}/pdf', [\App\Http\Controllers\Api\ContratController::class, 'pdf']);

    Route::get('/paiements/{paiement}/quittance', [\App\Http\Controllers\Api\PaiementController::class, 'quittance']);

    Route::get('/statistiques', [\App\Http\Controllers\Api\StatistiqueController::class, 'index']);
    }); // fin groupe abonnement

});