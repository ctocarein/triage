<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Services\BlocRulesService;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use Illuminate\Support\Collection;
use App\Support\ComparesValues;


class QuestionFlowService
{
    use ComparesValues;
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
        $answers = $session->answers->keyBy('question_id')->map->value->toArray();
        return [
            'answers' => $answers,
            'profile' => [
                'medical' => optional($session->identity->medicalProfile)->toArray() ?? [],
                'personal' => optional($session->identity->personalProfile)->toArray() ?? [],
            ],
            'answeredIds' => array_keys($answers),
        ];
    }

    public function goToNextQuestion(EvaluationSession $session, $groupIds = []): ?DynamicQuestionTemplate
    {
        $context = $this->getSessionContext($session);
        $forcedQuestion = $this->consumeForcedQuestion($session, $context['answers'], $context['profile']);
        if ($forcedQuestion) {
            $session->current_bloc = $forcedQuestion->bloc_key;
            $session->save();
            return $forcedQuestion;
        }

        // ⚠️ Toujours récupérer les bons groupIds si non passés explicitement
        if (empty($groupIds) && isset($session->context['selected_group_ids'])) {
            $groupIds = $session->context['selected_group_ids'];
        }

        $allTemplates = $this->getAllTemplates();

        if ($session->type === 'thematic' && !empty($groupIds)) {
            $allTemplates = $allTemplates->whereIn('question_group_id', $groupIds);
        }

        $blocs = $allTemplates->pluck('bloc_key')->filter()->unique();
        $forcedBloc = $this->consumeForcedBloc($session, $allTemplates, $context['answeredIds'], $context['answers'], $context['profile']);
        if ($forcedBloc) {
            $blocs = collect([$forcedBloc])->merge($blocs->reject(fn($bloc) => $bloc === $forcedBloc))->values();
        }

        foreach ($blocs as $blocKey) {
            if ($session->hasVisitedBloc($blocKey)) continue;
            if ($this->shouldSkipBloc($blocKey, $session)) {
                $session->addVisitedBloc($blocKey);
                continue;
            }
            $candidates = $allTemplates->where('bloc_key', $blocKey)
                ->filter(fn($q) => !in_array($q->id, $context['answeredIds']));

            if ($candidates->isEmpty()) {
                $session->addVisitedBloc($blocKey);
                continue;
            }

            $question = $candidates->first(fn($q) =>
                $this->shouldBeVisible($q, $context['answers'], $context['profile'])
            );
            if (!$question) {
                $session->addVisitedBloc($blocKey);
                continue;
            }

            $session->current_bloc = $blocKey;
            $session->save();
            return $question;
        }

        $session->markCompleted();
        return null;
    }

    public function getNextQuestions(EvaluationSession $session): array
    {
        $context = $this->getSessionContext($session);
        $forcedQuestion = $this->consumeForcedQuestion($session, $context['answers'], $context['profile']);
        if ($forcedQuestion) {
            $session->current_bloc = $forcedQuestion->bloc_key;
            $session->save();
            return [$forcedQuestion];
        }

        // Filtrage thématique (ajoute cette partie)
        $groupIds = $session->context['selected_group_ids'] ?? [];
        $allTemplates = $this->getAllTemplates();
        if ($session->type === 'thematic' && !empty($groupIds)) {
            $allTemplates = $allTemplates->whereIn('question_group_id', $groupIds);
        }

        $blocs = $allTemplates->pluck('bloc_key')->unique()->values();
        $forcedBloc = $this->consumeForcedBloc($session, $allTemplates, $context['answeredIds'], $context['answers'], $context['profile']);
        if ($forcedBloc) {
            $blocs = collect([$forcedBloc])->merge($blocs->reject(fn($bloc) => $bloc === $forcedBloc))->values();
        }
        $blocActuel = $session->current_bloc;

        if (!$blocActuel || !$this->blocHasRemainingQuestionsFrom($blocActuel, $context['answeredIds'], $allTemplates)) {
            $blocActuel = $blocs->first(fn($bloc) => $this->blocHasRemainingQuestionsFrom($bloc, $context['answeredIds'], $allTemplates));
            $session->current_bloc = $blocActuel;
            $session->save();
        }

        if (!$blocActuel) {
            $session->update(['is_completed' => true]);
            return [];
        }

        return $allTemplates
            ->where('bloc_key', $blocActuel)
            ->sortBy('order')
            ->filter(fn($q) => $this->shouldBeVisible($q, $context['answers'], $context['profile']))
            ->values()
            ->all();
    }

    protected function blocHasRemainingQuestionsFrom(string $blocKey, array $answeredIds, $allTemplates): bool
    {
        return $allTemplates
            ->where('bloc_key', $blocKey)
            ->some(fn($q) => !in_array($q->id, $answeredIds));
    }

   

    public function shouldBeVisible(DynamicQuestionTemplate $question, array $answers, array $profile): bool
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

    public function applyRoutingRules(EvaluationSession $session, DynamicQuestionTemplate $question, mixed $answerValue): ?array
    {
        $rules = $question->routing_rules;
        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }

        if (!is_array($rules) || empty($rules)) {
            return null;
        }

        $normalizedAnswer = $this->normalizeAnswerValue($answerValue);

        foreach ($rules as $rule) {
            if (!is_array($rule) || !array_key_exists('next', $rule)) {
                continue;
            }

            $condition = $rule['if'] ?? null;
            if (!$this->evaluateRoutingCondition($condition, $normalizedAnswer)) {
                continue;
            }

            return $this->applyRoutingNext($session, (string) $rule['next'], $question);
        }

        return null;
    }

    protected function evaluateRoutingCondition(mixed $condition, mixed $answerValue): bool
    {
        if ($condition === null) {
            return false;
        }

        if (is_array($condition)) {
            if (isset($condition['all']) && is_array($condition['all'])) {
                foreach ($condition['all'] as $item) {
                    if (!$this->evaluateRoutingCondition($item, $answerValue)) {
                        return false;
                    }
                }
                return true;
            }

            if (isset($condition['any']) && is_array($condition['any'])) {
                foreach ($condition['any'] as $item) {
                    if ($this->evaluateRoutingCondition($item, $answerValue)) {
                        return true;
                    }
                }
                return false;
            }

            if (array_key_exists('value', $condition) || array_key_exists('operator', $condition)) {
                $expected = $condition['value'] ?? null;
                $operator = $condition['operator'] ?? '=';
                return $this->compare($answerValue, $expected, $operator);
            }

            $keys = array_keys($condition);
            if ($keys === range(0, count($keys) - 1)) {
                return $this->compare($answerValue, $condition, 'in');
            }
        }

        return $this->compare($answerValue, $condition, '=');
    }

    protected function applyRoutingNext(EvaluationSession $session, string $next, DynamicQuestionTemplate $question): array
    {
        $context = $this->normalizeSessionContext($session);
        $context = $this->clearForcedRouting($context);

        if ($next === 'end_session') {
            $session->markCompleted();
            $this->updateSessionContext($session, $context);
            return ['action' => 'end_session'];
        }

        if (str_starts_with($next, 'level:')) {
            $context['level'] = substr($next, strlen('level:'));
            $this->updateSessionContext($session, $context);
            return ['action' => 'set_level', 'level' => $context['level']];
        }

        if (str_starts_with($next, 'bloc:')) {
            $blocKey = substr($next, strlen('bloc:'));
            return $this->forceBloc($session, $context, $blocKey, $question->bloc_key);
        }

        if (str_starts_with($next, 'question:')) {
            $questionKey = substr($next, strlen('question:'));
            return $this->forceQuestion($session, $context, $questionKey);
        }

        if (DynamicQuestionTemplate::where('bloc_key', $next)->exists()) {
            return $this->forceBloc($session, $context, $next, $question->bloc_key);
        }

        if (DynamicQuestionTemplate::where('key', $next)->exists()) {
            return $this->forceQuestion($session, $context, $next);
        }

        return ['action' => 'noop', 'next' => $next];
    }

    protected function forceBloc(
        EvaluationSession $session,
        array $context,
        string $blocKey,
        ?string $currentBloc
    ): array {
        $context['forced_bloc'] = $blocKey;
        if ($currentBloc) {
            $session->addVisitedBloc($currentBloc);
        }
        $session->current_bloc = $blocKey;
        $session->save();
        $this->updateSessionContext($session, $context);

        return ['action' => 'jump_bloc', 'bloc_key' => $blocKey];
    }

    protected function forceQuestion(EvaluationSession $session, array $context, string $questionKey): array
    {
        $context['forced_question_key'] = $questionKey;
        $question = DynamicQuestionTemplate::where('key', $questionKey)->first();
        if ($question && $question->bloc_key) {
            $session->current_bloc = $question->bloc_key;
            $session->save();
        }
        $this->updateSessionContext($session, $context);

        return ['action' => 'jump_question', 'question_key' => $questionKey];
    }

    protected function consumeForcedQuestion(
        EvaluationSession $session,
        array $answers,
        array $profile
    ): ?DynamicQuestionTemplate {
        $context = $this->normalizeSessionContext($session);
        $forcedKey = $context['forced_question_key'] ?? null;
        if (!$forcedKey) {
            return null;
        }

        unset($context['forced_question_key']);
        $this->updateSessionContext($session, $context);

        $question = DynamicQuestionTemplate::where('key', $forcedKey)->first();
        if (!$question) {
            return null;
        }

        if (array_key_exists($question->id, $answers)) {
            return null;
        }

        if (!$this->shouldBeVisible($question, $answers, $profile)) {
            return null;
        }

        return $question;
    }

    protected function consumeForcedBloc(
        EvaluationSession $session,
        Collection $allTemplates,
        array $answeredIds,
        array $answers,
        array $profile
    ): ?string {
        $context = $this->normalizeSessionContext($session);
        $forcedBloc = $context['forced_bloc'] ?? null;
        if (!$forcedBloc) {
            return null;
        }

        unset($context['forced_bloc']);
        $this->updateSessionContext($session, $context);

        $candidates = $allTemplates->where('bloc_key', $forcedBloc)
            ->filter(fn($q) => !in_array($q->id, $answeredIds))
            ->filter(fn($q) => $this->shouldBeVisible($q, $answers, $profile));

        return $candidates->isEmpty() ? null : $forcedBloc;
    }

    protected function normalizeSessionContext(EvaluationSession $session): array
    {
        $context = $session->context ?? [];
        return is_array($context) ? $context : [];
    }

    protected function updateSessionContext(EvaluationSession $session, array $context): void
    {
        $session->update(['context' => $context]);
    }

    protected function clearForcedRouting(array $context): array
    {
        unset($context['forced_bloc'], $context['forced_question_key']);
        return $context;
    }

    protected function normalizeAnswerValue(mixed $answerValue): mixed
    {
        if (is_string($answerValue)) {
            $trim = trim($answerValue);
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

        return $answerValue;
    }


    public function calculateScore(EvaluationSession $session): array
    {
        $answers = $session->answers->keyBy('question_id');
        $blocs = [];

        foreach ($this->getAllTemplates() as $question) {
            $bloc = $question->bloc_key ?? 'undefined';
            $answer = $answers->get($question->id);
            $value = optional($answer)->value;

            // Applique la logique booléenne spécifique
            if ($question->type === 'boolean' && $question->risk_weight > 0) {
                // Si la réponse est "oui"/true, on ajoute le poids, sinon non
                if (in_array($value, [true, 1, "1", "oui", "yes"], true)) {
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
            // Autres types : si répondu et risk_weight > 0
            elseif ($value !== null && $question->risk_weight > 0) {
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

        // Niveau de risque/bloc si zones définies
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
        foreach (config('risk-level.levels') as $zone) {
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

