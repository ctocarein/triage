<?php

namespace App\Services\Evaluation;

use App\Models\Evaluation\EvaluationSession;
use App\Models\Report;
use App\Models\DecisionPath;
use Illuminate\Support\Arr;

class ReportGenerationService
{
    /**
     * Génère et persiste un rapport à partir d'une session d'évaluation.
     *
     * @param EvaluationSession $session
     * @return Report
     * @throws \Exception
     */
    public function generate(EvaluationSession $session): Report
    {
        if (!$session) {
            throw new \Exception("Session d’évaluation introuvable.");
        }

        // 1️⃣ — Récupère toutes les réponses avec la question associée
        $answers = $session->answers()->with('question')->get();

        $score = 0;
        $summary = [];
        $specialties = [];
        $decisionPathId = $session->decision_path_id;

        foreach ($answers as $answer) {
            if (empty($answer->is_skipped) && $answer->question) {
                $score += $answer->question->risk_weight ?? 0;
                $summary[] = [
                    'question' => $answer->question->text,
                    'value'    => $answer->value,
                    'key'      => $answer->question->key,
                ];
                // Récupère les éventuelles spécialités recommandées par la question (optionnel)
                if (!empty($answer->question->meta['specialties'])) {
                    $specialties = array_merge($specialties, (array)$answer->question->meta['specialties']);
                }
            }
        }
        $specialties = array_unique($specialties);

        // 2️⃣ — Calcule le niveau de risque selon ta logique métier
        $risk_level = match (true) {
            $score >= 20 => 'high',
            $score >= 10 => 'moderate',
            default      => 'low',
        };

        // 3️⃣ — Recommandations dynamiques (si un node final/chemin le fournit)
        $defaultRecommendations = [
            'high'     => 'Veuillez consulter un spécialiste rapidement.',
            'moderate' => 'Un contrôle médical est recommandé.',
            'low'      => 'Continuez la surveillance régulière.',
        ];

        // Essaie de récupérer la recommandation personnalisée du DecisionPath (ou du node final si disponible)
        $decisionPath = $session->decisionPath; // relation Eloquent
        $recommendation = $decisionPath?->recommendation ?? $defaultRecommendations[$risk_level];

        // Si tu as un node de sortie spécifique ayant une recommandation, privilégie-le ici (ex : $session->lastNode())

        // 4️⃣ — Création (updateOrCreate pour éviter les doublons)
        $report = Report::updateOrCreate(
            [
                'evaluation_session_id' => $session->id,
                'account_id' => $session->account_id,
            ],
            [
                'score'             => $score,
                'risk_level'        => $risk_level,
                'recommendation'    => $recommendation,
                'decision_path_id'  => $decisionPath?->id,
                'summary'           => $summary,
                'specialties'       => $specialties,
            ]
        );

        return $report;
    }
}
