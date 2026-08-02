<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\Logement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogementController extends Controller
{
    // GET /api/logements?immeuble_id=&etage=&statut=
    public function index(Request $request)
    {
        $query = Logement::query()->with('medias');

        if ($request->filled('immeuble_id')) {
            $query->where('immeuble_id', $request->immeuble_id);
        }
        if ($request->filled('etage')) {
            $query->where('etage', $request->etage);
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        return response()->json($query->orderByDesc('etage')->get());
    }

    public function show(Logement $logement)
    {
        $logement->load(['medias', 'immeuble', 'contratActif.locataire']);
        return response()->json($logement);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('logements', 'create'), 403);

        $data = $request->validate([
            'immeuble_id' => ['required', 'exists:immeubles,id'],
            'reference'   => ['required', 'string', 'max:255'],
            'etage'       => ['required', 'integer', 'min:0'],
            'type'        => ['required', 'in:appartement,studio,mini_studio,local_commercial'],
            'loyer'       => ['required', 'numeric', 'min:0'],
            'statut'      => ['nullable', 'in:disponible,loue,indisponible'],
            'nb_pieces'   => ['nullable', 'integer'],
            'surface'     => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
        ]);

        // Quota de la formule : 0 = illimite. Verrou sur la ligne agence pour
        // empecher deux creations simultanees de depasser le quota d'une unite
        // (sans le lockForUpdate, deux requetes concurrentes pourraient toutes
        // les deux passer le controle avant qu'aucune n'ait encore cree son logement).
        $agenceId = $request->user()->agence_id;

        $resultat = DB::transaction(function () use ($data, $agenceId) {
            if ($agenceId) {
                $agence = Agence::where('id', $agenceId)->lockForUpdate()->first();
                if ($agence && $agence->quotaAtteint()) {
                    return ['quota_atteint' => true, 'quota' => $agence->quota_logements];
                }
            }

            return ['logement' => Logement::create($data)];
        });

        if (! empty($resultat['quota_atteint'])) {
            return response()->json([
                'message' => "Vous avez atteint la limite de {$resultat['quota']} logements de votre formule.",
                'motif'   => 'quota_atteint',
                'quota'   => $resultat['quota'],
            ], 402);
        }

        return response()->json($resultat['logement'], 201);
    }

    public function update(Request $request, Logement $logement)
    {
        abort_unless($request->user()->hasPermission('logements', 'update'), 403);

        $data = $request->validate([
            'reference'   => ['sometimes', 'string', 'max:255'],
            'etage'       => ['sometimes', 'integer', 'min:0'],
            'type'        => ['sometimes', 'in:appartement,studio,mini_studio,local_commercial'],
            'loyer'       => ['sometimes', 'numeric', 'min:0'],
            'statut'      => ['sometimes', 'in:disponible,loue,indisponible'],
            'nb_pieces'   => ['nullable', 'integer'],
            'surface'     => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
        ]);

        $logement->update($data);
        return response()->json($logement);
    }

    public function destroy(Request $request, Logement $logement)
    {
        abort_unless($request->user()->hasPermission('logements', 'delete'), 403);
        $logement->delete();
        return response()->json(['message' => 'Logement supprime.']);
    }
}
