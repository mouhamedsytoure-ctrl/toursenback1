<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Slugs qui entreraient en conflit avec des routes existantes
    private const SLUGS_RESERVES = [
        'api', 'app', 'admin', 'login', 'register', 'vitrine', 'public', 'espace', 'assets', 'www',
    ];

    // POST /api/login
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Ce compte est desactive.'],
            ]);
        }

        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->utilisateurPayload($user),
        ]);
    }

    // GET /api/me
    public function me(Request $request)
    {
        $user = $request->user();
        $data = $this->utilisateurPayload($user);

        // Pour un admin, on renvoie aussi ses droits
        if ($user->isAdmin()) {
            $data['permissions'] = $user->adminPermissions()
                ->get(['module', 'can_view', 'can_create', 'can_update', 'can_delete']);
        }

        return response()->json($data);
    }

    // POST /api/logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Deconnecte.']);
    }

    /**
     * POST /api/register
     * Inscription self-service : cree l'agence ET son premier super admin,
     * puis connecte directement l'utilisateur (meme format que /login).
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'agence_nom'       => ['required', 'string', 'max:255'],
            'agence_slug'      => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/',
                'unique:agences,slug', Rule::notIn(self::SLUGS_RESERVES),
            ],
            'agence_telephone' => ['nullable', 'string', 'max:255'],
            'agence_ville'     => ['nullable', 'string', 'max:255'],
            'admin_nom'        => ['required', 'string', 'max:255'],
            'admin_email'      => ['required', 'email', 'unique:users,email'],
            'admin_password'   => ['required', 'string', 'min:8'],
            'admin_telephone'  => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $agence = Agence::create([
                'nom'              => $data['agence_nom'],
                'slug'             => $data['agence_slug'],
                'telephone'        => $data['agence_telephone'] ?? null,
                'ville'            => $data['agence_ville'] ?? null,
                'plan'             => 'essai',
                'statut'           => 'actif',
                'quota_logements'  => 10,
                'essai_termine_le' => now()->addDays(14),
            ]);

            // Personne n'est connecte en console/HTTP anonyme : on force le tenant
            // avant toute creation liee, sinon agence_id resterait vide sur l'utilisateur.
            Tenant::pour($agence->id);

            $user = User::create([
                'name'      => $data['admin_nom'],
                'email'     => $data['admin_email'],
                'password'  => Hash::make($data['admin_password']),
                'role'      => 'super_admin',
                'telephone' => $data['admin_telephone'] ?? null,
                'is_active' => true,
                'agence_id' => $agence->id,
            ]);

            // Recharge les colonnes a valeur par defaut (is_platform_admin)
            // absentes du modele juste apres un create().
            return $user->refresh();
        });

        Tenant::reinitialiser();

        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->utilisateurPayload($user),
        ], 201);
    }

    // Forme commune du "user" renvoyee par login/me/register, avec le branding de l'agence.
    private function utilisateurPayload(User $user): array
    {
        $user->loadMissing('agence');
        $agence = $user->agence;

        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'role'              => $user->role,
            'telephone'         => $user->telephone,
            'agence_id'         => $user->agence_id,
            'is_platform_admin' => $user->is_platform_admin,
            'agence'            => $agence ? [
                'nom'              => $agence->nom,
                'slug'             => $agence->slug,
                'logo'             => $agence->logo,
                'telephone'        => $agence->telephone,
                'ville'            => $agence->ville,
                'plan'             => $agence->plan,
                'statut'           => $agence->statut,
                'essai_termine_le' => $agence->essai_termine_le,
            ] : null,
        ];
    }
}
