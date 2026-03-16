<?php

namespace App\Domains\Evaluation\Services\Navigation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Support\ComparesValues;
use App\Support\HandlesConditionArrays;

/**
 * Évalue les conditions du moteur de règles.
 *
 * Responsabilité unique : prendre une condition JSON (visible_if, routing_rules.if,
 * conditions de groupe) et un contexte de scope, et retourner un booléen.
 *
 * Stateless — injectable et testable indépendamment du flux de session.
 */
class ConditionEvaluator
{
    use ComparesValues;
    use HandlesConditionArrays;

    // -------------------------------------------------------------------------
    // API publique
    // -------------------------------------------------------------------------

    /**
     * Évalue une condition contre un scope pré-construit.
     */
    public function evaluate(mixed $condition, array $scope): bool
    {
        if ($condition === null) {
            return false;
        }

        if (is_bool($condition)) {
            return $condition;
        }

        if (is_array($condition)) {
            if (isset($condition['not'])) {
                return ! $this->evaluate($condition['not'], $scope);
            }

            if (isset($condition['all']) && is_array($condition['all'])) {
                foreach ($condition['all'] as $item) {
                    if (! $this->evaluate($item, $scope)) {
                        return false;
                    }
                }
                return true;
            }

            if (isset($condition['any']) && is_array($condition['any'])) {
                foreach ($condition['any'] as $item) {
                    if ($this->evaluate($item, $scope)) {
                        return true;
                    }
                }
                return false;
            }

            if (array_key_exists('source', $condition) || array_key_exists('key', $condition) || array_key_exists('question', $condition)) {
                $actual   = $this->resolveActual($condition, $scope);
                $expected = $condition['value'] ?? null;
                $operator = $condition['operator'] ?? '=';
                return $this->compare($actual, $expected, $operator);
            }

            if (array_key_exists('value', $condition) || array_key_exists('operator', $condition)) {
                $expected = $condition['value'] ?? null;
                $operator = $condition['operator'] ?? '=';
                return $this->compare($scope['answer']['value'] ?? null, $expected, $operator);
            }

            if ($this->isList($condition)) {
                return $this->compare($scope['answer']['value'] ?? null, $condition, 'in');
            }
        }

        return $this->compare($scope['answer']['value'] ?? null, $condition, '=');
    }

    /**
     * Point d'entrée principal : détermine si une question doit être affichée.
     *
     * Combine deux règles :
     *  1. Si la question cible un champ de profil déjà renseigné → cachée
     *  2. Évalue le `visible_if` de la question
     */
    public function shouldBeVisible(DynamicQuestionTemplate $question, array $answers, array $profile): bool
    {
        if (in_array($question->answer_target, ['medical_profile', 'personal_profile'])) {
            $profileType = $question->answer_target === 'personal_profile' ? 'personal' : 'medical';
            if (! empty($profile[$profileType][$question->key] ?? null)) {
                return false;
            }
        }

        $visibleIf = is_string($question->visible_if)
            ? json_decode($question->visible_if, true)
            : $question->visible_if;

        return $this->isVisible($visibleIf ?? [], $answers, $profile);
    }

    /**
     * Évalue un `visible_if` (tableau de conditions ou condition unique).
     *
     * - `[]` → toujours visible
     * - liste `[cond1, cond2]` → AND implicite
     * - objet `{...}` → condition directe
     */
    public function isVisible(array $visibleIf, array $answers, array $profile): bool
    {
        if ($visibleIf === []) {
            return true;
        }

        $scope = $this->buildScope($answers, $profile);

        if ($this->isList($visibleIf)) {
            foreach ($visibleIf as $condition) {
                if (! $this->evaluate($condition, $scope)) {
                    return false;
                }
            }
            return true;
        }

        return $this->evaluate($visibleIf, $scope);
    }

    /**
     * Construit le scope utilisé par `evaluate()`.
     *
     * @param  mixed                         $answerValue  Valeur de la réponse courante (null si absent)
     * @param  EvaluationSession|null        $session
     * @param  DynamicQuestionTemplate|null  $question
     */
    public function buildScope(
        array $answers,
        array $profile,
        mixed $answerValue = null,
        ?EvaluationSession $session = null,
        ?DynamicQuestionTemplate $question = null,
    ): array {
        $normalized     = $this->normalize($answerValue);
        $comparable     = $this->extractComparable($normalized);
        $sessionContext = $session ? ($session->context ?? []) : [];
        $sessionContext = is_array($sessionContext) ? $sessionContext : [];

        return [
            'answer' => [
                'raw'   => $normalized,
                'value' => $comparable,
            ],
            'answers' => $answers,
            'profile' => [
                'medical'  => $profile['medical'] ?? [],
                'personal' => $profile['personal'] ?? [],
            ],
            'session' => [
                'id'           => $session?->id,
                'public_id'    => $session?->public_id,
                'status'       => $session?->status,
                'type'         => $session?->type,
                'risk_level'   => $session?->risk_level,
                'score'        => $session?->score,
                'current_bloc' => $session?->current_bloc,
                'context'      => $sessionContext,
            ],
            'question' => [
                'id'            => $question?->id,
                'key'           => $question?->key,
                'bloc_key'      => $question?->bloc_key,
                'type'          => $question?->type,
                'answer_target' => $question?->answer_target,
            ],
        ];
    }

    /**
     * Normalise une valeur de réponse brute.
     *
     * - Décode les strings JSON valides
     * - Convertit "true"/"false" en booléens
     */
    public function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            $trim = trim($value);
            if ($trim !== '' && ($trim[0] === '[' || $trim[0] === '{')) {
                $decoded = json_decode($trim, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }

            $lower = strtolower($trim);
            if ($lower === 'true') {
                return true;
            }
            if ($lower === 'false') {
                return false;
            }
        }

        return $value;
    }

    /**
     * Extrait la valeur comparable depuis une réponse normalisée.
     *
     * Si la valeur est un tableau avec une clé `value` scalaire,
     * retourne cette valeur scalaire (ex: `{"value": "oui"}`).
     */
    public function extractComparable(mixed $normalized): mixed
    {
        if (! is_array($normalized)) {
            return $normalized;
        }

        if (array_key_exists('value', $normalized) && ! is_array($normalized['value'])) {
            return $normalized['value'];
        }

        return $normalized;
    }

    // -------------------------------------------------------------------------
    // Résolution interne
    // -------------------------------------------------------------------------

    protected function resolveActual(array $condition, array $scope): mixed
    {
        $source = $condition['source'] ?? 'question';
        $key    = $condition['key'] ?? $condition['question'] ?? null;

        return match ($source) {
            'answer' => $this->resolveAnswerSource($condition, $scope, $key),
            'question'       => $key ? ($scope['answers'][$key] ?? null) : null,
            'profile.medical'  => $key ? data_get($scope['profile']['medical'] ?? [], $key) : null,
            'profile.personal' => $key ? data_get($scope['profile']['personal'] ?? [], $key) : null,
            'session'        => $key ? data_get($scope['session'] ?? [], $key) : null,
            'context'        => $key ? data_get($scope['session']['context'] ?? [], $key) : null,
            'current_question' => $key ? data_get($scope['question'] ?? [], $key) : null,
            default          => null,
        };
    }

    private function resolveAnswerSource(array $condition, array $scope, mixed $key): mixed
    {
        if ($key === null || $key === '' || $key === 'value') {
            return $scope['answer']['value'] ?? null;
        }

        if ($key === 'raw') {
            return $scope['answer']['raw'] ?? null;
        }

        return data_get($scope['answer'], $key);
    }
}
