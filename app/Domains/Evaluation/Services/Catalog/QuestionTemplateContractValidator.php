<?php

namespace App\Domains\Evaluation\Services\Catalog;

use App\Support\HandlesConditionArrays;
use InvalidArgumentException;

class QuestionTemplateContractValidator
{
    use HandlesConditionArrays;

    private ConditionContractValidator $conditionValidator;
    private RoutingRulesValidator $routingValidator;

    public function __construct(
        ?ConditionContractValidator $conditionValidator = null,
        ?RoutingRulesValidator $routingValidator = null
    ) {
        $this->conditionValidator = $conditionValidator ?? new ConditionContractValidator();
        $this->routingValidator   = $routingValidator   ?? new RoutingRulesValidator();
    }

    /**
     * @param array<string, mixed> $question
     */
    public function assertValid(array $question): void
    {
        $questionKey = (string) ($question['key'] ?? 'unknown');
        $errors = [];

        if (array_key_exists('visible_if', $question) && $question['visible_if'] !== null) {
            $visible = $this->decodeJsonOrCollect($question['visible_if'], "{$questionKey}.visible_if", $errors);
            if ($visible !== null) {
                $errors = array_merge(
                    $errors,
                    $this->conditionValidator->validate($visible, "{$questionKey}.visible_if")
                );
            }
        }

        if (array_key_exists('routing_rules', $question) && $question['routing_rules'] !== null) {
            $errors = array_merge(
                $errors,
                $this->routingValidator->validateRoutingRules(
                    $question['routing_rules'],
                    [],
                    [],
                    $questionKey
                )
            );
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
            $decoded = $this->decodeJsonOrCollect($question['visible_if'], 'visible_if', $ignored);
            $question['visible_if'] = $this->normalizeConditionNode($decoded);
        }

        return $question;
    }

    /**
     * @param array<int, string> $errors
     */
    private function decodeJsonOrCollect(mixed $value, string $path, array &$errors): mixed
    {
        $decoded = $this->decodeJsonIfNeeded($value);
        if (is_string($value) && $decoded === null) {
            $errors[] = "{$path} contains invalid JSON: " . json_last_error_msg() . ".";
        }

        return $decoded;
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
