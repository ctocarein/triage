<?php

namespace App\Services\Evaluation;

use App\Models\Evaluation\QuestionGroup;
use App\Models\Evaluation\EvaluationSession;
use App\Models\Evaluation\DynamicQuestionTemplate;
use Illuminate\Support\Collection;

class QuestionFlowService
{
    protected ?Collection $cachedTemplates = null;


       /**
     * Retourne les questions marquées comme point d'entrée.
     */
    public function getEntryPoints(?string $target = null): Collection
    {
        $collection = $this->getAllTemplates()
            ->where('is_entry_point', true);

        if ($target) {
            $collection = $collection->where('answer_target', $target);
        }

        return $collection->values();
    }

    /**
     * Charge et met en cache toutes les questions de l'application.
     */
    protected function getAllTemplates(): Collection
    {
        if (!$this->cachedTemplates) {
            $this->cachedTemplates = DynamicQuestionTemplate::orderBy('order')->get();
        }
        return $this->cachedTemplates;
    }

    /**
     * Retourne le contexte d'une session : profils, réponses, IDs répondus.
     */
    public function getSessionContext(EvaluationSession $session): array
    {
        $answers = $session->answers->keyBy('dynamic_question_template_id')->map->value->toArray();
        return [
            'answers' => $answers,
            'profile' => [
                'medical' => optional($session->account->medicalProfile)->toArray() ?? [],
                'personal' => optional($session->account->personalProfile)->toArray() ?? [],
            ],
            'answeredIds' => array_keys($answers),
        ];
    }

    /**
     * Retourne la prochaine question à poser dans le flux dynamique.
     */
    public function goToNextQuestion(EvaluationSession $session): ?DynamicQuestionTemplate
    {
        $context = $this->getSessionContext($session);

        foreach ($this->getAllTemplates() as $question) {
            if (in_array($question->id, $context['answeredIds'])) continue;

            if ($this->shouldBeVisible($question, $context['answers'], $context['profile'])) {
                $session->current_bloc = $question->bloc_key;
                $session->save();
                return $question;
            }
        }

        $session->update(['is_completed' => true]);
        return null;
    }

    /**
     * Retourne toutes les questions visibles du bloc en cours.
     */
    public function getNextQuestions(EvaluationSession $session): array
    {
        $context = $this->getSessionContext($session);

        $blocs = $this->getAllTemplates()->pluck('bloc_key')->unique()->values();

        $blocActuel = $session->current_bloc;

        if (!$blocActuel || !$this->blocHasRemainingQuestions($blocActuel, $context['answeredIds'])) {
            $blocActuel = $blocs->first(fn($bloc) => $this->blocHasRemainingQuestions($bloc, $context['answeredIds']));
            $session->current_bloc = $blocActuel;
            $session->save();
        }

        if (!$blocActuel) {
            $session->update(['is_completed' => true]);
            return [];
        }

        return $this->getAllTemplates()
            ->where('bloc_key', $blocActuel)
            ->sortBy('order')
            ->filter(fn($q) => $this->shouldBeVisible($q, $context['answers'], $context['profile']))
            ->values()
            ->all();

    
    }

    /**
     * Détermine si un bloc contient encore des questions non répondues.
     */
    protected function blocHasRemainingQuestions(string $blocKey, array $answeredIds): bool
    {
        return $this->getAllTemplates()
            ->where('bloc_key', $blocKey)
            ->filter(fn($q) => !in_array($q->id, $answeredIds))
            ->isNotEmpty();
    }

    /**
     * Détermine si une question est visible, selon conditions et profil.
     */
    private function shouldBeVisible(DynamicQuestionTemplate $question, array $answers, array $profile): bool
    {
        if (
            in_array($question->answer_target, ['medical_profile', 'personal_profile']) &&
            $this->hasValueInProfile($question, $profile)
        ) return false;

        $visibleIf = is_string($question->visible_if)
            ? json_decode($question->visible_if, true)
            : $question->visible_if;

        return $this->isVisible($visibleIf ?? [], $answers, $profile);
    }

    /**
     * Vérifie si une donnée est déjà présente dans le profil.
     */
    private function hasValueInProfile(DynamicQuestionTemplate $question, array $profile): bool
    {
        $target = $question->answer_target;
        $key = $question->key;
        return !empty($profile[$target][$key] ?? null);
    }

    /**
     * Applique la logique conditionnelle de visibilité.
     */
    private function isVisible(array $visibleIf, array $answers, array $profile): bool
    {
        foreach ($visibleIf as $condition) {
            $source = $condition['source'] ?? 'question';
            $key    = $condition['key'] ?? $condition['question'] ?? null;
            $value  = $condition['value'] ?? null;
            $op     = $condition['operator'] ?? '=';

            $actual = match ($source) {
                'question'         => $answers[$key] ?? null,
                'profile.medical'  => $profile['medical'][$key] ?? null,
                'profile.personal' => $profile['personal'][$key] ?? null,
                default            => null,
            };

            if (!$this->compare($actual, $value, $op)) return false;
        }

        return true;
    }

    /**
     * Effectue la comparaison entre une valeur et une condition.
     */
    private function compare($actual, $expected, string $operator): bool
    {
        return match ($operator) {
            '='       => $actual == $expected,
            '!='      => $actual != $expected,
            '>'       => $actual > $expected,
            '<'       => $actual < $expected,
            'in'      => in_array($actual, (array) $expected),
            'not_in'  => !in_array($actual, (array) $expected),
            default   => false,
        };
    }

    /**
     * Calcule le score total par bloc à partir des réponses d'une session.
     */
    public function calculateScore(EvaluationSession $session): array
    {
        $answers = $session->answers->keyBy('dynamic_question_template_id');
        $blocs = [];

        foreach ($this->getAllTemplates() as $question) {
            $bloc = $question->bloc_key ?? 'undefined';
            $answer = $answers->get($question->id);
            $value = optional($answer)->value;

            if ($value !== null && $question->risk_weight > 0) {
                if (!isset($blocs[$bloc])) {
                    $blocs[$bloc] = [
                        'score' => 0,
                        'questions' => [],
                        'level' => null,
                        'meta' => $question->meta ?? []
                    ];
                }

                $blocs[$bloc]['score'] += $question->risk_weight;
                $blocs[$bloc]['questions'][] = $question->key;
            }
        }

        foreach ($blocs as $bloc => &$data) {
            $zones = $data['meta']['score_zone'] ?? null;
            if (is_array($zones)) {
                foreach ($zones as $z) {
                    if ($data['score'] >= $z['min'] && $data['score'] <= $z['max']) {
                        $data['level'] = $z['level'];
                        break;
                    }
                }
            }
        }

        return $blocs;
    }

    /**
     * Retourne le score global et le niveau de risque déduit.
     */
    public function evaluateGlobalRisk(EvaluationSession $session): array
    {
        $blocs = $this->calculateScore($session);
        $totalScore = collect($blocs)->sum('score');

        $level = 'non déterminé';
        foreach (config('chereh_risk.levels') as $zone) {
            if ($totalScore >= $zone['min'] && $totalScore <= $zone['max']) {
                $level = $zone['name'];
                break;
            }
        }

        return [
            'total_score' => $totalScore,
            'level' => $level,
            'blocs' => $blocs
        ];
    }

    /**
     * Retourne les groupes actifs pour cette session, filtrés par condition logique.
     */
    public function getActiveGroups(EvaluationSession $session): Collection
    {
        $groups = QuestionGroup::where('is_active', true)->orderBy('order')->get();

        $context = $this->getSessionContext($session);

        return $groups->filter(function ($group) use ($context) {
            $conditions = is_string($group->conditions)
                ? json_decode($group->conditions, true)
                : $group->conditions;

            if (!is_array($conditions) || empty($conditions)) return true;

            return $this->isVisible($conditions, $context['answers'], $context['profile']);
        })->values();
    }

    /**
     * Détermine le groupe suivant dans l'ordre logique, avec sauts conditionnels.
     */
    public function nextGroup(?string $currentGroupKey, EvaluationSession $session): ?string
    {
        $groups = $this->getActiveGroups($session)->pluck('key')->toArray();

        if (empty($groups)) return null;

        if (is_null($currentGroupKey)) {
            $currentGroupKey = $session->currentQuestion?->group?->key ?? null;
            if (is_null($currentGroupKey)) return $groups[0];
        }

        $index = array_search($currentGroupKey, $groups);
        if ($index === false || $index + 1 >= count($groups)) return null;

        $nextKey = $groups[$index + 1];
        $age = optional($session->account->personalProfile)->age();

        // Sauts personnalisés
        if ($nextKey === 'symptom_cervix' && $age < 22) {
            return $this->nextGroup($nextKey, $session);
        }

        if ($nextKey === 'symptom_breast' && $age < 25) {
            return $this->nextGroup($nextKey, $session);
        }

        return $nextKey;
    }
}
