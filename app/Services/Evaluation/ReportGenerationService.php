<?php
namespace App\Services\Evaluation;

use App\Models\Evaluation\EvaluationSession;
use App\Models\Evaluation\Report;
use App\Models\Evaluation\DecisionPath;

class ReportGenerationService
{
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
                    'value' => $answer->value
                ];
            }
        }

        // Déterminer le niveau de risque
        $risk_level = match (true) {
            $score >= 20 => 'high',
            $score >= 10 => 'moderate',
            default => 'low',
        };

        // Chercher un chemin de décision correspondant (optionnel)
        $decisionPath = DecisionPath::where('risk_level', $risk_level)->first();

        return Report::create([
            'evaluation_session_id' => $session->id,
            'score' => $score,
            'risk_level' => $risk_level,
            'recommendation' => $decisionPath->recommendation ?? 'Veuillez consulter un médecin.',
            'decision_path_id' => $decisionPath?->id,
            'summary' => $summary,
        ]);
    }
}
