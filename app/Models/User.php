<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'telephone', 'is_active', 'agence_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
        'is_platform_admin' => 'boolean',
    ];

    // --- Roles ---
    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }
    public function isAdmin(): bool      { return $this->role === 'admin'; }
    public function isLocataire(): bool  { return $this->role === 'locataire'; }

    // --- Relations ---
    // Simple relation, sans le trait BelongsToAgence : pas de scope ni de hook
    // de creation ici, pour ne pas provoquer la recursion avec Sanctum.
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function adminPermissions(): HasMany
    {
        return $this->hasMany(AdminPermission::class);
    }

    // Contrats de l'utilisateur en tant que locataire
    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    public function reclamations(): HasMany
    {
        return $this->hasMany(Reclamation::class);
    }

    /**
     * Verifie un droit d'admin. Le super admin a tous les droits.
     * $action : view | create | update | delete
     */
    public function hasPermission(string $module, string $action): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        $perm = $this->adminPermissions()->where('module', $module)->first();
        return $perm ? (bool) $perm->{'can_' . $action} : false;
    }
}
