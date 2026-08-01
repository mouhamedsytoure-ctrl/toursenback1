<?php

namespace App\Models\Concerns;

use App\Models\Agence;
use App\Models\Scopes\AgenceScope;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToAgence
{
    public static function bootBelongsToAgence(): void
    {
        static::addGlobalScope(new AgenceScope());

        static::creating(function ($model) {
            if (empty($model->agence_id)) {
                $model->agence_id = Tenant::agenceId();
            }
        });
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    // Echappe volontairement au scope : voit les lignes de toutes les agences
    public function scopeToutesAgences(Builder $query): Builder
    {
        return $query->withoutGlobalScope(AgenceScope::class);
    }

    // Echappe au scope courant pour filtrer sur une agence precise
    public function scopeDeLAgence(Builder $query, int $agenceId): Builder
    {
        return $query->withoutGlobalScope(AgenceScope::class)->where('agence_id', $agenceId);
    }
}
