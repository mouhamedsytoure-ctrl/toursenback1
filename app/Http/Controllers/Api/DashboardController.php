<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Immeuble;
use App\Models\Logement;
use App\Models\Paiement;
use App\Models\Reclamation;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // GET /api/dashboard  (super admin / admin)
    public function index(Request $request)
    {
        abort_if($request->user()->isLocataire(), 403);

        $periode = now()->format('Y-m'); // ex: 2026-06

        $encaisse = (float) Paiement::where('periode', $periode)
            ->where('statut', 'paye')->sum('montant');

        $contratsActifs = Contrat::where('statut', 'actif')->get(['id', 'montant_loyer', 'date_debut']);
        $attendu = (float) $contratsActifs->sum('montant_loyer');

        // Un locataire est "impaye" des qu'aucun paiement "paye" n'existe pour
        // lui ce mois-ci (aucune ligne n'est creee tant que personne n'a
        // confirme le paiement : chercher un statut different de "paye" ne
        // trouve donc jamais rien, contrairement a une comparaison par
        // absence de contrat_id parmi les paiements payes). Le mois d'entree
        // n'est jamais compte comme impaye : il est deja couvert par la
        // caution versee a la signature.
        $contratIdsPayes = Paiement::where('periode', $periode)
            ->where('statut', 'paye')
            ->pluck('contrat_id');
        $impayesContrats = $contratsActifs
            ->reject(fn ($c) => $c->date_debut?->format('Y-m') === $periode)
            ->whereNotIn('id', $contratIdsPayes);

        $totalLogements = Logement::count();
        $loues          = Logement::where('statut', 'loue')->count();

        return response()->json([
            'periode'            => $periode,
            'encaisse'           => $encaisse,
            'attendu'            => $attendu,
            'taux_occupation'    => $totalLogements > 0 ? round($loues / $totalLogements * 100) : 0,
            'logements_loues'    => $loues,
            'logements_total'    => $totalLogements,
            'impayes_nombre'     => $impayesContrats->count(),
            'impayes_montant'    => (float) $impayesContrats->sum('montant_loyer'),
            'reclamations_ouvertes' => Reclamation::where('statut', '!=', 'resolu')->count(),
            'nb_immeubles'       => Immeuble::count(),
            'nb_locataires'      => User::where('role', 'locataire')->count(),
        ]);
    }
}
