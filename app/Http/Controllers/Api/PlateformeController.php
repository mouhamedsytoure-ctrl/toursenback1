<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agence;
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

        $agence->update(array_filter($data, fn ($v) => $v !== null));

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
