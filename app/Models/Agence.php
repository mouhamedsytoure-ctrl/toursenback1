<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agence extends Model
{
    protected $fillable = [
        'nom', 'slug', 'logo', 'telephone', 'whatsapp', 'email', 'adresse', 'ville',
        'representant_legal', 'representant_fonction', 'ninea', 'rccm',
        'plan', 'plan_souhaite', 'statut', 'motif_suspension', 'note_suspension',
        'quota_logements', 'max_utilisateurs', 'essai_termine_le', 'abonnement_expire_le',
    ];

    protected $casts = [
        'quota_logements'      => 'integer',
        'max_utilisateurs'     => 'integer',
        'essai_termine_le'     => 'datetime',
        'abonnement_expire_le' => 'datetime',
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

    /** Bloc d'identite utilise en en-tete des baux et quittances. */
    public function entete(): array
    {
        return [
            'nom'         => $this->nom,
            'representant'=> $this->representant_legal ?: '__________',
            'fonction'    => $this->representant_fonction ?: 'Gerant',
            'adresse'     => $this->adresse ?: '__________',
            'ville'       => $this->ville ?: 'Dakar',
            'telephone'   => $this->telephone ?: '__________',
            'email'       => $this->email ?: '__________',
            'ninea'       => $this->ninea,
            'rccm'        => $this->rccm,
            'logo'        => $this->logo,
        ];
    }

    /** L'agence peut-elle encore utiliser l'application ? */
    public function estActive(): bool
    {
        if ($this->statut !== 'actif') {
            return false;
        }

        if ($this->plan === 'essai') {
            return ! $this->essai_termine_le || $this->essai_termine_le->isFuture();
        }

        // Plan payant : abonnement_expire_le null = accord manuel du proprietaire
        // de la plateforme (sans echeance) ; sinon l'abonnement doit etre a jour.
        return ! $this->abonnement_expire_le || $this->abonnement_expire_le->isFuture();
    }

    /**
     * Pourquoi l'acces est actuellement bloque (n'a de sens que si !estActive()).
     * "suspendu_paiement"/"essai_termine"/"abonnement_expire" : reparable en payant.
     * "suspendu_autre" : payer ne change rien, il faut contacter le proprietaire.
     */
    public function motifBlocage(): string
    {
        if ($this->statut === 'suspendu') {
            return $this->motif_suspension === 'autre' ? 'suspendu_autre' : 'suspendu_paiement';
        }

        return $this->plan === 'essai' ? 'essai_termine' : 'abonnement_expire';
    }

    /** Payer reactiverait-il ce compte, ou faut-il contacter l'administrateur ? */
    public function blocagePaiementPossible(): bool
    {
        return $this->motifBlocage() !== 'suspendu_autre';
    }

    /** Quota de logements atteint ? (0 = illimite) */
    public function quotaAtteint(): bool
    {
        if ((int) $this->quota_logements === 0) {
            return false;
        }

        return $this->logements()->count() >= $this->quota_logements;
    }

    /** Limite de comptes admin/super_admin atteinte ? (0 = illimite) */
    public function utilisateursAtteint(): bool
    {
        if ((int) $this->max_utilisateurs === 0) {
            return false;
        }

        return $this->users()->whereIn('role', ['super_admin', 'admin'])->count() >= $this->max_utilisateurs;
    }
}