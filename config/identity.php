<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Organisation par defaut
    |--------------------------------------------------------------------------
    |
    | Rattachement de repli des beneficiaires enroles par un evaluateur qui
    | n'appartient a aucune organisation. Le beneficiaire rejoint d'abord
    | l'organisation de son enroleur quand celui-ci en a une ; l'organisation
    | ci-dessous ne sert que lorsqu'il n'y en a pas.
    |
    | L'identifiant est fixe pour rester stable entre les environnements et
    | permettre un rapprochement des donnees. Il peut etre surcharge par
    | l'environnement si une organisation Chereh existe deja en base.
    |
    */

    'default_organization' => [
        'id'   => env('DEFAULT_ORGANIZATION_ID', '00000000-0000-4000-8000-000000000001'),
        'name' => env('DEFAULT_ORGANIZATION_NAME', 'Chereh admin'),
        'type' => 'platform',
    ],

    /*
    |--------------------------------------------------------------------------
    | PIN initial
    |--------------------------------------------------------------------------
    |
    | PIN attribue au beneficiaire dont le numero est collecte pendant
    | l'evaluation, communique par l'evaluateur a la fin de la visite pour
    | qu'il puisse installer l'application et s'y connecter.
    |
    | Le numero de telephone servant d'identifiant de connexion, ce PIN
    | partage rend le compte devinable par quiconque connait le numero.
    | C'est pourquoi la credential est creee avec must_change_password : la
    | fenetre se referme au premier acces. Ne pas desactiver ce drapeau sans
    | remplacer le PIN par une valeur tiree au hasard et remise en main propre.
    |
    */

    'default_pin' => env('DEFAULT_BENEFICIARY_PIN', '5555'),

];
