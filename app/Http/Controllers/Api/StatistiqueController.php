<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Immeuble;
use App\Models\Logement;
use App\Models\Paiement;
use App\Models\Reclamation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques d'analyse : evolutions, tendances, classements.
 * Complete le DashboardController, qui ne donne que des compteurs instantanes.
 */
class StatistiqueController extends Controller
{
    // GET /api/statistiques?mois=12
    public function index(Request $request)
    {
        abort_if($request->user()->isLocataire(), 403);

        // Statistiques avancees reservees aux formules Pro et VIP.
        $agence = $request->user()->agence;
        if ($agence && ! $request->user()->is_platform_admin && ! in_array($agence->plan, ['pro', 'illimite'], true)) {
            abort(403, 'Les statistiques avancees sont reservees aux formules Pro et VIP.');
        }

        $nbMois = (int) $request->query('mois', 12);
        $nbMois = max(3, min($nbMois, 24));

        return response()->json([
            'evolution'       => $this->evolution($nbMois),
            'recouvrement'    => $this->recouvrement($nbMois),
            'modes_paiement'  => $this->modesPaiement($nbMois),
            'top_immeubles'   => $this->topImmeubles(),
            'retards'         => $this->retards(),
            'occupation'      => $this->occupation(),
            'reclamations'    => $this->reclamations(),
            'comparaison'     => $this->comparaisonMoisPrecedent(),
        ]);
    }

    /** Encaisse mois par mois sur la periode demandee. */
    private function evolution(int $nbMois): array
    {
        $debut = now()->startOfMonth()->subMonths($nbMois - 1);

        // Une seule requete groupee : evite N requetes pour N mois.
        $lignes = Paiement::where('statut', 'paye')
            ->where('periode', '>=', $debut->format('Y-m'))
            ->select('periode', DB::raw('SUM(montant) as total'), DB::raw('COUNT(*) as nb'))
            ->groupBy('periode')->get()->keyBy('periode');

        $attendus = Paiement::where('periode', '>=', $debut->format('Y-m'))
            ->select('periode', DB::raw('SUM(montant) as total'))
            ->groupBy('periode')->get()->keyBy('periode');

        $serie = [];
        for ($i = 0; $i < $nbMois; $i++) {
            $m   = $debut->copy()->addMonths($i);
            $cle = $m->format('Y-m');

            $serie[] = [
                'periode'  => $cle,
                'libelle'  => $m->locale('fr')->isoFormat('MMM YY'),
                'encaisse' => (float) ($lignes[$cle]->total ?? 0),
                'attendu'  => (float) ($attendus[$cle]->total ?? 0),
                'nb'       => (int) ($lignes[$cle]->nb ?? 0),
            ];
        }

        return $serie;
    }

    /** Taux de recouvrement mensuel : encaisse / attendu, en pourcentage. */
    private function recouvrement(int $nbMois): array
    {
        return array_map(function ($m) {
            return [
                'periode' => $m['periode'],
                'libelle' => $m['libelle'],
                'taux'    => $m['attendu'] > 0 ? round($m['encaisse'] / $m['attendu'] * 100) : 0,
            ];
        }, $this->evolution($nbMois));
    }

    /** Repartition Wave / Orange Money / especes. */
    private function modesPaiement(int $nbMois): array
    {
        $debut = now()->startOfMonth()->subMonths($nbMois - 1)->format('Y-m');

        $lignes = Paiement::where('statut', 'paye')
            ->where('periode', '>=', $debut)
            ->select('mode_paiement', DB::raw('SUM(montant) as total'), DB::raw('COUNT(*) as nb'))
            ->groupBy('mode_paiement')->get();

        $total = max((float) $lignes->sum('total'), 1);

        $libelles = ['wave' => 'Wave', 'orange_money' => 'Orange Money', 'especes' => 'Especes'];

        return $lignes->map(fn ($l) => [
            'mode'       => $l->mode_paiement ?? 'non_precise',
            'libelle'    => $libelles[$l->mode_paiement] ?? 'Non precise',
            'montant'    => (float) $l->total,
            'nb'         => (int) $l->nb,
            'pourcentage' => round((float) $l->total / $total * 100),
        ])->sortByDesc('montant')->values()->all();
    }

    /** Classement des immeubles par revenu encaisse sur 12 mois. */
    private function topImmeubles(): array
    {
        $debut = now()->startOfMonth()->subMonths(11)->format('Y-m');

        $lignes = Paiement::where('paiements.statut', 'paye')
            ->where('paiements.periode', '>=', $debut)
            ->join('contrats', 'contrats.id', '=', 'paiements.contrat_id')
            ->join('logements', 'logements.id', '=', 'contrats.logement_id')
            ->join('immeubles', 'immeubles.id', '=', 'logements.immeuble_id')
            ->select(
                'immeubles.id', 'immeubles.nom', 'immeubles.ville',
                DB::raw('SUM(paiements.montant) as revenu'),
                DB::raw('COUNT(DISTINCT contrats.id) as nb_contrats')
            )
            ->groupBy('immeubles.id', 'immeubles.nom', 'immeubles.ville')
            ->orderByDesc('revenu')->limit(10)->get();

        return $lignes->map(fn ($l) => [
            'id'          => $l->id,
            'nom'         => $l->nom,
            'ville'       => $l->ville,
            'revenu'      => (float) $l->revenu,
            'nb_contrats' => (int) $l->nb_contrats,
        ])->all();
    }

    /** Impayes classes par anciennete : plus c'est vieux, moins c'est recuperable. */
    private function retards(): array
    {
        $mois = now()->format('Y-m');

        $impayes = Paiement::where('statut', '!=', 'paye')
            ->where('periode', '<=', $mois)
            ->with('contrat.locataire:id,name', 'contrat.logement:id,reference')
            ->get();

        $tranches = ['courant' => 0, 'un_mois' => 0, 'deux_trois' => 0, 'plus_trois' => 0];
        $montants = $tranches;

        foreach ($impayes as $p) {
            $ecart = $this->ecartMois($p->periode, $mois);

            $cle = match (true) {
                $ecart <= 0 => 'courant',
                $ecart === 1 => 'un_mois',
                $ecart <= 3 => 'deux_trois',
                default      => 'plus_trois',
            };

            $tranches[$cle]++;
            $montants[$cle] += (float) $p->montant;
        }

        return [
            'tranches' => [
                ['cle' => 'courant',    'libelle' => 'Mois courant', 'nb' => $tranches['courant'],    'montant' => $montants['courant']],
                ['cle' => 'un_mois',    'libelle' => '1 mois',       'nb' => $tranches['un_mois'],    'montant' => $montants['un_mois']],
                ['cle' => 'deux_trois', 'libelle' => '2 a 3 mois',   'nb' => $tranches['deux_trois'], 'montant' => $montants['deux_trois']],
                ['cle' => 'plus_trois', 'libelle' => 'Plus de 3 mois', 'nb' => $tranches['plus_trois'], 'montant' => $montants['plus_trois']],
            ],
            'total_nb'      => $impayes->count(),
            'total_montant' => (float) $impayes->sum('montant'),
            'pires'         => $impayes->sortBy('periode')->take(5)->map(fn ($p) => [
                'locataire' => $p->contrat?->locataire?->name ?? '—',
                'logement'  => $p->contrat?->logement?->reference ?? '—',
                'periode'   => $p->periode,
                'montant'   => (float) $p->montant,
                'retard'    => $this->ecartMois($p->periode, $mois),
            ])->values()->all(),
        ];
    }

    /** Occupation globale et par immeuble. */
    private function occupation(): array
    {
        $total = Logement::count();
        $loues = Logement::where('statut', 'loue')->count();

        $parImmeuble = Immeuble::withCount([
            'logements',
            'logements as loues_count' => fn ($q) => $q->where('statut', 'loue'),
        ])->get()->map(fn ($i) => [
            'nom'   => $i->nom,
            'total' => $i->logements_count,
            'loues' => $i->loues_count,
            'taux'  => $i->logements_count > 0 ? round($i->loues_count / $i->logements_count * 100) : 0,
        ])->sortByDesc('taux')->values()->all();

        return [
            'total'        => $total,
            'loues'        => $loues,
            'disponibles'  => Logement::where('statut', 'disponible')->count(),
            'taux'         => $total > 0 ? round($loues / $total * 100) : 0,
            'par_immeuble' => $parImmeuble,
        ];
    }

    /** Reclamations : volume et delai moyen de resolution. */
    private function reclamations(): array
    {
        $resolues = Reclamation::where('statut', 'resolu')->whereNotNull('updated_at')->get();

        $delai = $resolues->count() > 0
            ? round($resolues->avg(fn ($r) => $r->created_at->diffInDays($r->updated_at)), 1)
            : 0;

        return [
            'ouvertes'      => Reclamation::where('statut', '!=', 'resolu')->count(),
            'resolues'      => $resolues->count(),
            'delai_moyen'   => $delai,
            'par_priorite'  => Reclamation::where('statut', '!=', 'resolu')
                ->select('priorite', DB::raw('COUNT(*) as nb'))
                ->groupBy('priorite')->pluck('nb', 'priorite'),
        ];
    }

    /** Variation par rapport au mois precedent, pour afficher des fleches. */
    private function comparaisonMoisPrecedent(): array
    {
        $ceMois   = now()->format('Y-m');
        $moisPrec = now()->subMonth()->format('Y-m');

        $enc = fn ($p) => (float) Paiement::where('periode', $p)->where('statut', 'paye')->sum('montant');

        $actuel     = $enc($ceMois);
        $precedent  = $enc($moisPrec);
        $variation  = $precedent > 0 ? round(($actuel - $precedent) / $precedent * 100) : null;

        return [
            'encaisse_actuel'    => $actuel,
            'encaisse_precedent' => $precedent,
            'variation'          => $variation,
        ];
    }

    /** Nombre de mois entre deux periodes au format Y-m. */
    private function ecartMois(string $depuis, string $jusqua): int
    {
        [$a1, $m1] = array_map('intval', explode('-', $depuis));
        [$a2, $m2] = array_map('intval', explode('-', $jusqua));

        return ($a2 - $a1) * 12 + ($m2 - $m1);
    }
}
