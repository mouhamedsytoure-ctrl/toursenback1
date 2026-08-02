<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Immeuble;
use Illuminate\Http\Request;

class ImmeubleController extends Controller
{
    // GET /api/immeubles
    public function index()
    {
        $immeubles = Immeuble::withCount([
            'logements',
            'logements as disponibles_count' => fn ($q) => $q->where('statut', 'disponible'),
        ])->with('medias')->get();

        return response()->json($immeubles);
    }

    // GET /api/immeubles/{immeuble}  -> avec ses logements (pour le drill etage)
    public function show(Immeuble $immeuble)
    {
        $immeuble->load(['logements.medias', 'medias']);
        return response()->json($immeuble);
    }

    // POST /api/immeubles
    public function store(Request $request)
    {
        $this->authorizeAction($request, 'create');

        $data = $request->validate([
            'nom'         => ['required', 'string', 'max:255'],
            'adresse'     => ['nullable', 'string', 'max:255'],
            'ville'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'latitude'    => ['nullable', 'numeric'],
            'longitude'   => ['nullable', 'numeric'],
        ]);
        $data['created_by'] = $request->user()->id;

        $immeuble = Immeuble::create($data);
        return response()->json($immeuble, 201);
    }

    // PUT /api/immeubles/{immeuble}
    public function update(Request $request, Immeuble $immeuble)
    {
        $this->authorizeAction($request, 'update');

        $data = $request->validate([
            'nom'          => ['sometimes', 'string', 'max:255'],
            'adresse'      => ['nullable', 'string', 'max:255'],
            'ville'        => ['sometimes', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'latitude'     => ['nullable', 'numeric'],
            'longitude'    => ['nullable', 'numeric'],
            'mis_en_avant' => ['sometimes', 'boolean'],
        ]);

        // Mise en avant sur la vitrine : reservee au plan VIP.
        if (array_key_exists('mis_en_avant', $data)) {
            $agence = $request->user()->agence;
            $estVip = $request->user()->is_platform_admin || ($agence && $agence->plan === 'illimite');
            abort_unless($estVip, 403, "La mise en avant des annonces est reservee a la formule VIP.");
        }

        $immeuble->update($data);
        return response()->json($immeuble);
    }

    // DELETE /api/immeubles/{immeuble}
    public function destroy(Request $request, Immeuble $immeuble)
    {
        $this->authorizeAction($request, 'delete');
        $immeuble->delete();
        return response()->json(['message' => 'Immeuble supprime.']);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($request->user()->hasPermission('immeubles', $action), 403, 'Action non autorisee.');
    }
}
