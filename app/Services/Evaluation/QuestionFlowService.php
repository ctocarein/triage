<?php

namespace App\Services\Evaluation;

use App\Models\Evaluation\DynamicQuestionTemplate;

class QuestionFlowService
{
    public function getVisibleQuestions(array $answers = []): \Illuminate\Support\Collection
    {
        $questions = DynamicQuestionTemplate::with('group')->orderBy('order')->get();

        return $questions->filter(function ($question) use ($answers) {
            if (!$question->visible_if) return true;

            foreach ($question->visible_if as $condition) {
                $qKey = $condition['question'] ?? null;
                $expected = $condition['value'] ?? null;
                $actual = $answers[$qKey] ?? null;
                $operator = $condition['operator'] ?? '=';

                switch ($operator) {
                    case '=':      if ($actual != $expected) return false; break;
                    case '!=':     if ($actual == $expected) return false; break;
                    case '>':      if (!($actual > $expected)) return false; break;
                    case '<':      if (!($actual < $expected)) return false; break;
                    case 'in':     if (!in_array($actual, (array)$expected)) return false; break;
                    case 'not_in': if (in_array($actual, (array)$expected)) return false; break;
                    default:       return false;
                }
            }

            return true;
        })->values();
    }

    public function getEntryPoints(?string $target = null)
    {
        $query = DynamicQuestionTemplate::where('is_entry_point', true)->orderBy('order');

        if ($target) {
            $query->where('answer_target', $target);
        }

        return $query->get();
    }

    public function getNextQuestions(EvaluationSession $session): array
    {
        $answers = $session->answers->keyBy('dynamic_question_template_id');
        $questions = DynamicQuestionTemplate::with('group')->orderBy('order')->get();

        return $questions->filter(function ($question) use ($answers) {
            // Si visible_if est vide, la question est visible
            if (!$question->visible_if) return true;

            foreach ($question->visible_if as $condition) {
                $qKey = $condition['question'];
                $expected = $condition['value'];
                $operator = $condition['operator'] ?? '=';

                // Trouver l’ID de la question par sa clé
                $matched = DynamicQuestionTemplate::where('key', $qKey)->first();
                if (!$matched) continue;

                $actual = optional($answers->get($matched->id))->value;

                switch ($operator) {
                    case '=': return $actual == $expected;
                    case '!=': return $actual != $expected;
                    case 'in': return in_array($actual, (array) $expected);
                    case 'not_in': return !in_array($actual, (array) $expected);
                    case '>': return $actual > $expected;
                    case '<': return $actual < $expected;
                    default: return false;
                }
            }

            return true;
        })->values()->all();
    }
    
}
