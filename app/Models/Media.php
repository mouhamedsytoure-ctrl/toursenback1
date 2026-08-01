<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    use BelongsToAgence;

    protected $table = 'medias';

    protected $fillable = [
        'mediable_id', 'mediable_type', 'type', 'libelle', 'chemin', 'public_id', 'couverture', 'ordre',
    ];

    protected $casts = [
        'couverture' => 'boolean',
        'ordre'      => 'integer',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return $this->chemin ?? '';
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
