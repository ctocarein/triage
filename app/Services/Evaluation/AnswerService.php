<?php

namespace App\Services\Evaluation;

use App\Models\Evaluation\EvaluationAnswer;
use App\Models\Evaluation\EvaluationSession;
use App\Models\Evaluation\DynamicQuestionTemplate;

class AnswerService
{
    public function saveAnswer(array $data): EvaluationAnswer
    {
        $session = EvaluationSession::findOrFail($data['evaluation_session_id']);
        $question = DynamicQuestionTemplate::findOrFail($data['dynamic_question_template_id']);

        if ($session->status !== 'in_progress') {
            throw new \Exception("Session fermée à l'enregistrement.");
        }

        $existing = EvaluationAnswer::where([
            'evaluation_session_id' => $session->id,
            'dynamic_question_template_id' => $question->id
        ])->first();

        if ($existing) {
            $existing->update([
                'value' => $data['value'],
                'is_skipped' => $data['is_skipped']
            ]);
            return $existing;
        }

        $answer = EvaluationAnswer::create([
            'evaluation_session_id' => $session->id,
            'dynamic_question_template_id' => $question->id,
            'value' => $data['value'],
            'is_skipped' => $data['is_skipped'] ?? false,
        ]);

        if (!$answer->is_skipped && $question->risk_weight > 0) {
            $session->increment('score', (int) $question->risk_weight);
        }

        return $answer;
    }
}
