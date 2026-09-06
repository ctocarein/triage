<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Enums\AnswerStatus;
use App\Domains\Evaluation\Models\EvaluationAnswer;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\Scoring\RiskModelService;

class AnswerService
{
    public function __construct(
        protected RiskModelService $riskModel,
    ) {}

    /**
     * Enregistre ou met a jour une reponse a une question.
     *
     * @param array $data Donnees validees (incluant session, question, reponse...).
     * @return EvaluationAnswer La reponse enregistree.
     * @throws \Exception Si la session est fermee ou invalide.
     */
    public function saveAnswer(array $data): EvaluationAnswer
    {
        $session = EvaluationSession::findOrFail($data['evaluation_session_id']);
        $questionId = $data['dynamic_question_template_id'] ?? null;

        if (! $questionId) {
            throw new \Exception('Question id manquant.');
        }

        $question = DynamicQuestionTemplate::findOrFail($questionId);

        if (! $session->isInProgress()) {
            throw new \Exception("La session est terminee, impossible d'enregistrer une reponse.");
        }

        $existing = EvaluationAnswer::where([
            'evaluation_session_id' => $session->id,
            'dynamic_question_template_id' => $question->id,
        ])->first();

        $payload = [
            'value' => $data['value'] ?? null,
            'file_ids' => $data['file_ids'] ?? null,
            'is_skipped' => $data['is_skipped'] ?? false,
            'status' => $this->resolveStatus($data),
        ];

        // Mise a jour de reponse existante
        if ($existing) {
            $valueChanged = $this->hasMeaningfulChange($existing, $payload);
            $existing->update($payload);
            if (! $existing->order) {
                $existing->forceFill(['order' => $this->resolveNextOrder($session)])->save();
            }
            if ($valueChanged) {
                $this->invalidateDownstreamAnswers($session, (int) ($existing->order ?? 0));

                // Reprendre dans le bloc de la question corrigee : l'evaluateur
                // vient de se reprendre, les questions que sa correction rend
                // caduques doivent revenir tout de suite et non apres un detour
                // par un autre bloc.
                if ($question->bloc_key) {
                    $session->forceFill(['current_bloc' => $question->bloc_key])->save();
                }
            }
            $this->syncSessionScore($session);

            return $existing;
        }

        // Nouvelle reponse
        $answer = EvaluationAnswer::create([
            'evaluation_session_id' => $session->id,
            'dynamic_question_template_id' => $question->id,
            'value' => $payload['value'],
            'file_ids' => $payload['file_ids'],
            'is_skipped' => $payload['is_skipped'],
            'status' => $payload['status'],
            'order' => $this->resolveNextOrder($session),
        ]);

        $this->syncSessionScore($session);

        return $answer;
    }

    protected function syncSessionScore(EvaluationSession $session): void
    {
        $risk = $this->riskModel->evaluate($session);

        $session->forceFill([
            'score' => round($risk['score_total'], 2),
            'risk_level' => $risk['risk_level'],
        ])->save();
    }

    protected function resolveStatus(array $data): string
    {
        if (!empty($data['status'])) {
            $raw = $data['status'] instanceof AnswerStatus
                ? $data['status']->value
                : (string) $data['status'];

            return AnswerStatus::from($raw)->value;
        }

        return !empty($data['is_skipped']) ? AnswerStatus::Skipped->value : AnswerStatus::Answered->value;
    }

    protected function resolveNextOrder(EvaluationSession $session): int
    {
        $max = (int) ($session->answers()->max('order') ?? 0);
        return $max + 1;
    }

    protected function hasMeaningfulChange(EvaluationAnswer $existing, array $payload): bool
    {
        if ((bool) $existing->is_skipped !== (bool) $payload['is_skipped']) {
            return true;
        }

        $current = json_encode($existing->value);
        $next = json_encode($payload['value'] ?? null);

        return $current !== $next;
    }

    protected function invalidateDownstreamAnswers(EvaluationSession $session, int $order): void
    {
        if ($order <= 0) {
            return;
        }

        $downstream = $session->answers()
            ->where('order', '>', $order)
            ->whereNotIn('status', [AnswerStatus::Invalidated->value])
            ->with('question:id,bloc_key')
            ->get();

        if ($downstream->isEmpty()) {
            return;
        }

        $update = ['status' => AnswerStatus::Invalidated->value];
        if ((bool) config('vnext.purge_invalidated', false)) {
            $update['value'] = null;
            $update['file_ids'] = null;
            $update['is_skipped'] = true;
        }

        $session->answers()
            ->whereIn('id', $downstream->pluck('id'))
            ->update($update);

        // Les blocs concernes redeviennent a parcourir. Invalider une reponse ne
        // suffit pas : le sequenceur saute tout bloc marque visite, donc sans
        // cette liberation la session se terminerait sans reposer les questions
        // que la correction vient de rendre caduques.
        $downstream
            ->pluck('question.bloc_key')
            ->filter()
            ->unique()
            ->each(fn (string $blocKey) => $session->removeVisitedBloc($blocKey));
    }
}
