<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Decision\Models\DecisionInsight;
use App\Domains\Decision\Services\DecisionInsightService;
use App\Domains\Evaluation\Models\EvaluationAnswer;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Models\ThematicBloc;
use App\Domains\Evaluation\Domain\Policies\IdentityEvaluationPolicy;
use App\Domains\Report\Models\Report;
use App\Domains\Report\Services\ReportGenerationService;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral;
use App\Domains\Ambassador\Domain\Events\ReferralUsed;
use App\Domains\Evaluation\Services\ProfileUpdaterService;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Enums\AnswerStatus;
use App\Domains\Evaluation\Services\Navigation\QuestionSequencer;
use App\Domains\Evaluation\Services\Scoring\EvaluationSnapshotService;

class EvaluationFlowService
{
    public function __construct(
        protected AnswerService $answerService,
        protected DecisionInsightService $insightService,
        protected ReportGenerationService $reportService,
        protected ProfileUpdaterService $profileUpdater,
        protected QuestionSequencer $questionSequencer,
        protected EvaluationSnapshotService $snapshotService
    ) {
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function start(Identity $identity, array $validated): array
    {
        $type         = $validated['type'];
        $blocKeys     = $validated['bloc_keys'] ?? [];
        $referralCode = $validated['referral_code'] ?? null;
        $clientIp     = $validated['client_ip'] ?? null;
        $subjectId    = $validated['subject_id'] ?? null;
        $referral     = null;
        $agentIdentity = null;

        // Si subject_id fourni, l'acteur doit être FieldAgent et on crée la session pour le bénéficiaire
        if ($subjectId !== null) {
            if (! $identity->hasMembershipRole('FieldAgent')) {
                return [
                    'status'  => 403,
                    'payload' => [
                        'message'    => 'Seul un agent de terrain peut démarrer une évaluation pour un bénéficiaire.',
                        'error_code' => 'AGENT_REQUIRED',
                    ],
                ];
            }
            $subject = Identity::find($subjectId);
            if (! $subject) {
                return [
                    'status'  => 404,
                    'payload' => ['message' => 'Bénéficiaire introuvable.'],
                ];
            }
            $agentIdentity = $identity;
            $identity      = $subject;
        }

        if ($referralCode !== null) {
            $referral = Referral::query()->where('code', $referralCode)->first();
            if (! $referral || $referral->revoked_at !== null || ($referral->expires_at !== null && $referral->expires_at->isPast())) {
                return [
                    'status'  => 410,
                    'payload' => [
                        'message'      => 'Lien de parrainage invalide ou expire.',
                        'error_code'   => 'REFERRAL_EXPIRED',
                        'redirect_url' => config('ambassador.referrals.expired_url'),
                    ],
                ];
            }
            if ($referral->ambassador?->user_id === $identity->id) {
                return [
                    'status'  => 403,
                    'payload' => [
                        'message'    => "Vous ne pouvez pas utiliser votre propre lien de parrainage.",
                        'error_code' => 'REFERRAL_SELF',
                    ],
                ];
            }
        }

        $existingInProgress = $identity->evaluationSessions()
            ->active()
            ->latest('started_at')
            ->first();

        if ($existingInProgress && $this->sessionTokenMismatch($identity, $existingInProgress)) {
            $this->expireSession($existingInProgress);
            $existingInProgress = null;
        }

        if ($existingInProgress) {
            return [
                'status'  => 403,
                'payload' => [
                    'message'    => 'Vous avez une evaluation en cours. Veuillez la terminer.',
                    'error_code' => 'SESSION_IN_PROGRESS',
                    'session_id' => $existingInProgress->public_id,
                ],
            ];
        }

        if ($type === 'complete') {
            $completedSession = $identity->evaluationSessions()
                ->completed()
                ->fullEvaluation()
                ->latest('completed_at')
                ->first();

            if ($completedSession) {
                return [
                    'status'  => 403,
                    'payload' => [
                        'message'      => 'Vous avez deja complete une evaluation.',
                        'error_code'   => 'ALREADY_COMPLETED',
                        'redirect_url' => config('ambassador.referrals.used_url'),
                        'session_id'   => $completedSession->public_id,
                    ],
                ];
            }
        }

        if ($referral && $clientIp) {
            $otherIpSession = EvaluationSession::query()
                ->where('referral_id', $referral->id)
                ->where('identity_id', '!=', $identity->id)
                ->where('context->client_ip', $clientIp)
                ->first();

            if ($otherIpSession) {
                return [
                    'status'  => 403,
                    'payload' => [
                        'message'      => 'Ce lien a deja ete utilise sur cet appareil.',
                        'error_code'   => 'REFERRAL_DEVICE_USED',
                        'redirect_url' => config('ambassador.referrals.used_url'),
                    ],
                ];
            }
        }

        if ($referral) {
            $existingReferralSession = $identity->evaluationSessions()
                ->where('referral_id', $referral->id)
                ->latest('started_at')
                ->first();

            if ($existingReferralSession && $existingReferralSession->isInProgress()) {
                return [
                    'status'  => 403,
                    'payload' => [
                        'message'    => 'Cette session est deja en cours. Veuillez la terminer.',
                        'error_code' => 'SESSION_IN_PROGRESS',
                        'session_id' => $existingReferralSession->public_id,
                    ],
                ];
            }

            if ($existingReferralSession && $existingReferralSession->status === 'completed') {
                return [
                    'status'  => 403,
                    'payload' => [
                        'message'      => 'Vous avez deja termine une evaluation avec ce lien.',
                        'error_code'   => 'REFERRAL_USED',
                        'redirect_url' => config('ambassador.referrals.used_url'),
                    ],
                ];
            }
        }

        if ($type !== 'complete' && ! IdentityEvaluationPolicy::hasCompletedFullEvaluation($identity)) {
            return [
                'status'  => 403,
                'payload' => [
                    'message' => "Merci de completer une evaluation complete avant d'acceder aux evaluations thematiques.",
                ],
            ];
        }

        // Résoudre les group IDs pour le type thematic
        $selectedGroupIds = [];
        if ($type === 'thematic') {
            if (empty($blocKeys)) {
                return [
                    'status'  => 422,
                    'payload' => ['message' => 'Veuillez selectionner au moins un bloc thematique.'],
                ];
            }

            $blocs = ThematicBloc::whereIn('key', $blocKeys)->where('is_active', true)->get();
            if ($blocs->isEmpty() || $blocs->count() !== count($blocKeys)) {
                return [
                    'status'  => 422,
                    'payload' => ['message' => 'Un ou plusieurs blocs thematiques ne sont pas valides ou actifs.'],
                ];
            }

            $selectedGroupIds = $blocs->pluck('question_group_id')->filter()->unique()->values()->all();

            // Réutiliser une session thematic identique en cours
            $normalized = $this->normalizeBlocKeys($blocKeys);
            $existing   = $identity->evaluationSessions()
                ->active()
                ->where('type', 'thematic')
                ->latest('started_at')
                ->get()
                ->first(function (EvaluationSession $session) use ($normalized, $identity): bool {
                    if ($this->sessionTokenMismatch($identity, $session)) {
                        $this->expireSession($session);
                        return false;
                    }
                    return $this->normalizeBlocKeys($session->target_blocs ?? []) === $normalized;
                });

            if ($existing) {
                $questionPayload = $this->resolveCurrentQuestionPayload($existing);
                $report  = $existing->report;
                $insight = $this->insightService->generate($existing, $report);

                return [
                    'status'  => 200,
                    'payload' => $this->buildPayload($existing, $insight, $questionPayload, $report, 'Session deja en cours.'),
                ];
            }
        }

        $session = $identity->evaluationSessions()->create([
            'decision_path_id'      => null,
            'decision_path_key'     => null,
            'decision_path_version' => null,
            'catalog_version'       => $this->resolveActiveVersionId('catalog'),
            'risk_model_version'    => $this->resolveActiveVersionId('risk_model'),
            'type'                  => $type,
            'status'                => 'in_progress',
            'started_at'            => now(),
            'expires_at'            => $this->resolveSessionExpiry(),
            'target_blocs'          => $blocKeys ?: null,
            'visited_blocs'         => [],
            'path_trace'            => [],
            'context'               => array_filter([
                'bloc_keys'           => $blocKeys ?: null,
                'selected_group_ids'  => $selectedGroupIds ?: null,
                'token_id'            => $identity->currentAccessToken()?->id,
                'client_ip'           => $clientIp,
                'agent_id'            => $agentIdentity?->id,
            ], fn ($v) => $v !== null),
            'referral_id'           => $referral?->id,
        ]);

        $firstQuestion = $this->questionSequencer->goToNextQuestion($session, $selectedGroupIds);

        if (! $firstQuestion) {
            return [
                'status'  => 500,
                'payload' => [
                    'message'    => "Aucune question disponible pour demarrer l'evaluation.",
                    'session_id' => $session->public_id,
                    'question'   => null,
                ],
            ];
        }

        $session->update(['current_question_id' => $firstQuestion->id]);

        $questionPayload = $this->buildQuestionPayloadFromTemplate($firstQuestion);
        $insight         = $this->insightService->generate($session);

        return [
            'status'  => 201,
            'payload' => $this->buildPayload($session, $insight, $questionPayload, null, 'Session demarree.'),
        ];
    }

    /**
     * @param array<string, mixed> $answerData
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function advance(EvaluationSession $session, array $answerData): array
    {
        if (! $session->isInProgress()) {
            $report  = $session->report;
            $insight = $this->insightService->generate($session, $report);

            return [
                'status'  => 200,
                'payload' => $this->buildPayload($session, $insight, null, $report, 'Session deja terminee.'),
            ];
        }

        $answerData['evaluation_session_id'] = $session->id;
        $answer = $this->answerService->saveAnswer($answerData);
        $answer->loadMissing('question');

        if (
            $answer->question &&
            in_array($answer->question->answer_target, ['personal_profile', 'medical_profile'], true)
        ) {
            $this->profileUpdater->syncFromAnswers($session);
        }

        $session->update(['current_question_id' => $answer->dynamic_question_template_id]);

        $routing = null;
        if ($answer->question) {
            $routing = $this->questionSequencer->applyRoutingRules($session, $answer->question, $answer->value);
        }

        $this->recordPathTrace($session, $answer, $routing);

        // Refresh : le routing peut avoir changé le statut (end_session → cancelled)
        $session->refresh();

        if (! $session->isInProgress()) {
            $report = null;
            if ($session->status === 'completed') {
                $report = $this->reportService->generate($session);
            }
            $insight = $this->insightService->generate($session, $report);
            $message = $session->status === 'completed' ? 'Evaluation terminee.' : null;

            return [
                'status'  => 200,
                'payload' => $this->buildPayload($session, $insight, null, $report, $message),
            ];
        }

        $groupIds      = $session->context['selected_group_ids'] ?? [];
        $nextQuestion  = $this->questionSequencer->goToNextQuestion($session, $groupIds);

        // goToNextQuestion appelle markCompleted() en interne si plus de questions
        $session->refresh();

        if (! $nextQuestion || ! $session->isInProgress()) {
            $report  = $this->reportService->generate($session);
            $insight = $this->insightService->generate($session, $report);
            $message = $session->status === 'completed' ? 'Evaluation terminee.' : null;

            return [
                'status'  => 200,
                'payload' => $this->buildPayload($session, $insight, null, $report, $message),
            ];
        }

        $session->update(['current_question_id' => $nextQuestion->id]);

        $questionPayload = $this->buildQuestionPayloadFromTemplate($nextQuestion);
        $insight         = $this->insightService->generate($session);

        return [
            'status'  => 200,
            'payload' => $this->buildPayload($session, $insight, $questionPayload, null, null),
        ];
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function state(EvaluationSession $session): array
    {
        $questionPayload = $this->resolveCurrentQuestionPayload($session);
        $report  = $session->report;
        $insight = $this->insightService->generate($session, $report);

        return [
            'status'  => 200,
            'payload' => $this->buildPayload($session, $insight, $questionPayload, $report, null),
        ];
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function complete(EvaluationSession $session): array
    {
        if (! $session->isInProgress()) {
            $report  = $session->report;
            $insight = $this->insightService->generate($session, $report);

            return [
                'status'  => 200,
                'payload' => $this->buildPayload($session, $insight, null, $report, 'Cette session est deja completee.'),
            ];
        }

        $this->markCompleted($session);
        $report  = $this->reportService->generate($session);
        $insight = $this->insightService->generate($session, $report);

        return [
            'status'  => 201,
            'payload' => $this->buildPayload($session, $insight, null, $report, 'Session completee avec succes.'),
        ];
    }

    public function buildPayload(
        EvaluationSession $session,
        ?DecisionInsight $insight,
        ?array $questionPayload,
        ?Report $report,
        ?string $message
    ): array {
        $this->snapshotService->maybeRecord($session);

        $payload = [
            'session'  => $this->mapSession($session),
            'insight'  => $insight ? $this->mapInsight($insight) : null,
            'question' => $questionPayload,
            'report'   => $report ? $report->toArray() : null,
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        } elseif ($session->status === 'cancelled') {
            $payload['message'] = $this->resolveTerminationMessage($session);
        }

        return $payload;
    }

    protected function recordPathTrace(EvaluationSession $session, EvaluationAnswer $answer, ?array $routing): void
    {
        $trace   = $session->path_trace ?? [];
        $trace[] = [
            'question_id'  => $answer->dynamic_question_template_id,
            'question_key' => $answer->question?->key,
            'status'       => $answer->status instanceof AnswerStatus ? $answer->status->value : $answer->status,
            'order'        => $answer->order,
            'routing'      => $routing,
            'at'           => now()->toISOString(),
        ];

        $session->path_trace = $trace;
        $session->save();
    }

    protected function resolveCurrentQuestionPayload(EvaluationSession $session): ?array
    {
        if (! $session->current_question_id) {
            return null;
        }

        $question = DynamicQuestionTemplate::find($session->current_question_id);

        return $question ? $this->buildQuestionPayloadFromTemplate($question) : null;
    }

    protected function mapSession(EvaluationSession $session): array
    {
        $context = is_array($session->context) ? $session->context : [];

        return [
            'id'                    => $session->public_id,
            'public_id'             => $session->public_id,
            'status'                => $session->status,
            'type'                  => $session->type,
            'score'                 => $session->score,
            'risk_level'            => $session->risk_level,
            'started_at'            => $session->started_at,
            'completed_at'          => $session->completed_at,
            'expires_at'            => $session->expires_at,
            'decision_path_id'      => $session->decision_path_id,
            'decision_path_key'     => $session->decision_path_key,
            'decision_path_version' => $session->decision_path_version,
            'catalog_version'       => $session->catalog_version,
            'risk_model_version'    => $session->risk_model_version,
            'versions'              => [
                'protocol'              => $this->snapshotService->resolveProtocolVersion($session),
                'catalog_version_id'    => $session->catalog_version,
                'risk_model_version_id' => $session->risk_model_version,
            ],
            'current_question_id'   => $session->current_question_id,
            'target_blocs'          => $session->target_blocs,
            'visited_blocs'         => $session->visited_blocs,
            'termination_type'      => $context['termination_type'] ?? null,
            'termination_reason'    => $context['termination_reason'] ?? null,
        ];
    }

    protected function mapInsight(DecisionInsight $insight): array
    {
        return [
            'id'                       => $insight->id,
            'model_version'            => $insight->model_version,
            'calibration_tag'          => $insight->calibration_tag,
            'risk_score'               => $insight->risk_score,
            'risk_level'               => $insight->risk_level,
            'score_total'              => $insight->score_total,
            'confidence_score'         => $insight->confidence_score,
            'confidence_interval_low'  => $insight->confidence_interval_low,
            'confidence_interval_high' => $insight->confidence_interval_high,
            'data_quality'             => $insight->data_quality,
            'explanations'             => $insight->explanations,
            'suggested_action'         => $insight->suggested_action,
            'requires_human_validation' => $insight->requires_human_validation,
            'human_validation_reason'  => $insight->human_validation_reason,
            'final_decision'           => $insight->final_decision,
            'vnext_shadow'             => $insight->vnext_shadow,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildQuestionPayloadFromTemplate(DynamicQuestionTemplate $question): array
    {
        return [
            'id'            => $question->id,
            'key'           => $question->key,
            'text'          => $question->text,
            'type'          => $question->type,
            'bloc_key'      => $question->bloc_key,
            'answer_target' => $question->answer_target,
            'order'         => $question->order,
            'risk_weight'   => $question->risk_weight,
            'min'           => $question->min,
            'max'           => $question->max,
            'media_type'    => $question->media_type,
            'media_url'     => $question->media_url,
            'options'       => $question->options,
            'visible_if'    => $question->visible_if,
            'question_id'   => $question->id,
            'node_id'       => null,
            'required'      => $question->is_required,
            'placeholder'   => $question->placeholder,
            'meta'          => $question->meta,
        ];
    }

    protected function resolveActiveVersionId(string $name): ?int
    {
        $version = ModelVersion::query()
            ->where('name', $name)
            ->where('is_active', true)
            ->orderByDesc('released_at')
            ->first();

        return $version?->id;
    }

    protected function resolveSessionExpiry(): ?\Illuminate\Support\Carbon
    {
        $hours = (int) config('evaluation.session_ttl_hours', 24);
        if ($hours <= 0) {
            return null;
        }

        return now()->addHours($hours);
    }

    /**
     * @param array<int, string> $blocKeys
     */
    protected function normalizeBlocKeys(array $blocKeys): array
    {
        $normalized = array_values(array_filter($blocKeys, fn ($value) => (string) $value !== ''));
        sort($normalized);

        return $normalized;
    }

    protected function sessionTokenMismatch(Identity $identity, EvaluationSession $session): bool
    {
        $tokenId       = $identity->currentAccessToken()?->id;
        $context       = $session->context ?? [];
        $sessionTokenId = is_array($context) ? ($context['token_id'] ?? null) : null;

        return $tokenId !== null
            && $sessionTokenId !== null
            && (int) $tokenId !== (int) $sessionTokenId;
    }

    protected function expireSession(EvaluationSession $session): void
    {
        if ($session->status !== 'expired') {
            $updates = ['status' => 'expired'];
            if ($session->expires_at === null) {
                $updates['expires_at'] = now();
            }
            $session->update($updates);
        }
    }

    protected function markCompleted(EvaluationSession $session): void
    {
        $session->markCompleted();

        if ($session->referral_id !== null) {
            Referral::query()
                ->where('id', $session->referral_id)
                ->update(['last_used_at' => now()]);

            event(new ReferralUsed($session->referral_id, 'completed'));
        }
    }

    protected function resolveTerminationMessage(EvaluationSession $session): string
    {
        $context = is_array($session->context) ? $session->context : [];
        $type = $context['termination_type'] ?? null;
        $reason = $context['termination_reason'] ?? null;

        return match ($type) {
            'consent_declined' => 'Vous avez choisi de ne pas poursuivre l\'évaluation.',
            'urgent_referral' => $this->resolveUrgentTerminationMessage($reason),
            default => 'L\'évaluation a été interrompue avant son terme.',
        };
    }

    protected function resolveUrgentTerminationMessage(?string $reason): string
    {
        return match ($reason) {
            'urgent_breast_nipple_bleeding' => 'Un écoulement sanglant du mamelon nécessite une évaluation médicale rapide.',
            'urgent_breast_skin_orange' => 'Une modification cutanée de type peau d\'orange nécessite une évaluation médicale rapide.',
            'urgent_pelvic_mass' => 'Une masse ou un gonflement pelvien nécessite une évaluation médicale rapide.',
            default => 'Des signes d\'alerte ont été détectés. Une évaluation médicale rapide est recommandée.',
        };
    }
}
