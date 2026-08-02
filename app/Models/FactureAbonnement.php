<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactureAbonnement extends Model
{
    protected $table = 'factures_abonnement';

    protected $fillable = [
        'agence_id', 'plan', 'montant', 'token_paydunya', 'statut', 'payee_le',
    ];

    protected $casts = [
        'montant' => 'integer',
        'payee_le' => 'datetime',
    ];

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }
}
