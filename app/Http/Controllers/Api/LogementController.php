<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Logement;
use Illuminate\Http\Request;

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

        // Quota de la formule : 0 = illimite.
        $agence = $request->user()->agence;
        if ($agence && $agence->quotaAtteint()) {
            return response()->json([
                'message' => "Vous avez atteint la limite de {$agence->quota_logements} logements de votre formule.",
                'motif'   => 'quota_atteint',
                'quota'   => $agence->quota_logements,
            ], 402);
        }

        $logement = Logement::create($data);
        return response()->json($logement, 201);
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
