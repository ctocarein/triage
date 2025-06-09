<?php

namespace App\Services\Evaluation;

use App\Services\Evaluation\BlocRulesService;
use App\Models\Evaluation\QuestionGroup;
use App\Models\Evaluation\EvaluationSession;
use App\Models\Evaluation\DynamicQuestionTemplate;
use Illuminate\Support\Collection;

class QuestionFlowService
{
    protected ?Collection $cachedTemplates = null;
    protected BlocRulesService $blocRules;

    public function __construct(BlocRulesService $blocRules)
    {
        $this->blocRules = $blocRules;
    }

    public function getEntryPoints(?string $target = null): Collection
    {
        $collection = $this->getAllTemplates()->where('is_entry_point', true);
        return $target ? $collection->where('answer_target', $target)->values() : $collection->values();
    }

    protected function getAllTemplates(): Collection
    {
        return $this->cachedTemplates ??= DynamicQuestionTemplate::orderBy('order')->get();
    }

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

    public function goToNextQuestion(EvaluationSession $session): ?DynamicQuestionTemplate
    {
        $context = $this->getSessionContext($session);
        $blocs = $this->getAllTemplates()->pluck('bloc_key')->filter()->unique();

        if (!empty($session->target_blocs)) {
            $blocs = $blocs->filter(fn($b) => in_array($b, $session->target_blocs));
        }

        foreach ($blocs as $blocKey) {
            if ($session->hasVisitedBloc($blocKey)) continue;
            if ($this->shouldSkipBloc($blocKey, $session)) {
                $session->addVisitedBloc($blocKey);
                continue;
            }

            $question = $this->getAllTemplates()
                ->where('bloc_key', $blocKey)
                ->first(fn($q) => !in_array($q->id, $context['answeredIds']) && $this->shouldBeVisible($q, $context['answers'], $context['profile']));

            if ($question) {
                $session->current_bloc = $blocKey;
                $session->save();
                return $question;
            } else {
                $session->addVisitedBloc($blocKey);
            }
        }

        $session->update(['is_completed' => true]);
        return null;
    }

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

    protected function blocHasRemainingQuestions(string $blocKey, array $answeredIds): bool
    {
        return $this->getAllTemplates()
            ->where('bloc_key', $blocKey)
            ->some(fn($q) => !in_array($q->id, $answeredIds));
    }

    private function shouldBeVisible(DynamicQuestionTemplate $question, array $answers, array $profile): bool
    {
        if (in_array($question->answer_target, ['medical_profile', 'personal_profile']) && $this->hasValueInProfile($question, $profile)) {
            return false;
        }

        $visibleIf = is_string($question->visible_if)
            ? json_decode($question->visible_if, true)
            : $question->visible_if;

        return $this->isVisible($visibleIf ?? [], $answers, $profile);
    }

    private function hasValueInProfile(DynamicQuestionTemplate $question, array $profile): bool
    {
        return !empty($profile[$question->answer_target][$question->key] ?? null);
    }

    private function isVisible(array $visibleIf, array $answers, array $profile): bool
    {
        foreach ($visibleIf as $condition) {
            $source = $condition['source'] ?? 'question';
            $key = $condition['key'] ?? $condition['question'] ?? null;
            $value = $condition['value'] ?? null;
            $op = $condition['operator'] ?? '=';

            $actual = match ($source) {
                'question' => $answers[$key] ?? null,
                'profile.medical' => $profile['medical'][$key] ?? null,
                'profile.personal' => $profile['personal'][$key] ?? null,
                default => null,
            };

            if (!$this->compare($actual, $value, $op)) return false;
        }

        return true;
    }

    private function compare($actual, $expected, string $operator): bool
    {
        return match ($operator) {
            '=' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '<' => $actual < $expected,
            'in' => in_array($actual, (array) $expected),
            'not_in' => !in_array($actual, (array) $expected),
            default => false,
        };
    }

    public function calculateScore(EvaluationSession $session): array
    {
        $answers = $session->answers->keyBy('dynamic_question_template_id');
        $blocs = [];

        foreach ($this->getAllTemplates() as $question) {
            $bloc = $question->bloc_key ?? 'undefined';
            $answer = $answers->get($question->id);
            $value = optional($answer)->value;

            if ($value !== null && $question->risk_weight > 0) {
                $blocs[$bloc] ??= [
                    'score' => 0,
                    'questions' => [],
                    'level' => null,
                    'meta' => $question->meta ?? []
                ];

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

    public function getActiveGroups(EvaluationSession $session): Collection
    {
        $groups = QuestionGroup::where('is_active', true)->orderBy('order')->get();
        $context = $this->getSessionContext($session);

        return $groups->filter(function ($group) use ($context) {
            $conditions = is_string($group->conditions)
                ? json_decode($group->conditions, true)
                : $group->conditions;

            return empty($conditions) || $this->isVisible($conditions, $context['answers'], $context['profile']);
        })->values();
    }

    protected function shouldSkipBloc(string $blocKey, EvaluationSession $session): bool
    {
        return $this->blocRules->shouldSkip($blocKey, $session);
    }
}
