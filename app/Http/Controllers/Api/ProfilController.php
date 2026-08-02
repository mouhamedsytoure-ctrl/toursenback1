<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfilController extends Controller
{
    /**
     * PUT /api/profil
     * L'utilisateur connecte modifie ses propres infos (email, mot de passe, nom, telephone).
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:255'],
            'email'     => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'telephone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password'  => ['sometimes', 'nullable', 'string', 'min:8'],
        ]);

        if (array_key_exists('name', $data))      $user->name = $data['name'];
        if (array_key_exists('email', $data))     $user->email = $data['email'];
        if (array_key_exists('telephone', $data)) $user->telephone = $data['telephone'];
        if (! empty($data['password']))           $user->password = Hash::make($data['password']);

        $user->save();

        return response()->json([
            'message' => 'Profil mis a jour.',
            'user'    => $user->only(['id', 'name', 'email', 'role', 'telephone']),
        ]);
    }
}
