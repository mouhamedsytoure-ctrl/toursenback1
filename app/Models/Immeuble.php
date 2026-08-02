<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Immeuble extends Model
{
    use BelongsToAgence;

    protected $table = 'immeubles';

    protected $fillable = [
        'nom', 'adresse', 'ville', 'description',
        'latitude', 'longitude', 'photo_couverture', 'created_by', 'mis_en_avant',
    ];

    protected $casts = [
        'mis_en_avant' => 'boolean',
    ];

    public function logements(): HasMany
    {
        return $this->hasMany(Logement::class);
    }

    public function medias(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Nombre de logements disponibles (pour la vitrine)
    public function nbDisponibles(): int
    {
        return $this->logements()->where('statut', 'disponible')->count();
    }
}
