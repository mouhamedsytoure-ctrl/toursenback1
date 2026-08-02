<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Gestion par l'agence de ses propres informations (coordonnees, branding).
 * Distinct de PlateformeController : ici, l'agence gere son propre profil,
 * pas le proprietaire de la plateforme qui gere toutes les agences.
 */
class AgenceController extends Controller
{
    // GET /api/agence
    public function show(Request $request)
    {
        $agence = $request->user()->agence;
        abort_unless($agence, 404, "Aucune agence rattachee a ce compte.");

        return response()->json($agence);
    }

    // PUT /api/agence  (super admin uniquement)
    public function update(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $agence = $request->user()->agence;
        abort_unless($agence, 404, "Aucune agence rattachee a ce compte.");

        $data = $request->validate([
            'nom'                    => ['sometimes', 'string', 'max:255'],
            'telephone'              => ['nullable', 'string', 'max:255'],
            'whatsapp'               => ['nullable', 'string', 'max:255'],
            'email'                  => ['nullable', 'email', 'max:255'],
            'adresse'                => ['nullable', 'string', 'max:255'],
            'ville'                  => ['nullable', 'string', 'max:255'],
            'logo'                   => ['nullable', 'string', 'max:2048'],
            'representant_legal'     => ['nullable', 'string', 'max:255'],
            'representant_fonction'  => ['nullable', 'string', 'max:255'],
            'ninea'                  => ['nullable', 'string', 'max:255'],
            'rccm'                   => ['nullable', 'string', 'max:255'],
        ]);

        $agence->update($data);

        return response()->json($agence->fresh());
    }
}
