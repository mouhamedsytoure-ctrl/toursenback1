<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Notifications\RecuDisponible;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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

    // POST /api/paiements  (le secretaire/admin confirme qu'un locataire a paye)
    // Enregistre le paiement ET, s'il est marque paye, envoie aussitot l'email
    // de recu au locataire : un seul clic cote interface.
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
        $statut = $data['statut'] ?? 'paye';

        // updateOrCreate : si un paiement (meme annule) existait deja pour ce
        // contrat/periode, on le remplace plutot que d'echouer sur la
        // contrainte d'unicite (contrat_id, periode). On efface au passage
        // une eventuelle annulation precedente puisque c'est un nouvel
        // enregistrement.
        $paiement = Paiement::updateOrCreate(
            ['contrat_id' => $data['contrat_id'], 'periode' => $data['periode']],
            [
                'montant'          => $data['montant'],
                'mode_paiement'    => $data['mode_paiement'],
                'statut'           => $statut,
                'enregistre_par'   => $request->user()->id,
                'date_paiement'    => now(),
                'motif_annulation' => null,
                'annule_par'       => null,
                'annule_le'        => null,
                'recu_envoye_at'   => null,
            ]
        );

        if ($paiement->statut === 'paye') {
            $this->envoyerNotificationRecuSiPossible($paiement);
        }

        return response()->json($paiement->fresh(), 201);
    }

    // POST /api/paiements/{paiement}/annuler
    // Un paiement confirme n'est jamais supprime : il passe en "annule" avec
    // un motif obligatoire et une trace de qui/quand. Ca permet de corriger
    // une erreur (mauvaise personne, mauvais montant) sans jamais pouvoir
    // faire disparaitre silencieusement un paiement deja confirme.
    public function annuler(Request $request, Paiement $paiement)
    {
        abort_unless($request->user()->hasPermission('loyers', 'delete'), 403);

        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($paiement->statut === 'annule') {
            return response()->json(['message' => 'Ce paiement est déjà annulé.'], 422);
        }

        $paiement->update([
            'statut'           => 'annule',
            'motif_annulation' => $data['motif'],
            'annule_par'       => $request->user()->id,
            'annule_le'        => now(),
        ]);

        return response()->json($paiement->fresh());
    }

    // GET /api/paiements/{paiement}/recu  -> donnees du recu (JSON)
    public function recu(Request $request, Paiement $paiement)
    {
        $this->autoriserAccesPaiement($request, $paiement);
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

    // POST /api/paiements/{paiement}/envoyer-recu
    // Renvoi manuel (ex: le premier envoi automatique a echoue, ou l'email de
    // contact vient d'etre corrige).
    public function envoyerRecu(Request $request, Paiement $paiement)
    {
        abort_unless($request->user()->hasPermission('loyers', 'update'), 403);

        if ($paiement->statut !== 'paye') {
            return response()->json([
                'message' => "Ce paiement n'est pas encore marqué payé.",
            ], 422);
        }

        if (! $this->envoyerNotificationRecuSiPossible($paiement)) {
            return response()->json([
                'message' => "Ce locataire n'a pas d'email de contact enregistré.",
            ], 422);
        }

        return response()->json($paiement->fresh());
    }

    // Envoie la notification "reçu disponible" vers le VRAI email de contact
    // du locataire (Contrat::preneur_email), jamais vers son identifiant de
    // connexion. Retourne false si aucun email de contact n'est enregistré.
    private function envoyerNotificationRecuSiPossible(Paiement $paiement): bool
    {
        $paiement->load('contrat');
        $email = $paiement->contrat?->preneur_email;

        if (! $email) {
            return false;
        }

        Notification::route('mail', $email)->notify(new RecuDisponible($paiement));

        $paiement->recu_envoye_at = now();
        $paiement->save();

        return true;
    }

    // GET /api/paiements/{paiement}/quittance  -> QUITTANCE en PDF (style agence)
    public function quittance(Request $request, Paiement $paiement)
    {
        $this->autoriserAccesPaiement($request, $paiement);
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

        $data = [
            'logo'    => public_path('logo-toursen.jpeg'),
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
    // Un locataire ne peut voir/telecharger que SON PROPRE recu (sinon
    // n'importe qui pourrait lire le nom, l'adresse et le loyer d'un autre
    // locataire en changeant juste l'id dans l'URL). Le staff (admin/
    // secretaire/super admin) garde acces a tout, comme sur /paiements.
    private function autoriserAccesPaiement(Request $request, Paiement $paiement): void
    {
        $user = $request->user();
        if ($user->isLocataire()) {
            abort_unless($paiement->contrat?->user_id === $user->id, 403);
        }
    }

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
