<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\Immeuble;
use App\Support\Tenant;

class VitrineController extends Controller
{
    // Resout l'agence via son slug et force le tenant courant pour la requete.
    // Sans ca, les modeles avec BelongsToAgence ne seraient pas filtres (aucun utilisateur connecte ici).
    private function resoudreAgence(string $slug): Agence
    {
        $agence = Agence::where('slug', $slug)->firstOrFail();
        Tenant::pour($agence->id);

        return $agence;
    }

    // GET /api/public/{slug}/immeubles  (PUBLIC, sans connexion)
    // Liste des immeubles de l'agence avec le nombre de logements disponibles,
    // plus les infos de l'agence pour le branding du front (nom, logo, telephone).
    public function immeubles(string $slug)
    {
        $agence = $this->resoudreAgence($slug);

        $immeubles = Immeuble::withCount([
            'logements as disponibles_count' => fn ($q) => $q->where('statut', 'disponible'),
        ])->with('medias')->get(['id', 'nom', 'adresse', 'ville', 'photo_couverture']);

        return response()->json([
            'agence' => [
                'nom'       => $agence->nom,
                'logo'      => $agence->logo,
                'telephone' => $agence->telephone,
            ],
            'immeubles' => $immeubles,
        ]);
    }

    // GET /api/public/{slug}/immeubles/{immeuble}  (PUBLIC)
    // Detail public : seulement les logements DISPONIBLES, groupables par etage cote client.
    public function show(string $slug, int $immeuble)
    {
        $agence = $this->resoudreAgence($slug);

        $immeuble = Immeuble::findOrFail($immeuble);
        $immeuble->load([
            'medias',
            'creator:id,telephone',
            'logements' => fn ($q) => $q->where('statut', 'disponible')->with('medias'),
        ]);

        return response()->json([
            'agence' => [
                'nom'       => $agence->nom,
                'logo'      => $agence->logo,
                'telephone' => $agence->telephone,
            ],
            'immeuble' => $immeuble,
        ]);
    }
}
