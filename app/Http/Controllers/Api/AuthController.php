<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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
            'agence_whatsapp'  => ['nullable', 'string', 'max:255'],
            'agence_ville'     => ['nullable', 'string', 'max:255'],
            // Formule que le visiteur a cliquee sur la page tarifs : purement informatif
            // (aide a la relance manuelle), n'accorde jamais les droits de cette formule.
            'plan_souhaite'    => ['nullable', 'in:starter,pro,illimite'],
            'admin_nom'        => ['required', 'string', 'max:255'],
            'admin_email'      => ['required', 'email', 'unique:users,email'],
            'admin_password'   => ['required', 'string', 'min:8'],
            'admin_telephone'  => ['nullable', 'string', 'max:255'],
        ]);

        $essai = config('plans.essai');

        $user = DB::transaction(function () use ($data, $essai) {
            $agence = Agence::create([
                'nom'              => $data['agence_nom'],
                'slug'             => $data['agence_slug'],
                'telephone'        => $data['agence_telephone'] ?? null,
                'whatsapp'         => $data['agence_whatsapp'] ?? null,
                'ville'            => $data['agence_ville'] ?? null,
                'plan'             => 'essai',
                'plan_souhaite'    => $data['plan_souhaite'] ?? null,
                'statut'           => 'actif',
                'quota_logements'  => $essai['quota_logements'],
                'max_utilisateurs' => $essai['max_utilisateurs'],
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

    /**
     * POST /api/mot-de-passe/oublie
     * Renvoie toujours le meme message, que l'email existe ou non,
     * pour ne pas laisser deviner quels comptes sont enregistres.
     */
    public function motDePasseOublie(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => "Si un compte existe avec cet email, un lien de reinitialisation vient d'etre envoye.",
        ]);
    }

    /**
     * POST /api/mot-de-passe/reinitialiser
     * Verifie le token recu par email et met a jour le mot de passe.
     */
    public function reinitialiserMotDePasse(Request $request)
    {
        $data = $request->validate([
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $statut = Password::reset($data, function (User $user, string $password) {
            $user->update(['password' => Hash::make($password)]);
        });

        if ($statut !== Password::PASSWORD_RESET) {
            $messages = [
                Password::INVALID_USER    => "Aucun compte ne correspond a cet email.",
                Password::INVALID_TOKEN   => "Ce lien de reinitialisation est invalide ou a expire.",
                Password::RESET_THROTTLED => "Veuillez patienter avant de reessayer.",
            ];

            throw ValidationException::withMessages([
                'email' => [$messages[$statut] ?? "Reinitialisation impossible."],
            ]);
        }

        return response()->json(['message' => 'Mot de passe reinitialise. Vous pouvez vous connecter.']);
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
                'whatsapp'         => $agence->whatsapp,
                'adresse'          => $agence->adresse,
                'ville'            => $agence->ville,
                'plan'             => $agence->plan,
                'plan_souhaite'    => $agence->plan_souhaite,
                'statut'           => $agence->statut,
                'active'           => $agence->estActive(),
                'essai_termine_le' => $agence->essai_termine_le,
                'jours_restants'   => $agence->essai_termine_le
                    ? (int) now()->startOfDay()->diffInDays($agence->essai_termine_le->startOfDay(), false)
                    : null,
                'quota_logements'  => $agence->quota_logements,
                'nb_logements'     => $agence->logements()->count(),
                'max_utilisateurs' => $agence->max_utilisateurs,
                'nb_utilisateurs'  => $agence->users()->whereIn('role', ['super_admin', 'admin'])->count(),
            ] : null,
        ];
    }
}
