<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contrat extends Model
{
    use BelongsToAgence;

    protected $fillable = [
        'logement_id', 'user_id',
        'preneur_nom', 'preneur_prenom', 'preneur_civilite', 'preneur_telephone', 'preneur_email',
        'preneur_adresse', 'preneur_profession', 'preneur_nationalite',
        'preneur_date_naissance', 'preneur_lieu_naissance',
        'preneur_piece_type', 'preneur_piece_numero',
        'composition', 'usage',
        'date_debut', 'date_fin', 'montant_loyer', 'caution', 'jour_echeance',
        'statut', 'est_bloque', 'motif_fin', 'archived_at', 'document',
    ];

    protected $casts = [
        'date_debut'             => 'date',
        'date_fin'               => 'date',
        'preneur_date_naissance' => 'date',
        'archived_at'            => 'datetime',
        'montant_loyer'          => 'decimal:2',
        'caution'                => 'decimal:2',
        'est_bloque'             => 'boolean',
    ];

    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    public function locataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function medias(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
