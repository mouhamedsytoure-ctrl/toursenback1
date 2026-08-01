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

        $attendu = (float) Contrat::where('statut', 'actif')->sum('montant_loyer');

        $impayes = Paiement::where('periode', $periode)
            ->where('statut', '!=', 'paye');

        $totalLogements = Logement::count();
        $loues          = Logement::where('statut', 'loue')->count();

        // User n'a pas de scope automatique : filtre explicite (sauf compte plateforme)
        $locatairesQuery = User::where('role', 'locataire');
        if (! $request->user()->is_platform_admin) {
            $locatairesQuery->where('agence_id', $request->user()->agence_id);
        }

        return response()->json([
            'periode'            => $periode,
            'encaisse'           => $encaisse,
            'attendu'            => $attendu,
            'taux_occupation'    => $totalLogements > 0 ? round($loues / $totalLogements * 100) : 0,
            'logements_loues'    => $loues,
            'logements_total'    => $totalLogements,
            'impayes_nombre'     => (clone $impayes)->count(),
            'impayes_montant'    => (float) (clone $impayes)->sum('montant'),
            'reclamations_ouvertes' => Reclamation::where('statut', '!=', 'resolu')->count(),
            'nb_immeubles'       => Immeuble::count(),
            'nb_locataires'      => $locatairesQuery->count(),
        ]);
    }
}
