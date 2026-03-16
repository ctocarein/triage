<?php

namespace App\Domains\Evaluation\Services\Navigation;

use App\Domains\Evaluation\Enums\QuestionType;
use App\Domains\Evaluation\Services\Scoring\RiskModelService;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use Illuminate\Support\Collection;


class QuestionSequencer
{
    protected ?Collection $cachedTemplates = null;

    public function __construct(
        protected BlocRulesService $blocRules,
        protected AdaptiveSequencer $adaptive,
        protected RiskModelService $riskModel,
        protected ConditionEvaluator $conditions,
        protected RoutingEngine $routing,
    ) {}


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
        $answersById  = [];
        $answersByKey = [];

        foreach ($session->answers()->with('question:id,key')->get() as $answer) {
            $normalized = $this->conditions->normalize($answer->value);
            $answersById[$answer->dynamic_question_template_id] = $normalized;

            $questionKey = $answer->question?->key;
            if ($questionKey) {
                $answersByKey[$questionKey] = $normalized;
            }
        }

        $profile = [
            'medical'  => optional($session->identity->medicalProfile)->toArray() ?? [],
            'personal' => optional($session->identity->personalProfile)->toArray() ?? [],
        ];

        // Injecter les champs du profil persisté dans le contexte des réponses.
        // Les réponses de la session courante restent prioritaires (opérateur ??=).
        // Permet aux conditions visible_if (source:question) de résoudre les champs
        // de profil capturés lors d'une session complète précédente.
        foreach ($profile as $fields) {
            foreach ($fields as $key => $value) {
                if ($value !== null) {
                    $answersByKey[$key] ??= $value;
                }
            }
        }

        return [
            'answers'       => $answersByKey,
            'answers_by_id' => $answersById,
            'profile'       => $profile,
            'answeredIds'   => array_keys($answersById),
        ];
    }

    public function goToNextQuestion(EvaluationSession $session, $groupIds = []): ?DynamicQuestionTemplate
    {
        if (!$session->isInProgress()) {
            return null;
        }

        $context = $this->getSessionContext($session);
        $forcedQuestion = $this->routing->consumeForcedQuestion($session, $context['answers'], $context['profile']);
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

        $blocs = $this->getOrderedBlocKeys($allTemplates);
        $forcedBloc = $this->routing->consumeForcedBloc($session, $allTemplates, $context['answeredIds'], $context['answers'], $context['profile']);
        if ($forcedBloc) {
            $blocs = collect([$forcedBloc])->merge($blocs->reject(fn($bloc) => $bloc === $forcedBloc))->values();
        } elseif ($this->isAdaptiveEnabled()) {
            $unvisited = $blocs->reject(fn($bloc) => $session->hasVisitedBloc($bloc));
            $sorted    = $this->adaptive->prioritizeBlocs(
                $unvisited,
                $allTemplates,
                $context['answeredIds'],
                $context['answers'],
                $context['profile'],
                $this,
            );
            $blocs = $sorted->merge($blocs->filter(fn($bloc) => $session->hasVisitedBloc($bloc)))->values();
        }

        foreach ($blocs as $blocKey) {
            if ($session->hasVisitedBloc($blocKey)) continue;
            $skip = $this->shouldSkipBloc($blocKey, $context['answers'], $context['profile']);
            if ($skip) {
                $session->addVisitedBloc($blocKey);
                continue;
            }
            $candidates = $allTemplates->where('bloc_key', $blocKey)
                ->filter(fn($q) => !in_array($q->id, $context['answeredIds']));

            // Exclure les questions de profil dans les sessions non-globales :
            // le profil a déjà été saisi lors de la session complete initiale.
            if ($this->isProfileAlreadyCaptured($session)) {
                $candidates = $candidates->filter(
                    fn($q) => ! in_array($q->answer_target, ['personal_profile', 'medical_profile'], true)
                );
            }

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

        // Early exit adaptatif : si le potentiel restant ne peut plus changer
        // de zone de risque, on complète directement.
        if ($this->isEarlyExitEnabled()) {
            $remainingBlocs = $blocs->reject(fn($b) => $session->hasVisitedBloc($b));
            if (! $this->adaptive->canChangeRiskLevel(
                (float) ($session->score ?? 0),
                $remainingBlocs,
                $allTemplates,
                $context['answeredIds'],
                $context['answers'],
                $context['profile'],
                $this,
            )) {
                $session->markCompleted();
                return null;
            }
        }

        $session->markCompleted();
        return null;
    }

    public function getNextQuestions(EvaluationSession $session): array
    {
        if (!$session->isInProgress()) {
            return [];
        }

        $context = $this->getSessionContext($session);
        $forcedQuestion = $this->routing->consumeForcedQuestion($session, $context['answers'], $context['profile']);
        if ($forcedQuestion) {
            $session->current_bloc = $forcedQuestion->bloc_key;
            $session->save();
            return [$forcedQuestion];
        }

        $groupIds = $session->context['selected_group_ids'] ?? [];
        $allTemplates = $this->getAllTemplates();
        if ($session->type === 'thematic' && !empty($groupIds)) {
            $allTemplates = $allTemplates->whereIn('question_group_id', $groupIds);
        }

        $blocs = $this->getOrderedBlocKeys($allTemplates);
        $forcedBloc = $this->routing->consumeForcedBloc($session, $allTemplates, $context['answeredIds'], $context['answers'], $context['profile']);
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
            ->when(
                $this->isProfileAlreadyCaptured($session),
                fn ($c) => $c->filter(
                    fn ($q) => ! in_array($q->answer_target, ['personal_profile', 'medical_profile'], true)
                )
            )
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
        return $this->conditions->shouldBeVisible($question, $answers, $profile);
    }

    public function applyRoutingRules(EvaluationSession $session, DynamicQuestionTemplate $question, mixed $answerValue): ?array
    {
        return $this->routing->applyRoutingRules($session, $question, $answerValue, $this->getSessionContext($session));
    }

    public function calculateScore(EvaluationSession $session): array
    {
        $answers = $session->answers->keyBy('dynamic_question_template_id');
        $blocs = [];

        foreach ($this->getAllTemplates() as $question) {
            $bloc = $question->bloc_key ?? 'undefined';
            $answer = $answers->get($question->id);
            $value = optional($answer)->value;

            if ($question->type === QuestionType::Boolean && $question->risk_weight > 0) {
                if (in_array($value, [true, 1, "1", "oui", "yes"], true)) {
                    $blocs[$bloc] ??= ['score' => 0, 'questions' => [], 'level' => null, 'meta' => $question->meta ?? []];
                    $blocs[$bloc]['score'] += $question->risk_weight;
                    $blocs[$bloc]['questions'][] = $question->key;
                }
            } elseif ($value !== null && $question->risk_weight > 0) {
                $blocs[$bloc] ??= ['score' => 0, 'questions' => [], 'level' => null, 'meta' => $question->meta ?? []];
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
        // total_score et level sont délégués à RiskModelService (source canonique)
        // pour garantir la cohérence avec DecisionInsightService et le moteur facts-based.
        // calculateScore() fournit uniquement la ventilation par bloc (usage UI).
        $risk  = $this->riskModel->evaluate($session);
        $blocs = $this->calculateScore($session);

        return [
            'total_score' => $risk['score_total'],
            'level'       => $risk['risk_level'],
            'blocs'       => $blocs,
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

            return empty($conditions) || $this->conditions->isVisible($conditions, $context['answers'], $context['profile']);
        })->values();
    }

    protected function shouldSkipBloc(string $blocKey, array $answers, array $profile): bool
    {
        return $this->blocRules->shouldSkip($blocKey, $answers, $profile);
    }

    protected function isProfileAlreadyCaptured(EvaluationSession $session): bool
    {
        return ! $session->isFullEvaluation();
    }

    /**
     * Retourne les clés de blocs présents dans $allTemplates,
     * triés par ThematicBloc.order (source de vérité pour l'ordre du parcours).
     * Les blocs non référencés dans ThematicBloc apparaissent en fin de liste.
     */
    protected function getOrderedBlocKeys(Collection $allTemplates): Collection
    {
        $presentBlocKeys = $allTemplates->pluck('bloc_key')->filter()->unique()->values();

        $orderedFromThematic = \App\Domains\Evaluation\Models\ThematicBloc::where('is_active', true)
            ->orderBy('order')
            ->pluck('key')
            ->filter(fn ($key) => $presentBlocKeys->contains($key));

        $remaining = $presentBlocKeys->diff($orderedFromThematic)->values();

        return $orderedFromThematic->merge($remaining)->values();
    }

    protected function isAdaptiveEnabled(): bool
    {
        return (bool) config('vnext.adaptive_sequencing', false);
    }

    protected function isEarlyExitEnabled(): bool
    {
        return (bool) config('vnext.adaptive_early_exit', false);
    }
}
