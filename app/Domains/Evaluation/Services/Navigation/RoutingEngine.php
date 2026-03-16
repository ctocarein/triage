<?php

namespace App\Domains\Evaluation\Services\Navigation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\EvaluationSession;
use Illuminate\Support\Collection;

/**
 * Moteur de routage et état de navigation de session.
 *
 * Responsabilités :
 *  - Évaluer les `routing_rules` d'une question après une réponse
 *  - Appliquer l'action de navigation résultante (jump, queue, end_session…)
 *  - Consommer l'état de navigation stocké dans `session.context`
 *    (forced_question_key, forced_bloc, question_queue)
 */
class RoutingEngine
{
    public function __construct(
        protected ConditionEvaluator $conditions,
    ) {}

    // =========================================================================
    // API publique
    // =========================================================================

    /**
     * Évalue les routing_rules de la question courante après qu'une réponse
     * a été enregistrée. Retourne l'action de navigation ou null si aucune
     * règle ne correspond.
     */
    public function applyRoutingRules(
        EvaluationSession $session,
        DynamicQuestionTemplate $question,
        mixed $answerValue,
        array $sessionContext,
    ): ?array {
        if (! $session->isInProgress()) {
            return null;
        }

        $rules = $question->routing_rules;
        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }

        if (! is_array($rules) || empty($rules)) {
            return null;
        }

        $scope = $this->conditions->buildScope(
            $sessionContext['answers'] ?? [],
            $sessionContext['profile'] ?? [],
            $answerValue,
            $session,
            $question,
        );

        foreach ($rules as $rule) {
            if (! is_array($rule) || ! array_key_exists('next', $rule)) {
                continue;
            }

            $condition = $rule['if'] ?? null;
            if (! $this->conditions->evaluate($condition, $scope)) {
                continue;
            }

            return $this->applyNext($session, (string) $rule['next'], $question);
        }

        return null;
    }

    /**
     * Consomme l'état de navigation forcée (slot unique + file de prérequis).
     *
     * Priorité :
     *  1. `forced_question_key` — saut immédiat vers une question précise
     *  2. `question_queue`      — file ordonnée de prérequis
     *
     * Skips silencieux si la question est déjà répondue ou non visible.
     */
    public function consumeForcedQuestion(
        EvaluationSession $session,
        array $answers,
        array $profile,
    ): ?DynamicQuestionTemplate {
        $context = $this->readContext($session);

        // 1. Slot unique (priorité haute — saut immédiat)
        $forcedKey = $context['forced_question_key'] ?? null;
        if ($forcedKey) {
            unset($context['forced_question_key']);
            $this->writeContext($session, $context);

            $question = DynamicQuestionTemplate::where('key', $forcedKey)->first();
            if ($question
                && ! array_key_exists($question->id, $answers)
                && $this->conditions->shouldBeVisible($question, $answers, $profile)
            ) {
                return $question;
            }
            // Question déjà connue → on tombe dans la queue ci-dessous
        }

        // 2. File ordonnée de prérequis
        $queue = $context['question_queue'] ?? [];
        while (! empty($queue)) {
            $key      = array_shift($queue);
            $question = DynamicQuestionTemplate::where('key', $key)->first();

            if (! $question) {
                continue; // clé introuvable → skip silencieux
            }
            if (array_key_exists($question->id, $answers)) {
                continue; // déjà répondu → skip
            }
            if (! $this->conditions->shouldBeVisible($question, $answers, $profile)) {
                continue; // masquée (valeur dans profil) → skip
            }

            $context['question_queue'] = array_values($queue);
            $this->writeContext($session, $context);
            return $question;
        }

        // File épuisée — nettoyage
        if (isset($context['question_queue'])) {
            unset($context['question_queue']);
            $this->writeContext($session, $context);
        }

        return null;
    }

    /**
     * Consomme un bloc forcé. Retourne la clé du bloc si des questions
     * visibles et non répondues y restent, null sinon (skip silencieux).
     */
    public function consumeForcedBloc(
        EvaluationSession $session,
        Collection $allTemplates,
        array $answeredIds,
        array $answers,
        array $profile,
    ): ?string {
        $context    = $this->readContext($session);
        $forcedBloc = $context['forced_bloc'] ?? null;

        if (! $forcedBloc) {
            return null;
        }

        unset($context['forced_bloc']);
        $this->writeContext($session, $context);

        $candidates = $allTemplates
            ->where('bloc_key', $forcedBloc)
            ->filter(fn ($q) => ! in_array($q->id, $answeredIds))
            ->filter(fn ($q) => $this->conditions->shouldBeVisible($q, $answers, $profile));

        return $candidates->isEmpty() ? null : $forcedBloc;
    }

    // =========================================================================
    // Accès au contexte de navigation (public pour QuestionFlowService)
    // =========================================================================

    public function readContext(EvaluationSession $session): array
    {
        $context = $session->context ?? [];
        return is_array($context) ? $context : [];
    }

    public function writeContext(EvaluationSession $session, array $context): void
    {
        $session->update(['context' => $context]);
    }

    // =========================================================================
    // Actions de navigation (privées)
    // =========================================================================

    protected function applyNext(
        EvaluationSession $session,
        string $next,
        DynamicQuestionTemplate $question,
    ): array {
        $context = $this->readContext($session);
        $context = $this->clearNavigation($context);

        if ($next === 'end_session' || str_starts_with($next, 'end_session:')) {
            $reason = null;
            if (str_contains($next, ':')) {
                $reason = substr($next, strlen('end_session:'));
                $reason = $reason === '' ? null : $reason;
            }
            $session->markCancelled($reason ?? 'routing_end');
            $this->writeContext($session, $context);
            return ['action' => 'end_session', 'status' => 'cancelled', 'reason' => $reason];
        }

        if (str_starts_with($next, 'level:')) {
            $context['level'] = substr($next, strlen('level:'));
            $this->writeContext($session, $context);
            return ['action' => 'set_level', 'level' => $context['level']];
        }

        if (str_starts_with($next, 'bloc:')) {
            return $this->forceBloc($session, $context, substr($next, strlen('bloc:')), $question->bloc_key);
        }

        if (str_starts_with($next, 'question:')) {
            return $this->forceQuestion($session, $context, substr($next, strlen('question:')));
        }

        if (str_starts_with($next, 'queue:')) {
            $keys = array_values(array_filter(array_map('trim', explode(',', substr($next, strlen('queue:'))))));
            return $this->enqueue($session, $context, $keys);
        }

        // Résolution automatique par valeur brute (bloc_key ou question key)
        if (DynamicQuestionTemplate::where('bloc_key', $next)->exists()) {
            return $this->forceBloc($session, $context, $next, $question->bloc_key);
        }

        if (DynamicQuestionTemplate::where('key', $next)->exists()) {
            return $this->forceQuestion($session, $context, $next);
        }

        return ['action' => 'noop', 'next' => $next];
    }

    private function forceBloc(
        EvaluationSession $session,
        array $context,
        string $blocKey,
        ?string $currentBloc,
    ): array {
        $context['forced_bloc'] = $blocKey;
        if ($currentBloc) {
            $session->addVisitedBloc($currentBloc);
        }
        $session->current_bloc = $blocKey;
        $session->save();
        $this->writeContext($session, $context);

        return ['action' => 'jump_bloc', 'bloc_key' => $blocKey];
    }

    private function forceQuestion(EvaluationSession $session, array $context, string $questionKey): array
    {
        $context['forced_question_key'] = $questionKey;
        $question = DynamicQuestionTemplate::where('key', $questionKey)->first();
        if ($question?->bloc_key) {
            $session->current_bloc = $question->bloc_key;
            $session->save();
        }
        $this->writeContext($session, $context);

        return ['action' => 'jump_question', 'question_key' => $questionKey];
    }

    private function enqueue(EvaluationSession $session, array $context, array $keys): array
    {
        $context['question_queue'] = array_values(
            array_merge($context['question_queue'] ?? [], $keys)
        );
        $this->writeContext($session, $context);

        return ['action' => 'queue_questions', 'keys' => $keys];
    }

    private function clearNavigation(array $context): array
    {
        unset($context['forced_bloc'], $context['forced_question_key'], $context['question_queue']);
        return $context;
    }
}
