<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agence extends Model
{
    protected $fillable = [
        'nom', 'slug', 'logo', 'telephone', 'email', 'adresse', 'ville',
        'plan', 'statut', 'quota_logements', 'essai_termine_le',
    ];

    protected $casts = [
        'quota_logements'  => 'integer',
        'essai_termine_le' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function immeubles(): HasMany
    {
        return $this->hasMany(Immeuble::class);
    }

    public function logements(): HasMany
    {
        return $this->hasMany(Logement::class);
    }
}
