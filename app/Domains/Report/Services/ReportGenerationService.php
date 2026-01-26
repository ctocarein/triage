<?php

namespace App\Domains\Report\Services;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Services\RiskModelService;
use App\Domains\Report\Models\Report;
use App\Domains\Decision\Services\DecisionInsightService;

class ReportGenerationService
{
    public function __construct(private readonly RiskModelService $riskModel) {}

    /**
     * Genere et persiste un rapport a partir d'une session d'evaluation.
     */
    public function generate(EvaluationSession $session): Report
    {
        if (!$session) {
            throw new \Exception('Session evaluation introuvable.');
        }

        $answers = $session->answers()->with('question')->get();

        $risk = $this->riskModel->evaluate($session);
        $score = $risk['score_total'];
        $maxScore = $risk['max_score'];
        $riskLevel = $risk['risk_level'];
        $summary = [];
        $specialties = [];

        foreach ($answers as $answer) {
            if (empty($answer->is_skipped) && $answer->question) {
                $summary[] = [
                    'question' => $answer->question->text,
                    'value' => $answer->value,
                    'key' => $answer->question->key,
                ];
                if (!empty($answer->question->meta['specialties'])) {
                    $specialties = array_merge($specialties, (array) $answer->question->meta['specialties']);
                }
            }
        }
        $specialties = array_unique($specialties);

        $defaultRecommendations = [
            'high' => 'Veuillez consulter un specialiste rapidement.',
            'moderate' => 'Un controle medical est recommande.',
            'low' => 'Continuez la surveillance reguliere.',
        ];

        $decisionPath = $session->decisionPath;
        $recommendation = $decisionPath?->recommendation ?? $defaultRecommendations[$riskLevel];

        $report = Report::updateOrCreate(
            [
                'evaluation_session_id' => $session->id,
                'identity_id' => $session->identity_id,
            ],
            [
                'score' => $score,
                'max_score' => $maxScore,
                'risk_level' => $riskLevel,
                'recommendation' => $recommendation,
                'decision_path_id' => $decisionPath?->id,
                'summary' => $summary,
                'specialties' => $specialties,
            ]
        );

        app(DecisionInsightService::class)->generate($session, $report);

        return $report;
    }
}
