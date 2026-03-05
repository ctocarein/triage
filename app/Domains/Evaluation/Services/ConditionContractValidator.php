<?php

namespace App\Domains\Evaluation\Services;

use App\Support\HandlesConditionArrays;

class ConditionContractValidator
{
    use HandlesConditionArrays;

    /**
     * @var array<int, string>
     */
    private const ALLOWED_SOURCES = [
        'answer',
        'question',
        'profile.medical',
        'profile.personal',
        'session',
        'context',
        'current_question',
    ];

    /**
     * @var array<int, string>
     */
    private const ALLOWED_OPERATORS = [
        '=',
        '!=',
        '>',
        '<',
        '>=',
        '<=',
        'in',
        'not_in',
        'empty',
        'not_empty',
        'age_gte',
        'age_gt',
        'age_lte',
        'age_lt',
        'age_eq',
    ];

    /**
     * Extract all question keys referenced by `source: "question"` nodes in a condition tree.
     *
     * @return array<int, string>
     */
    public function extractQuestionRefs(mixed $condition): array
    {
        $refs = [];
        $this->collectQuestionRefs($condition, $refs);
        return array_values(array_unique($refs));
    }

    private function collectQuestionRefs(mixed $condition, array &$refs): void
    {
        if (!is_array($condition)) {
            return;
        }

        if ($this->isList($condition)) {
            foreach ($condition as $item) {
                $this->collectQuestionRefs($item, $refs);
            }
            return;
        }

        if (($condition['source'] ?? 'question') === 'question') {
            $key = $condition['key'] ?? $condition['question'] ?? null;
            if (is_string($key) && $key !== '') {
                $refs[] = $key;
            }
        }

        foreach (['all', 'any'] as $groupKey) {
            if (isset($condition[$groupKey]) && is_array($condition[$groupKey])) {
                foreach ($condition[$groupKey] as $item) {
                    $this->collectQuestionRefs($item, $refs);
                }
            }
        }

        if (array_key_exists('not', $condition)) {
            $this->collectQuestionRefs($condition['not'], $refs);
        }
    }

    /**
     * @return array<int, string>
     */
    public function validate(mixed $condition, string $path = 'condition'): array
    {
        if ($condition === null) {
            return [];
        }

        if (is_bool($condition) || is_string($condition) || is_int($condition) || is_float($condition)) {
            return [];
        }

        if (!is_array($condition)) {
            return ["{$path} must be a scalar, boolean or object."];
        }

        // Legacy list support: [cond1, cond2] == all(cond1, cond2)
        if ($this->isList($condition)) {
            $errors = [];
            foreach ($condition as $idx => $item) {
                $errors = array_merge($errors, $this->validate($item, "{$path}[{$idx}]"));
            }

            return $errors;
        }

        if (array_key_exists('not', $condition)) {
            return $this->validate($condition['not'], "{$path}.not");
        }

        if (isset($condition['all'])) {
            if (!is_array($condition['all']) || !$this->isList($condition['all'])) {
                return ["{$path}.all must be a list."];
            }

            $errors = [];
            foreach ($condition['all'] as $idx => $item) {
                $errors = array_merge($errors, $this->validate($item, "{$path}.all[{$idx}]"));
            }

            return $errors;
        }

        if (isset($condition['any'])) {
            if (!is_array($condition['any']) || !$this->isList($condition['any'])) {
                return ["{$path}.any must be a list."];
            }

            $errors = [];
            foreach ($condition['any'] as $idx => $item) {
                $errors = array_merge($errors, $this->validate($item, "{$path}.any[{$idx}]"));
            }

            return $errors;
        }

        $allowedKeys = ['source', 'key', 'question', 'operator', 'value'];
        $unknownKeys = array_diff(array_keys($condition), $allowedKeys);
        if ($unknownKeys !== []) {
            return ["{$path} contains forbidden keys: " . implode(', ', $unknownKeys) . "."];
        }

        $source = $condition['source'] ?? 'question';
        if (!is_string($source) || !in_array($source, self::ALLOWED_SOURCES, true)) {
            return ["{$path}.source is invalid. Allowed: " . implode(', ', self::ALLOWED_SOURCES) . "."];
        }

        $key = $condition['key'] ?? $condition['question'] ?? null;
        if ($source !== 'answer' && (!is_string($key) || trim($key) === '')) {
            return ["{$path}.key is required for source '{$source}'."];
        }

        $operator = $condition['operator'] ?? '=';
        if (!is_string($operator) || !in_array($operator, self::ALLOWED_OPERATORS, true)) {
            return ["{$path}.operator is invalid. Allowed: " . implode(', ', self::ALLOWED_OPERATORS) . "."];
        }

        if (!in_array($operator, ['empty', 'not_empty'], true) && !array_key_exists('value', $condition)) {
            return ["{$path}.value is required for operator '{$operator}'."];
        }

        return [];
    }

}

