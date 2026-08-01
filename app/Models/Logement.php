<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Logement extends Model
{
    use BelongsToAgence;

    protected $fillable = [
        'immeuble_id', 'reference', 'etage', 'type',
        'loyer', 'statut', 'nb_pieces', 'surface', 'description',
    ];

    protected $casts = [
        'etage'   => 'integer',
        'loyer'   => 'decimal:2',
        'surface' => 'decimal:2',
    ];

    public function immeuble(): BelongsTo
    {
        return $this->belongsTo(Immeuble::class);
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    // Le contrat en cours (locataire actuel)
    public function contratActif(): HasOne
    {
        return $this->hasOne(Contrat::class)->where('statut', 'actif');
    }

    public function medias(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
