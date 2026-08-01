<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // GET /api/admins  (super admin uniquement)
    public function index(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return response()->json(
            User::where('role', 'admin')->with('adminPermissions')->get()
        );
    }

    private const MODULES = [
        'immeubles', 'logements', 'contrats', 'locataires',
        'paiements', 'reclamations', 'terrains', 'medias',
    ];

    /**
     * POST /api/admins
     * Le super admin cree un admin et coche ses droits.
     * permissions = [ {module, can_view, can_create, can_update, can_delete}, ... ]
     * Si permissions est absent ou vide, tous les droits sont accordes par defaut.
     */
    public function store(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'email'                   => ['required', 'email', 'unique:users,email'],
            'telephone'               => ['nullable', 'string', 'max:255'],
            'password'                => ['required', 'string', 'min:6'],
            'permissions'             => ['array'],
            'permissions.*.module'    => ['required', 'string'],
            'permissions.*.can_view'  => ['boolean'],
            'permissions.*.can_create'=> ['boolean'],
            'permissions.*.can_update'=> ['boolean'],
            'permissions.*.can_delete'=> ['boolean'],
        ]);

        $permissions = $data['permissions'] ?? [];

        // Aucune permission envoyee -> tous les droits sur tous les modules
        if (empty($permissions)) {
            $permissions = array_map(fn($m) => [
                'module'     => $m,
                'can_view'   => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => true,
            ], self::MODULES);
        }

        $admin = DB::transaction(function () use ($data, $permissions) {
            $admin = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'telephone' => $data['telephone'] ?? null,
                'password'  => Hash::make($data['password']),
                'role'      => 'admin',
                'is_active' => true,
            ]);

            foreach ($permissions as $p) {
                AdminPermission::create([
                    'user_id'    => $admin->id,
                    'module'     => $p['module'],
                    'can_view'   => $p['can_view']   ?? true,
                    'can_create' => $p['can_create'] ?? true,
                    'can_update' => $p['can_update'] ?? true,
                    'can_delete' => $p['can_delete'] ?? true,
                ]);
            }

            return $admin;
        });

        return response()->json($admin->load('adminPermissions'), 201);
    }

    /**
     * PUT /api/admins/{user}/permissions
     * Met a jour les droits d'un admin (les cases a cocher).
     */
    public function updatePermissions(Request $request, User $user)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_unless($user->isAdmin(), 422, "Cet utilisateur n'est pas un admin.");

        $data = $request->validate([
            'permissions'              => ['required', 'array'],
            'permissions.*.module'     => ['required', 'string'],
            'permissions.*.can_view'   => ['boolean'],
            'permissions.*.can_create' => ['boolean'],
            'permissions.*.can_update' => ['boolean'],
            'permissions.*.can_delete' => ['boolean'],
        ]);

        foreach ($data['permissions'] as $p) {
            AdminPermission::updateOrCreate(
                ['user_id' => $user->id, 'module' => $p['module']],
                [
                    'can_view'   => $p['can_view']   ?? false,
                    'can_create' => $p['can_create'] ?? false,
                    'can_update' => $p['can_update'] ?? false,
                    'can_delete' => $p['can_delete'] ?? false,
                ]
            );
        }

        return response()->json($user->load('adminPermissions'));
    }
}
