<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Duree de vie d'une session
    |--------------------------------------------------------------------------
    */

    'session_ttl_hours' => (int) env('EVALUATION_SESSION_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Orientation urgente
    |--------------------------------------------------------------------------
    |
    | Un signe d'alerte interrompt le questionnaire symptomatique -- il n'a plus
    | d'objet une fois l'urgence etablie -- mais pas l'evaluation. Le parcours
    | reprend au bloc ci-dessous pour collecter les antecedents : sans eux, le
    | score serait calcule sur un questionnaire tronque, et le dossier de la
    | beneficiaire resterait vide au moment ou il sert le plus.
    |
    | Tous les blocs situes avant celui-ci dans l'ordre du catalogue et non
    | encore parcourus sont marques comme visites. L'ordre fait foi, plutot
    | qu'une liste de cles en dur dans le moteur.
    |
    */

    'urgent_referral' => [
        'resume_bloc' => env('EVALUATION_URGENT_RESUME_BLOC', 'medical_background'),
    ],

];
