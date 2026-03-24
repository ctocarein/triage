<?php

namespace App\Domains\Evaluation\Enums;

enum ThematicBlocIntention: string
{
    /** Évaluation active de symptômes présents — produit un score clinique. */
    case Assessment = 'assessment';

    /** Dépistage préventif : détection d'un risque latent sans symptôme déclaré. */
    case Screening = 'screening';

    /** Surveillance longitudinale : suivi de l'évolution d'un symptôme connu. */
    case Monitoring = 'monitoring';

    /** Collecte de contexte pour préparer ou personnaliser une évaluation future. */
    case Preparation = 'preparation';

    /** Rappel / réengagement au bon moment (cycle, délai, événement calendaire). */
    case FollowUp = 'follow_up';

    /** Transfert de compréhension : expliquer un symptôme, résultat ou risque. */
    case Education = 'education';

    /** Orientation vers la bonne ressource ou le bon professionnel de santé. */
    case Triage = 'triage';

    /** Décision partagée : présenter des options pour que le patient participe au choix. */
    case SharedDecision = 'shared_decision';

    /** Urgence : signal critique — prime sur tout, sortie forcée du flux normal. */
    case Crisis = 'crisis';
}
