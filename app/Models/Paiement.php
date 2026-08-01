<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Paiement extends Model
{
    use BelongsToAgence;

    protected $fillable = [
        'contrat_id', 'periode', 'montant', 'mode_paiement', 'statut',
        'date_paiement', 'reference_transaction', 'recu_numero',
        'recu_fichier', 'enregistre_par',
    ];

    protected $casts = [
        'montant'       => 'decimal:2',
        'date_paiement' => 'datetime',
    ];

    /**
     * Reçu automatique : des qu'un paiement passe a "paye",
     * on genere un numero de recu et la date si absents.
     */
    protected static function booted(): void
    {
        static::saving(function (Paiement $paiement) {
            if ($paiement->statut === 'paye') {
                if (empty($paiement->recu_numero)) {
                    $paiement->recu_numero = 'REC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
                }
                if (empty($paiement->date_paiement)) {
                    $paiement->date_paiement = now();
                }
            }
        });
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistre_par');
    }
}
