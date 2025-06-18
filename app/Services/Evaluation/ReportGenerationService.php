<?php

namespace App\Services\Evaluation;

use App\Models\Evaluation\EvaluationSession;
use App\Models\Report;
use App\Models\DecisionPath;

class ReportGenerationService
{
    /**
     * Génère et persiste un rapport à partir d'une session d'évaluation.
     *
     * @param EvaluationSession $session
     * @return Report
     */
    public function generate(EvaluationSession $session): Report
    {
        $answers = $session->answers()->with('question')->get();

        $score = 0;
        $summary = [];

        foreach ($answers as $answer) {
            if (!$answer->is_skipped && $answer->question) {
                $score += $answer->question->risk_weight;
                $summary[] = [
                    'question' => $answer->question->key,
                    'value'    => $answer->value,
                ];
            }
        }

        // Déterminer le niveau de risque
        $risk_level = match (true) {
            $score >= 20 => 'high',
            $score >= 10 => 'moderate',
            default      => 'low',
        };

        // Recommandations par défaut si pas de chemin trouvé
        $defaultRecommendations = [
            'high'     => 'Veuillez consulter un spécialiste rapidement.',
            'moderate' => 'Un contrôle médical est recommandé.',
            'low'      => 'Continuez la surveillance régulière.',
        ];

        // Chercher un chemin de décision correspondant (optionnel)
        $decisionPath = DecisionPath::where('risk_level', $risk_level)->first();

        // Utiliser la recommandation du DecisionPath si disponible, sinon fallback
        $recommendation = $decisionPath->recommendation ?? $defaultRecommendations[$risk_level];

        // Créer le rapport (en liant au compte et à la session)
        return Report::create([
            'account_id'             => $session->account_id,
            'evaluation_session_id'  => $session->id,
            'score'                  => $score,
            'risk_level'             => $risk_level,
            'recommendation'         => $recommendation,
            'decision_path_id'       => $decisionPath?->id,
            'summary'                => $summary,
        ]);
    }
}
