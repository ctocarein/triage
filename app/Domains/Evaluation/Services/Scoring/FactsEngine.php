<?php

namespace App\Domains\Evaluation\Services\Scoring;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\FactMapping;
use App\Support\ComparesValues;

class FactsEngine
{
    use ComparesValues;

    public function evaluate(EvaluationSession $session): array
    {
        $answers = $session->answers()->with('question')->get();
        if ($answers->isEmpty()) {
            return [];
        }

        $answerMap = [];
        foreach ($answers as $answer) {
            if ($answer->is_skipped || $answer->value === null) {
                continue;
            }

            $key = $answer->question?->key;
            if (! $key) {
                continue;
            }

            $answerMap[$key] = $answer->value;
        }

        if (empty($answerMap)) {
            return [];
        }

        $mappings = FactMapping::query()
            ->where('is_active', true)
            ->whereIn('question_key', array_keys($answerMap))
            ->get();

        $facts = [];
        foreach ($mappings as $mapping) {
            $actual = $answerMap[$mapping->question_key] ?? null;
            if ($this->matches($actual, $mapping->answer_value, $mapping->operator)) {
                $facts[$mapping->fact_key] = true;
            }
        }

        return $facts;
    }

    protected function matches(mixed $actual, mixed $expected, ?string $operator): bool
    {
        $op = $operator ?? '=';

        if ($expected === null && $operator === null) {
            return !empty($actual);
        }

        return $this->compare($actual, $expected, $op);
    }
}