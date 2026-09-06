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
use App\Domains\Identity\Application\Services\BeneficiaryEnrollmentService;
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
        protected EvaluationSnapshotService $snapshotService,
        protected BeneficiaryEnrollmentService $enrollment
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
        $newSubject   = (bool) ($validated['new_subject'] ?? false);
        $referral     = null;
        $agentIdentity = null;
        $subjectPayload = null;

        // Le beneficiaire est designe par son identifiant s'il est deja connu, ou
        // cree a la volee avec new_subject. Son numero n'est pas demande ici : il
        // est collecte pendant l'evaluation, comme les champs de profil.
        if ($subjectId !== null || $newSubject) {
            if (! IdentityEvaluationPolicy::isEvaluator($identity)) {
                return [
                    'status'  => 403,
                    'payload' => [
                        'message'    => "Seul un agent de terrain ou un ambassadeur peut demarrer une evaluation pour un beneficiaire.",
                        'error_code' => 'EVALUATOR_REQUIRED',
                    ],
                ];
            }

            if ($newSubject) {
                $subject = $this->enrollment->createAnonymousBeneficiary($identity);
                $subjectPayload = ['identity_id' => (string) $subject->id, 'is_new' => true];
            } else {
                $subject = Identity::find($subjectId);
                if (! $subject) {
                    return [
                        'status'  => 404,
                        'payload' => ['message' => 'Beneficiaire introuvable.'],
                    ];
                }
                $subjectPayload = ['identity_id' => (string) $subject->id, 'is_new' => false];
            }

            if (! IdentityEvaluationPolicy::canConductAssistedEvaluation($identity, $subject)) {
                return [
                    'status'  => 403,
                    'payload' => [
                        'message'    => "Ce beneficiaire ne fait pas partie de votre organisation.",
                        'error_code' => 'SUBJECT_OUT_OF_SCOPE',
                    ],
                ];
            }

            $agentIdentity = $identity;
            $identity      = $subject;
        }

        // L'acteur reste celui qui est authentifie : en evaluation assistee, le sujet
        // n'a pas de token, c'est celui de l'evaluateur qui lie la session.
        $actor = $agentIdentity ?? $identity;

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

        if ($existingInProgress && $this->sessionTokenMismatch($actor, $existingInProgress)) {
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
                ->first(function (EvaluationSession $session) use ($normalized, $actor): bool {
                    if ($this->sessionTokenMismatch($actor, $session)) {
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
                    'payload' => $this->withSubject(
                        $this->buildPayload($existing, $insight, $questionPayload, $report, 'Session deja en cours.'),
                        $subjectPayload
                    ),
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
                'token_id'            => $actor->currentAccessToken()?->id,
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
            'payload' => $this->withSubject(
                $this->buildPayload($session, $insight, $questionPayload, null, 'Session demarree.'),
                $subjectPayload
            ),
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

        $question = DynamicQuestionTemplate::findOrFail($answerData['dynamic_question_template_id']);
        $this->questionSequencer->assertCanAnswer($session, $question);

        $answerData['evaluation_session_id'] = $session->id;
        $answer = $this->answerService->saveAnswer($answerData);
        $answer->loadMissing('question');

        if ($answer->question) {
            $this->syncContactFromAnswer($session, $answer);
        }

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

        // Les identifiants ne sont remis qu'une fois l'evaluation terminee :
        // c'est le moment ou l'evaluateur les communique au beneficiaire.
        if ($session->status === 'completed') {
            $payload = $this->withAccount($payload, $session);
        }

        return $this->withClosureContact(
            $this->withUrgentReferral($payload, $session),
            $session
        );
    }

    /**
     * Expose le beneficiaire dans la reponse de demarrage : en enrolement implicite,
     * c'est la seule occasion pour le client de recuperer l'identifiant de la personne
     * qu'il vient de creer.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $subject
     * @return array<string, mixed>
     */
    protected function withSubject(array $payload, ?array $subject): array
    {
        if ($subject !== null) {
            $payload['subject'] = $subject;
        }

        return $payload;
    }

    /**
     * Recupere les moyens de joindre la beneficiaire collectes par le
     * questionnaire.
     *
     * Deux cas distincts, et il ne faut pas les confondre : le numero du
     * parcours nominal devient un identifiant de connexion, celui laisse a la
     * cloture n'est qu'un contact. Une mineure ou une femme qui a refuse de
     * participer ne se voit pas ouvrir un dossier de sante qu'elle n'a pas
     * demande.
     */
    protected function syncContactFromAnswer(EvaluationSession $session, EvaluationAnswer $answer): void
    {
        $raw = is_array($answer->value) ? ($answer->value[0] ?? null) : $answer->value;
        $number = is_scalar($raw) ? trim((string) $raw) : '';

        if ($number === '') {
            return;
        }

        match ($answer->question?->key) {
            'phone' => $this->attachLoginPhone($session, $number),
            'consent_declined_whatsapp' => $this->attachClosureContact($session, $number),
            default => null,
        };
    }

    /**
     * Enregistre le contact laisse volontairement a la cloture, pour que
     * l'orientation puisse effectivement atteindre la personne.
     *
     * Porte par la session plutot que par la table des identifiants : celle-ci
     * impose l'unicite du couple (type, identifiant), ce qui est juste pour un
     * identifiant de connexion mais faux pour un contact -- un telephone
     * partage entre deux personnes d'un meme foyer ferait echouer l'insertion
     * en plein questionnaire. La session porte deja identity_id, le contact
     * reste donc rattache a la personne.
     */
    protected function attachClosureContact(EvaluationSession $session, string $number): void
    {
        $context = is_array($session->context) ? $session->context : [];

        $context['closure_contact'] = [
            'channel'     => 'whatsapp',
            'value'       => $number,
            'collected_at' => now()->toISOString(),
        ];

        $session->context = $context;
        $session->save();
    }

    /**
     * Rattache le numero collecte dans le questionnaire aux identifiants du
     * beneficiaire, et reconcilie la session si ce numero designe quelqu'un que
     * l'on connait deja.
     */
    protected function attachLoginPhone(EvaluationSession $session, string $phone): void
    {
        $subject = $session->identity;
        if (! $subject) {
            return;
        }

        $result = $this->enrollment->attachPhone($subject, $phone);
        $context = is_array($session->context) ? $session->context : [];

        if ($result['reconciled']) {
            // Le numero designe une personne deja connue : la session bascule sur
            // elle, et l'identite creee au demarrage est neutralisee. Possible
            // seulement parce que la question est posee tot, avant que le gros du
            // questionnaire n'ait ete rattache au doublon.
            $duplicate = $subject;
            $session->forceFill(['identity_id' => $result['identity']->id])->save();
            $session->setRelation('identity', $result['identity']);
            $this->enrollment->retireDuplicate($duplicate);

            $context['reconciled_from'] = (string) $duplicate->id;
        }

        $context['subject_phone'] = $phone;
        if ($result['pin'] !== null) {
            $context['pin_issued'] = true;
        }

        $session->context = $context;
        $session->save();
    }

    /**
     * Identifiants a communiquer au beneficiaire en fin de visite pour qu'il
     * installe l'application. Le PIN est la valeur de configuration : il n'est
     * pas relu depuis la base, ou il n'existe que sous forme de hash.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function withAccount(array $payload, EvaluationSession $session): array
    {
        $context = is_array($session->context) ? $session->context : [];

        if (($context['pin_issued'] ?? false) !== true || empty($context['subject_phone'])) {
            return $payload;
        }

        $payload['account'] = [
            'phone'                => $context['subject_phone'],
            'pin'                  => (string) config('identity.default_pin'),
            'must_change_password' => true,
        ];

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

    /**
     * Expose l'orientation urgente dans la reponse.
     *
     * `alert` n'apparait que sur la reponse a la question qui a leve l'alerte :
     * c'est la que le client doit l'annoncer, sans attendre la fin du parcours.
     * `urgent_referral` reste presente ensuite, pour que le bilan et la reprise
     * de session portent l'information.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function withUrgentReferral(array $payload, EvaluationSession $session): array
    {
        $context = is_array($session->context) ? $session->context : [];
        $referral = $context['urgent_referral'] ?? null;

        if (! is_array($referral)) {
            return $payload;
        }

        $reason = (string) ($referral['reason'] ?? 'urgent_referral');
        $message = $this->resolveUrgentMessage($reason);

        $payload['urgent_referral'] = [
            'reason'    => $reason,
            'message'   => $message,
            'raised_at' => $referral['raised_at'] ?? null,
        ];

        if (($context['urgent_referral_pending'] ?? false) === true) {
            $payload['alert'] = [
                'type'    => 'urgent_referral',
                'reason'  => $reason,
                'message' => $message,
            ];

            // Consommee : les reponses suivantes ne doivent pas la reafficher.
            unset($context['urgent_referral_pending']);
            $session->context = $context;
            $session->save();
        }

        return $payload;
    }

    /**
     * Expose le contact laisse a la cloture : c'est la seule facon pour
     * l'evaluateur de savoir qu'il a bien ete enregistre, et pour l'equipe de
     * retrouver les personnes a rappeler.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function withClosureContact(array $payload, EvaluationSession $session): array
    {
        $context = is_array($session->context) ? $session->context : [];
        $contact = $context['closure_contact'] ?? null;

        if (is_array($contact)) {
            $payload['closure_contact'] = $contact;
        }

        return $payload;
    }

    protected function resolveUrgentMessage(string $reason): string
    {
        return match ($reason) {
            'breast_nipple_bleeding', 'breast_bleeding' => 'Un ecoulement sanglant du mamelon necessite une evaluation medicale rapide.',
            'breast_skin_orange' => "Une modification cutanee de type peau d'orange necessite une evaluation medicale rapide.",
            'breast_mass' => "Une masse mammaire d'evolution rapide necessite une evaluation medicale rapide.",
            'vaginal_bleeding' => 'Des saignements vaginaux importants et inattendus necessitent une evaluation medicale rapide.',
            'pelvic_mass' => 'Une masse ou un gonflement pelvien necessite une evaluation medicale rapide.',
            default => "Des signes d'alerte ont ete detectes. Une evaluation medicale rapide est recommandee.",
        };
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
