<?php

/**
 * Tarifs des formules, en FCFA par mois.
 *
 * Ce sont des valeurs de depart : modifie-les quand tu auras arrete tes prix.
 * Le calcul du revenu mensuel recurrent (MRR) de la console s'appuie dessus.
 *
 * max_utilisateurs : nombre de comptes admin/super_admin par agence (0 = illimite).
 * Le compte super_admin cree a l'inscription compte dans ce total.
 */
return [
    'essai' => [
        'libelle'          => 'Essai',
        'prix_mensuel'     => 0,
        'quota_logements'  => 10,
        'max_utilisateurs' => 1,
    ],
    'starter' => [
        'libelle'          => 'Standard',
        'prix_mensuel'     => 10000,
        'quota_logements'  => 2,
        'max_utilisateurs' => 1,
    ],
    'pro' => [
        'libelle'          => 'Pro',
        'prix_mensuel'     => 25000,
        'quota_logements'  => 6,
        'max_utilisateurs' => 5,
    ],
    'illimite' => [
        'libelle'          => 'VIP',
        'prix_mensuel'     => 50000,
        'quota_logements'  => 0, // 0 = illimite
        'max_utilisateurs' => 0, // 0 = illimite
    ],
];
