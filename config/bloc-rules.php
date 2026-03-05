<?php

/**
 * Règles d'exclusion des blocs selon le profil patient.
 *
 * Ces seuils correspondent à des recommandations cliniques versionnées.
 * Toute modification doit être tracée et validée médicalement.
 *
 * Format : 'bloc_key' => ['field' => 'age', 'operator' => '<', 'value' => N]
 */
return [
    'age_exclusions' => [
        // Dépistage sein : non recommandé avant 25 ans (HAS 2022)
        'symptom_breast' => 25,

        // Dépistage col de l'utérus : non recommandé avant 22 ans (HAS 2020)
        'symptom_cervix' => 22,
    ],
];
