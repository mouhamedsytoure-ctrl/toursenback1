<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EspaceLocataireController extends Controller
{
    private function periodeCourante(): string
    {
        return now()->format('Y-m'); // ex : 2026-06
    }

    private function contratActif(Request $request): ?Contrat
    {
        return Contrat::where('user_id', $request->user()->id)
            ->where('statut', 'actif')
            ->with('logement.immeuble')
            ->latest()
            ->first();
    }

    /**
     * GET /api/locataire/contrat
     * Le locataire voit son logement, son loyer, son statut du mois et son historique.
     */
    public function contrat(Request $request)
    {
        $contrat = $this->contratActif($request);
        if (! $contrat) {
            return response()->json(['contrat' => null, 'paiements' => [], 'periode' => $this->periodeCourante(), 'paye_ce_mois' => false]);
        }

        $paiements = Paiement::where('contrat_id', $contrat->id)->latest()->get();
        $periode = $this->periodeCourante();
        // Le mois d'entree n'est jamais signale comme impaye : il est deja
        // couvert par la caution versee a la signature (hors application).
        $premierMois = $contrat->date_debut?->format('Y-m') === $periode;
        $paye = $premierMois || $paiements->contains(fn ($p) => $p->periode === $periode && $p->statut === 'paye');

        return response()->json([
            'contrat'      => $contrat,
            'paiements'    => $paiements,
            'periode'      => $periode,
            'paye_ce_mois' => $paye,
        ]);
    }

    /**
     * POST /api/locataire/payer
     * Le LOCATAIRE paie lui-meme son loyer (Wave / Orange Money / Especes).
     * Paiement automatique simule : passe directement a "paye" et genere le recu.
     * (La vraie integration Wave/Orange Money se branchera ici plus tard.)
     */
    public function payer(Request $request)
    {
        $data = $request->validate([
            'mode_paiement' => ['required', 'in:wave,orange_money,especes'],
            'periode'       => ['nullable', 'string', 'max:255'],
        ]);

        $contrat = $this->contratActif($request);
        if (! $contrat) {
            return response()->json(['message' => "Aucun contrat actif pour ce compte."], 404);
        }

        $periode = $data['periode'] ?? $this->periodeCourante();

        // Empeche de payer deux fois le meme mois
        $dejaPaye = Paiement::where('contrat_id', $contrat->id)
            ->where('periode', $periode)
            ->where('statut', 'paye')
            ->exists();
        if ($dejaPaye) {
            return response()->json(['message' => "Le loyer de $periode est deja paye."], 409);
        }

        $paiement = Paiement::create([
            'contrat_id'            => $contrat->id,
            'periode'               => $periode,
            'montant'               => $contrat->montant_loyer,
            'mode_paiement'         => $data['mode_paiement'],
            'statut'                => 'paye', // simule : automatique
            'reference_transaction' => 'SIM-' . strtoupper(Str::random(10)),
            'enregistre_par'        => $request->user()->id,
        ]);

        return response()->json([
            'message'  => 'Paiement effectue.',
            'paiement' => $paiement->fresh(),
        ], 201);
    }
}
