<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\Immeuble;
use App\Models\Scopes\AgenceScope;
use App\Support\Tenant;
use Illuminate\Http\Request;

class VitrineController extends Controller
{
    // Resout l'agence via son slug et force le tenant courant pour la requete.
    // Sans ca, les modeles avec BelongsToAgence ne seraient pas filtres (aucun utilisateur connecte ici).
    private function resoudreAgence(string $slug): Agence
    {
        $agence = Agence::where('slug', $slug)->firstOrFail();

        // Vitrine en ligne = fonctionnalite payante : une agence suspendue ou dont
        // l'essai est termine ne doit plus recruter de nouveaux locataires publiquement.
        abort_unless($agence->estActive(), 404);

        Tenant::pour($agence->id);

        return $agence;
    }

    // Branding + coordonnees de contact renvoyes a la vitrine publique.
    // whatsapp reprend le telephone si aucun numero WhatsApp distinct n'est renseigne.
    private function brandingAgence(Agence $agence): array
    {
        return [
            'nom'       => $agence->nom,
            'logo'      => $agence->logo,
            'telephone' => $agence->telephone,
            'whatsapp'  => $agence->whatsapp ?: $agence->telephone,
        ];
    }

    // GET /api/public/{slug}/immeubles  (PUBLIC, sans connexion)
    // Liste des immeubles de l'agence avec le nombre de logements disponibles,
    // plus les infos de l'agence pour le branding du front (nom, logo, telephone).
    public function immeubles(string $slug)
    {
        $agence = $this->resoudreAgence($slug);

        $immeubles = Immeuble::withCount([
            'logements as disponibles_count' => fn ($q) => $q->where('statut', 'disponible'),
        ])->with('medias')
            ->orderByDesc('mis_en_avant')
            ->get(['id', 'nom', 'adresse', 'ville', 'photo_couverture', 'mis_en_avant']);

        return response()->json([
            'agence'    => $this->brandingAgence($agence),
            'immeubles' => $immeubles,
        ]);
    }

    /**
     * GET /api/public/annuaire  (PUBLIC, sans connexion)
     * Tous les immeubles de toutes les agences ACTIVES, tous confondus --
     * la vitrine globale de la plateforme, distincte de la vitrine par agence.
     * Filtres optionnels : ville (zone), prix_min, prix_max (sur les logements
     * disponibles). Les immeubles mis en avant (plan VIP) sortent en tete.
     */
    public function annuaire(Request $request)
    {
        // estActive() combine plusieurs regles (essai, abonnement, statut) : plus
        // simple et plus sur de le recalculer en PHP que de le dupliquer en SQL.
        $agencesActives = Agence::all()->filter(fn ($a) => $a->estActive())->pluck('id');

        $query = Immeuble::withoutGlobalScope(AgenceScope::class)
            ->whereIn('agence_id', $agencesActives)
            ->with(['agence:id,nom,slug,logo', 'medias'])
            ->withCount([
                'logements as disponibles_count' => fn ($q) => $q->where('statut', 'disponible'),
            ]);

        if ($request->filled('ville')) {
            $query->where('ville', 'like', '%' . $request->ville . '%');
        }

        if ($request->filled('prix_min') || $request->filled('prix_max')) {
            $query->whereHas('logements', function ($q) use ($request) {
                $q->where('statut', 'disponible');
                if ($request->filled('prix_min')) {
                    $q->where('loyer', '>=', (float) $request->prix_min);
                }
                if ($request->filled('prix_max')) {
                    $q->where('loyer', '<=', (float) $request->prix_max);
                }
            });
        }

        $immeubles = $query->orderByDesc('mis_en_avant')->latest()
            ->get(['id', 'agence_id', 'nom', 'adresse', 'ville', 'photo_couverture', 'mis_en_avant']);

        return response()->json($immeubles);
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
            'agence'   => $this->brandingAgence($agence),
            'immeuble' => $immeuble,
        ]);
    }
}
