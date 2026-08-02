<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PaiementController extends Controller
{
    // GET /api/paiements?contrat_id=&statut=
    public function index(Request $request)
    {
        $query = Paiement::query()->with('contrat.locataire', 'contrat.logement');

        // Un locataire ne voit que SES paiements
        if ($request->user()->isLocataire()) {
            $query->whereHas('contrat', fn ($q) => $q->where('user_id', $request->user()->id));
        }
        if ($request->filled('contrat_id')) {
            $query->where('contrat_id', $request->contrat_id);
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        return response()->json($query->latest()->get());
    }

    // POST /api/paiements  (l'admin enregistre un paiement)
    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('loyers', 'create'), 403);

        $data = $request->validate([
            'contrat_id'    => ['required', 'exists:contrats,id'],
            'periode'       => ['required', 'string', 'max:255'],
            'montant'       => ['required', 'numeric', 'min:0'],
            'mode_paiement' => ['required', 'in:wave,orange_money,especes'],
            'statut'        => ['nullable', 'in:paye,en_attente,retard,impaye'],
        ]);
        $data['statut'] = $data['statut'] ?? 'paye';
        $data['enregistre_par'] = $request->user()->id;

        $paiement = Paiement::create($data);
        return response()->json($paiement->fresh(), 201);
    }

    // GET /api/paiements/{paiement}/recu  -> donnees du recu (JSON)
    public function recu(Paiement $paiement)
    {
        $paiement->load('contrat.locataire', 'contrat.logement.immeuble');

        return response()->json([
            'agence'        => 'Toursen Immobilier',
            'numero'        => $paiement->recu_numero,
            'date'          => $paiement->date_paiement,
            'locataire'     => $paiement->contrat->locataire->name,
            'logement'      => $paiement->contrat->logement->immeuble->nom
                                . ' - ' . $paiement->contrat->logement->reference,
            'periode'       => $paiement->periode,
            'montant'       => $paiement->montant,
            'mode_paiement' => $paiement->mode_paiement,
            'statut'        => $paiement->statut,
        ]);
    }

    // GET /api/paiements/{paiement}/quittance  -> QUITTANCE en PDF (style agence)
    public function quittance(Paiement $paiement)
    {
        $paiement->load('contrat.locataire', 'contrat.logement.immeuble');
        $c  = $paiement->contrat;
        $lg = $c?->logement;
        $im = $lg?->immeuble;

        $nom = $c?->locataire?->name
            ?: trim(($c->preneur_prenom ?? '') . ' ' . ($c->preneur_nom ?? ''));
        if ($nom === '') $nom = '__________';

        $adresse = trim(($im?->adresse ?: '') . ' ' . ($im?->ville ?: ''));
        if ($adresse === '') $adresse = 'Dakar';

        $dateObj = $paiement->date_paiement ?? $paiement->created_at;
        $date = $dateObj ? \Illuminate\Support\Carbon::parse($dateObj)->format('d/m/Y') : now()->format('d/m/Y');

        // Chaque agence emet ses quittances a son propre nom.
        $ag = $paiement->agence ?: request()->user()?->agence;
        $A  = $ag ? $ag->entete() : [];

        $data = [
            'agence'  => $A,
            'logo'    => $A['logo'] ?? null,
            'nom'     => $nom,
            'adresse' => $adresse,
            'mois'    => $this->moisFr($paiement->periode),
            'numero'  => $paiement->recu_numero ?: $paiement->id,
            'montant' => number_format((float) $paiement->montant, 0, '.', ','),
            'date'    => $date,
            'ville'   => $im?->ville ?: 'Dakar',
        ];

        return Pdf::loadView('paiements.quittance', $data)
            ->setPaper('a4')
            ->stream('quittance_' . $paiement->id . '.pdf');
    }

    // "2026-06" -> "JUIN 2026"
    private function moisFr(?string $periode): string
    {
        if (! $periode) return '';
        $mois = ['01' => 'JANVIER', '02' => 'FEVRIER', '03' => 'MARS', '04' => 'AVRIL',
            '05' => 'MAI', '06' => 'JUIN', '07' => 'JUILLET', '08' => 'AOUT',
            '09' => 'SEPTEMBRE', '10' => 'OCTOBRE', '11' => 'NOVEMBRE', '12' => 'DECEMBRE'];
        $parts = explode('-', $periode);
        if (count($parts) === 2 && isset($mois[$parts[1]])) {
            return $mois[$parts[1]] . ' ' . $parts[0];
        }
        return strtoupper($periode);
    }
}
