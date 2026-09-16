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
use App\Http\Controllers\Api\TransfertController;
use App\Http\Controllers\Api\BaremeController;
use App\Http\Controllers\Api\EnvoiController; 


// ---------- PUBLIC ----------
Route::post('/login', [AuthController::class, 'login']);

Route::get('/public/immeubles', [VitrineController::class, 'immeubles']);
Route::get('/public/immeubles/{immeuble}', [VitrineController::class, 'show']);

// ---------- PROTEGE (jeton requis) ----------
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

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
    Route::post('/paiements/{paiement}/envoyer-recu', [PaiementController::class, 'envoyerRecu']);
    Route::post('/paiements/{paiement}/annuler', [PaiementController::class, 'annuler']);

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
    Route::post('/contrats/{contrat}/reinitialiser-acces', [ContratController::class, 'reinitialiserAcces']);

    // Modifier son propre profil (email / mot de passe / nom / telephone)
    Route::put('/profil', [ProfilController::class, 'update']);

    Route::get('/contrats/{contrat}/pdf', [\App\Http\Controllers\Api\ContratController::class, 'pdf']);

    Route::get('/paiements/{paiement}/quittance', [\App\Http\Controllers\Api\PaiementController::class, 'quittance']);


    // --- Module Transferts (Western Union / RIA / Orange Money) ---
    Route::get('/transferts', [TransfertController::class, 'index']);
    Route::post('/transferts', [TransfertController::class, 'store']);
    Route::delete('/transferts/{transfert}', [TransfertController::class, 'destroy']);
    Route::get('/baremes', [BaremeController::class, 'index']);
    Route::put('/baremes', [BaremeController::class, 'update']);

    Route::get('/envois', [EnvoiController::class, 'index']);
Route::post('/envois', [EnvoiController::class, 'store']);
Route::delete('/envois/{envoi}', [EnvoiController::class, 'destroy']);

});