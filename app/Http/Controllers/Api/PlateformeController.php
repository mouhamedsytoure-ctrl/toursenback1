<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\FactureAbonnement;
use App\Models\Logement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Console du proprietaire de la plateforme.
 * Toutes les routes sont protegees par le middleware "plateforme".
 */
class PlateformeController extends Controller
{
    // GET /api/plateforme/agences
    public function agences()
    {
        // Comptages groupes par agence : une requete par table plutot que N+1.
        // withoutGlobalScope est indispensable : Logement est filtre par agence.
        $logements = Logement::withoutGlobalScope(\App\Models\Scopes\AgenceScope::class)
            ->select('agence_id', DB::raw('count(*) as total'))
            ->groupBy('agence_id')->pluck('total', 'agence_id');

        $utilisateurs = User::select('agence_id', DB::raw('count(*) as total'))
            ->groupBy('agence_id')->pluck('total', 'agence_id');

        $plans = config('plans');

        $agences = Agence::orderByDesc('created_at')->get()->map(function ($a) use ($logements, $utilisateurs, $plans) {
            $prix = $plans[$a->plan]['prix_mensuel'] ?? 0;

            return [
                'id'                => $a->id,
                'nom'               => $a->nom,
                'slug'              => $a->slug,
                'ville'             => $a->ville,
                'telephone'         => $a->telephone,
                'plan'              => $a->plan,
                'plan_libelle'      => $plans[$a->plan]['libelle'] ?? $a->plan,
                'plan_souhaite'     => $a->plan_souhaite,
                'plan_souhaite_libelle' => $a->plan_souhaite ? ($plans[$a->plan_souhaite]['libelle'] ?? $a->plan_souhaite) : null,
                'prix_mensuel'      => $prix,
                'statut'            => $a->statut,
                'motif_suspension'  => $a->motif_suspension,
                'note_suspension'   => $a->note_suspension,
                'active'            => $a->estActive(),
                'quota_logements'   => $a->quota_logements,
                'max_utilisateurs'  => $a->max_utilisateurs,
                'nb_logements'      => (int) ($logements[$a->id] ?? 0),
                'nb_utilisateurs'   => (int) ($utilisateurs[$a->id] ?? 0),
                'essai_termine_le'  => $a->essai_termine_le,
                'jours_restants'    => $a->essai_termine_le
                    ? (int) now()->startOfDay()->diffInDays($a->essai_termine_le->startOfDay(), false)
                    : null,
                'inscrite_le'       => $a->created_at,
            ];
        });

        return response()->json($agences);
    }

    // GET /api/plateforme/stats
    public function stats()
    {
        $plans = config('plans');

        $agences = Agence::all();

        // MRR : on ne compte que les agences reellement actives et payantes.
        $mrr = $agences->filter(fn ($a) => $a->estActive() && $a->plan !== 'essai')
            ->sum(fn ($a) => $plans[$a->plan]['prix_mensuel'] ?? 0);

        $enEssai = $agences->filter(fn ($a) => $a->plan === 'essai' && $a->estActive());

        // Evolution sur 12 mois : inscriptions (toujours reelles) et revenus
        // reellement encaisses (factures payees -- vide tant qu'aucun paiement
        // automatique n'est encore passe, ce n'est pas une donnee simulee).
        $evolution = [];
        for ($i = 11; $i >= 0; $i--) {
            $mois   = now()->subMonths($i);
            $debut  = $mois->copy()->startOfMonth();
            $fin    = $mois->copy()->endOfMonth();

            $evolution[] = [
                'periode' => $mois->format('Y-m'),
                'libelle' => $mois->locale('fr')->isoFormat('MMM YY'),
                'nb_agences' => Agence::whereBetween('created_at', [$debut, $fin])->count(),
                'revenus'    => (int) FactureAbonnement::where('statut', 'payee')
                    ->whereBetween('payee_le', [$debut, $fin])->sum('montant'),
            ];
        }

        $ceMois  = $evolution[11];
        $moisPrec = $evolution[10];

        $variation = function (int $actuel, int $precedent): ?float {
            if ($precedent === 0) return null;
            return round((($actuel - $precedent) / $precedent) * 100);
        };

        return response()->json([
            'nb_agences'        => $agences->count(),
            'nb_actives'        => $agences->filter(fn ($a) => $a->estActive())->count(),
            'nb_en_essai'       => $enEssai->count(),
            'nb_payantes'       => $agences->filter(fn ($a) => $a->estActive() && $a->plan !== 'essai')->count(),
            'nb_suspendues'     => $agences->where('statut', 'suspendu')->count(),
            'nb_expirees'       => $agences->filter(fn ($a) => ! $a->estActive() && $a->statut !== 'suspendu')->count(),
            'mrr'               => $mrr,
            'essais_bientot'    => $enEssai->filter(
                fn ($a) => $a->essai_termine_le && $a->essai_termine_le->isBefore(now()->addDays(3))
            )->count(),
            'repartition_plans' => $agences->groupBy('plan')->map->count(),

            'evolution' => $evolution,
            'comparaison' => [
                'agences_ce_mois'       => $ceMois['nb_agences'],
                'agences_mois_dernier'  => $moisPrec['nb_agences'],
                'variation_agences'     => $variation($ceMois['nb_agences'], $moisPrec['nb_agences']),
                'revenus_ce_mois'       => $ceMois['revenus'],
                'revenus_mois_dernier'  => $moisPrec['revenus'],
                'variation_revenus'     => $variation($ceMois['revenus'], $moisPrec['revenus']),
            ],
            'total_annee_courante' => (int) FactureAbonnement::where('statut', 'payee')
                ->whereYear('payee_le', now()->year)->sum('montant'),
            'total_encaisse_historique' => (int) FactureAbonnement::where('statut', 'payee')->sum('montant'),
        ]);
    }

    // PUT /api/plateforme/agences/{agence}
    public function modifier(Request $request, Agence $agence)
    {
        $data = $request->validate([
            'plan'             => ['nullable', 'in:essai,starter,pro,illimite'],
            'statut'           => ['nullable', 'in:actif,suspendu,expire'],
            'quota_logements'  => ['nullable', 'integer', 'min:0'],
            'max_utilisateurs' => ['nullable', 'integer', 'min:0'],
            'motif_suspension' => ['nullable', 'in:paiement,autre'],
            'note_suspension'  => ['nullable', 'string', 'max:500'],
        ]);

        // Changer de plan applique le quota et la limite d'utilisateurs de ce plan,
        // sauf valeur explicite fournie.
        if (isset($data['plan'])) {
            if (! isset($data['quota_logements'])) {
                $data['quota_logements'] = config("plans.{$data['plan']}.quota_logements", $agence->quota_logements);
            }
            if (! isset($data['max_utilisateurs'])) {
                $data['max_utilisateurs'] = config("plans.{$data['plan']}.max_utilisateurs", $agence->max_utilisateurs);
            }
        }

        $agence->fill(array_filter($data, fn ($v) => $v !== null));

        // Reactiver efface systematiquement le motif de suspension (array_filter
        // ci-dessus retirerait sinon ces null et laisserait une note perimee).
        if (($data['statut'] ?? null) === 'actif') {
            $agence->motif_suspension = null;
            $agence->note_suspension = null;
        }

        $agence->save();

        return response()->json($agence->fresh());
    }

    // POST /api/plateforme/agences/{agence}/prolonger
    public function prolonger(Request $request, Agence $agence)
    {
        $data = $request->validate(['jours' => ['required', 'integer', 'min:1', 'max:365']]);

        $base = $agence->essai_termine_le && $agence->essai_termine_le->isFuture()
            ? $agence->essai_termine_le
            : now();

        $agence->update([
            'essai_termine_le' => $base->copy()->addDays($data['jours']),
            'statut'           => 'actif',
        ]);

        return response()->json($agence->fresh());
    }
}
