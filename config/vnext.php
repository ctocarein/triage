<?php

return [
    'shadow_mode'          => (bool) env('VNEXT_SHADOW_MODE', false),
    'purge_invalidated'    => (bool) env('VNEXT_PURGE_INVALIDATED', false),

    // Ordonnancement adaptatif : trie les blocs par potentiel de contribution décroissant.
    'adaptive_sequencing'  => (bool) env('VNEXT_ADAPTIVE_SEQUENCING', false),

    // Sortie anticipée : complète la session si le potentiel restant ne peut plus
    // changer la zone de risque. Requiert adaptive_sequencing = true pour être utile.
    'adaptive_early_exit'  => (bool) env('VNEXT_ADAPTIVE_EARLY_EXIT', false),
];
