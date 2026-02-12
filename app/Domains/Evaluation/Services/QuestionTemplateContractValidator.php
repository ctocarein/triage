<?php

namespace App\Domains\Evaluation\Services;

use InvalidArgumentException;

class QuestionTemplateContractValidator
{
    private ConditionContractValidator $conditionValidator;

    public function __construct(?ConditionContractValidator $conditionValidator = null)
    {
        $this->conditionValidator = $conditionValidator ?? new ConditionContractValidator();
    }

    /**
     * @param array<string, mixed> $question
     */
    public function assertValid(array $question): void
    {
        $questionKey = (string) ($question['key'] ?? 'unknown');
        $errors = [];

        if (array_key_exists('visible_if', $question) && $question['visible_if'] !== null) {
            $visible = $this->decodeJsonIfNeeded($question['visible_if'], "{$questionKey}.visible_if", $errors);
            if ($visible !== null) {
                $errors = array_merge(
                    $errors,
                    $this->conditionValidator->validate($visible, "{$questionKey}.visible_if")
                );
            }
        }

        if (array_key_exists('routing_rules', $question) && $question['routing_rules'] !== null) {
            $rules = $this->decodeJsonIfNeeded($question['routing_rules'], "{$questionKey}.routing_rules", $errors);
            if ($rules !== null) {
                if (!is_array($rules) || !$this->isList($rules)) {
                    $errors[] = "{$questionKey}.routing_rules must be a list.";
                } else {
                    foreach ($rules as $idx => $rule) {
                        if (!is_array($rule)) {
                            $errors[] = "{$questionKey}.routing_rules[{$idx}] must be an object.";
                            continue;
                        }

                        if (!array_key_exists('if', $rule)) {
                            $errors[] = "{$questionKey}.routing_rules[{$idx}].if is required.";
                        } else {
                            $errors = array_merge(
                                $errors,
                                $this->conditionValidator->validate(
                                    $rule['if'],
                                    "{$questionKey}.routing_rules[{$idx}].if"
                                )
                            );
                        }

                        if (!array_key_exists('next', $rule) || !is_string($rule['next']) || trim($rule['next']) === '') {
                            $errors[] = "{$questionKey}.routing_rules[{$idx}].next must be a non-empty string.";
                        } elseif (!$this->isAllowedNext($rule['next'])) {
                            $errors[] = "{$questionKey}.routing_rules[{$idx}].next has invalid format '{$rule['next']}'.";
                        }
                    }
                }
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException("Condition contract validation failed:\n- " . implode("\n- ", $errors));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $questions
     */
    public function assertUniqueOrderPerBloc(array $questions, string $context = 'questions'): void
    {
        $seen = [];
        $errors = [];

        foreach ($questions as $index => $question) {
            if (!is_array($question)) {
                continue;
            }

            if (!array_key_exists('order', $question) || !array_key_exists('bloc_key', $question)) {
                continue;
            }

            $blocKey = $question['bloc_key'];
            $order = $question['order'];

            if (!is_scalar($blocKey) || $blocKey === '' || !is_scalar($order)) {
                continue;
            }

            $blocKey = (string) $blocKey;
            $orderKey = (string) $order;
            $questionKey = (string) ($question['key'] ?? "index_{$index}");
            $compound = $blocKey . '::' . $orderKey;

            if (!array_key_exists($compound, $seen)) {
                $seen[$compound] = $questionKey;
                continue;
            }

            $errors[] = "{$context} duplicates order '{$orderKey}' in bloc '{$blocKey}' for keys '{$seen[$compound]}' and '{$questionKey}'.";
        }

        if ($errors !== []) {
            throw new InvalidArgumentException("Order uniqueness validation failed:\n- " . implode("\n- ", $errors));
        }
    }

    /**
     * Normalize legacy condition shapes to the official contract.
     *
     * @param array<string, mixed> $question
     * @return array<string, mixed>
     */
    public function normalize(array $question): array
    {
        if (array_key_exists('visible_if', $question) && $question['visible_if'] !== null) {
            $ignored = [];
            $decoded = $this->decodeJsonIfNeeded($question['visible_if'], 'visible_if', $ignored);
            $question['visible_if'] = $this->normalizeConditionNode($decoded);
        }

        return $question;
    }

    /**
     * @param array<int, string> $errors
     */
    private function decodeJsonIfNeeded(mixed $value, string $path, array &$errors): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "{$path} contains invalid JSON: " . json_last_error_msg() . ".";
            return null;
        }

        return $decoded;
    }

    private function isAllowedNext(string $next): bool
    {
        return $next === 'end_session'
            || str_starts_with($next, 'end_session:')
            || str_starts_with($next, 'bloc:')
            || str_starts_with($next, 'question:')
            || str_starts_with($next, 'level:');
    }

    private function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function normalizeConditionNode(mixed $condition): mixed
    {
        if (!is_array($condition)) {
            return $condition;
        }

        if ($this->isList($condition)) {
            return [
                'all' => array_map(fn ($item) => $this->normalizeConditionNode($item), $condition),
            ];
        }

        if (isset($condition['all']) && is_array($condition['all'])) {
            $condition['all'] = array_map(fn ($item) => $this->normalizeConditionNode($item), $condition['all']);
        }

        if (isset($condition['any']) && is_array($condition['any'])) {
            $condition['any'] = array_map(fn ($item) => $this->normalizeConditionNode($item), $condition['any']);
        }

        if (array_key_exists('not', $condition)) {
            $condition['not'] = $this->normalizeConditionNode($condition['not']);
        }

        return $condition;
    }
}
