<?php

namespace App\Services\Evaluation;

use App\Models\Evaluation\EvaluationAnswer;
use App\Models\Evaluation\EvaluationSession;
use App\Models\Evaluation\DynamicQuestionTemplate;
use Illuminate\Support\Facades\Log;

class AnswerService
{
    /**
     * Enregistre ou met à jour une réponse à une question.
     *
     * @param array $data Données validées (incluant session, question, réponse...).
     * @return EvaluationAnswer La réponse enregistrée.
     * @throws \Exception Si la session est fermée ou invalide.
     */
    public function saveAnswer(array $data): EvaluationAnswer
    {
        $session = EvaluationSession::findOrFail($data['evaluation_session_id']);
        $question = DynamicQuestionTemplate::findOrFail($data['dynamic_question_template_id']);

        if (!$session->isInProgress()) {
            throw new \Exception("⛔ La session est terminée, impossible d'enregistrer une réponse.");
        }

        $existing = EvaluationAnswer::where([
            'evaluation_session_id' => $session->id,
            'dynamic_question_template_id' => $question->id
        ])->first();

        // 🛠 Mise à jour de réponse existante
        if ($existing) {
            $existing->update([
                'value' => $data['value'],
                'is_skipped' => $data['is_skipped'] ?? false,
            ]);
            return $existing;
        }

        // ✅ Nouvelle réponse
        $answer = EvaluationAnswer::create([
            'evaluation_session_id' => $session->id,
            'dynamic_question_template_id' => $question->id,
            'value' => $data['value'],
            'is_skipped' => $data['is_skipped'] ?? false,
        ]);

        // 🎯 Incrément du score si réponse pertinente
        if (!$answer->is_skipped && $question->risk_weight > 0) {
            $session->increment('score', (int) $question->risk_weight);
        }

        return $answer;
    }
}
