<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seuil minimal de divulgation
    |--------------------------------------------------------------------------
    |
    | Nombre minimal de femmes evaluees dans un district avant que ses chiffres
    | soient affiches en detail sur la carte.
    |
    | Dans un district ou trois femmes ont ete evaluees, afficher « 3 femmes,
    | dont 1 orientation urgente » designe quelqu'un pour qui connait le
    | terrain. Sur des donnees de cancer, ce n'est pas une precaution
    | theorique.
    |
    | Sous le seuil, le district reste sur la carte et reste marque comme
    | couvert -- seuls ses chiffres sont retenus. Le masquer ferait croire a
    | une absence de couverture, ce qui serait faux et pousserait a envoyer des
    | moyens la ou il y en a deja.
    |
    | 5 est un point de depart courant, a regler a l'usage.
    |
    */

    'min_disclosure_count' => (int) env('HEALTH_MAP_MIN_DISCLOSURE', 5),

];
