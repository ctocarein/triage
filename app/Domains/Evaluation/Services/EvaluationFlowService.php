<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Decision\Models\DecisionNode;
use App\Domains\Decision\Models\DecisionPath;
use App\Domains\Decision\Models\DecisionInsight;
use App\Domains\Decision\Services\DecisionInsightService;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Domains\Evaluation\Domain\Policies\IdentityEvaluationPolicy;
use App\Domains\Report\Models\Report;
use App\Domains\Report\Services\ReportGenerationService;
use App\Services\Decisions\DecisionPathEngine;

class EvaluationFlowService
{
    public function __construct(
        protected AnswerService $answerService,
        protected DecisionInsightService $insightService,
        protected ReportGenerationService $reportService
    ) {
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function start(Identity $identity, array $validated): array
    {
        $type = $validated['type'];
        $blocKeys = $validated['bloc_keys'] ?? [];
        $decisionPath = null;

        if ($type !== 'complete' && ! IdentityEvaluationPolicy::hasCompletedFullEvaluation($identity)) {
            return [
                'status' => 403,
                'payload' => [
                    'message' => "Merci de completer une evaluation complete avant d'acceder aux evaluations thematiques.",
                ],
            ];
        }

        $inProgressFull = IdentityEvaluationPolicy::getInProgressFullEvaluation($identity);
        if ($inProgressFull && $type !== 'complete') {
            return [
                'status' => 403,
                'payload' => [
                    'message' => 'Vous avez une evaluation complete en cours. Veuillez la terminer avant de continuer.',
                    'session_id' => $inProgressFull->id,
                ],
            ];
        }

        if ($type === 'complete' && $inProgressFull) {
            $questionPayload = $this->resolveCurrentQuestionPayload($inProgressFull);
            $report = $inProgressFull->report;
            $insight = $this->insightService->generate($inProgressFull, $report);

            return [
                'status' => 200,
                'payload' => $this->buildPayload(
                    $inProgressFull,
                    $insight,
                    $questionPayload,
                    $report,
                    'Session deja en cours.'
                ),
            ];
        }

        if ($type === 'complete') {
            $decisionPath = DecisionPath::where('key', 'dp_full_assessment')->first();
            if (! $decisionPath) {
                return [
                    'status' => 500,
                    'payload' => [
                        'message' => "Le parcours d'evaluation complete n'existe pas (cle dp_full_assessment manquante en base).",
                    ],
                ];
            }
            if (! $decisionPath->is_active) {
                return [
                    'status' => 422,
                    'payload' => [
                        'message' => "Le parcours d'evaluation complete n'est pas actif.",
                    ],
                ];
            }
        } else {
            if (empty($blocKeys)) {
                return [
                    'status' => 422,
                    'payload' => [
                        'message' => 'Veuillez selectionner au moins un bloc thematique.',
                    ],
                ];
            }

            $paths = DecisionPath::whereIn('key', $blocKeys)->where('is_active', true)->get();
            if ($paths->isEmpty() || count($paths) !== count($blocKeys)) {
                return [
                    'status' => 422,
                    'payload' => [
                        'message' => 'Un ou plusieurs DecisionPath ne sont pas valides ou actifs.',
                    ],
                ];
            }

            $decisionPath = $paths->first();
            if (! $decisionPath) {
                return [
                    'status' => 500,
                    'payload' => [
                        'message' => 'Aucun parcours thematique actif trouve.',
                    ],
                ];
            }
        }

        $session = $identity->evaluationSessions()->create([
            'decision_path_id' => $decisionPath->id,
            'decision_path_key' => $decisionPath->key,
            'decision_path_version' => $decisionPath->version,
            'catalog_version' => $this->resolveActiveVersionId('catalog'),
            'risk_model_version' => $this->resolveActiveVersionId('risk_model'),
            'type' => $type,
            'status' => 'in_progress',
            'started_at' => now(),
            'target_blocs' => $blocKeys ?: null,
            'visited_blocs' => [],
            'context' => [
                'bloc_keys' => $blocKeys,
            ],
        ]);

        $engine = new DecisionPathEngine($decisionPath);
        $firstNode = $engine->getFirstNode();

        if (! $firstNode) {
            return [
                'status' => 500,
                'payload' => [
                    'message' => "Le parcours d'evaluation n'a pas de premiere etape definie.",
                    'session_id' => $session->id,
                    'decision_path_id' => $decisionPath->id,
                    'question' => null,
                ],
            ];
        }

        $questionId = $this->resolveQuestionId($firstNode);
        if ($questionId) {
            $session->update([
                'current_question_id' => $questionId,
                'current_node_key' => $firstNode->node_key,
            ]);
        }

        $questionPayload = $firstNode->getQuestionPayload();
        $insight = $this->insightService->generate($session);

        return [
            'status' => 201,
            'payload' => $this->buildPayload(
                $session,
                $insight,
                $questionPayload,
                null,
                'Session demarree.'
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
            $report = $session->report;
            $insight = $this->insightService->generate($session, $report);

            return [
                'status' => 200,
                'payload' => $this->buildPayload(
                    $session,
                    $insight,
                    null,
                    $report,
                    'Session deja terminee.'
                ),
            ];
        }

        if (! $session->decisionPath) {
            return [
                'status' => 500,
                'payload' => [
                    'message' => 'Aucun parcours decisionnel associe a cette session.',
                ],
            ];
        }

        $answerData['evaluation_session_id'] = $session->id;
        $answer = $this->answerService->saveAnswer($answerData);

        $session->update([
            'current_question_id' => $answer->question_id,
        ]);

        $engine = new DecisionPathEngine($session->decisionPath);
        $nextNode = $engine->getNextNode($session);

        if (! $nextNode) {
            $this->markCompleted($session);
            $report = $this->reportService->generate($session);
            $insight = $this->insightService->generate($session, $report);

            return [
                'status' => 200,
                'payload' => $this->buildPayload(
                    $session,
                    $insight,
                    null,
                    $report,
                    'Evaluation terminee.'
                ),
            ];
        }

        $questionPayload = $nextNode->getQuestionPayload();

        if (in_array($nextNode->node_type, ['end', 'recommendation'], true)) {
            $this->markCompleted($session);
            $report = $this->reportService->generate($session);
            $insight = $this->insightService->generate($session, $report);

            return [
                'status' => 200,
                'payload' => $this->buildPayload(
                    $session,
                    $insight,
                    $questionPayload,
                    $report,
                    $nextNode->outcome_message ?? 'Fin du parcours.'
                ),
            ];
        }

        $questionId = $this->resolveQuestionId($nextNode);
        if (! $questionId) {
            return [
                'status' => 500,
                'payload' => [
                    'message' => 'Probleme de configuration: node sans question_id.',
                ],
            ];
        }

        $session->update([
            'current_question_id' => $questionId,
            'current_node_key' => $nextNode->node_key,
        ]);

        $insight = $this->insightService->generate($session);

        return [
            'status' => 200,
            'payload' => $this->buildPayload(
                $session,
                $insight,
                $questionPayload,
                null,
                null
            ),
        ];
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function state(EvaluationSession $session): array
    {
        $questionPayload = $this->resolveCurrentQuestionPayload($session);
        $report = $session->report;
        $insight = $this->insightService->generate($session, $report);

        return [
            'status' => 200,
            'payload' => $this->buildPayload(
                $session,
                $insight,
                $questionPayload,
                $report,
                null
            ),
        ];
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function complete(EvaluationSession $session): array
    {
        if (! $session->isInProgress()) {
            $report = $session->report;
            $insight = $this->insightService->generate($session, $report);

            return [
                'status' => 200,
                'payload' => $this->buildPayload(
                    $session,
                    $insight,
                    null,
                    $report,
                    'Cette session est deja completee.'
                ),
            ];
        }

        $this->markCompleted($session);
        $report = $this->reportService->generate($session);
        $insight = $this->insightService->generate($session, $report);

        return [
            'status' => 201,
            'payload' => $this->buildPayload(
                $session,
                $insight,
                null,
                $report,
                'Session completee avec succes.'
            ),
        ];
    }

    public function buildPayload(
        EvaluationSession $session,
        ?DecisionInsight $insight,
        ?array $questionPayload,
        ?Report $report,
        ?string $message
    ): array {
        $payload = [
            'session' => $this->mapSession($session),
            'insight' => $insight ? $this->mapInsight($insight) : null,
            'question' => $questionPayload,
            'report' => $report ? $report->toArray() : null,
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return $payload;
    }

    protected function mapSession(EvaluationSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'type' => $session->type,
            'score' => $session->score,
            'risk_level' => $session->risk_level,
            'started_at' => $session->started_at,
            'completed_at' => $session->completed_at,
            'decision_path_id' => $session->decision_path_id,
            'decision_path_key' => $session->decision_path_key,
            'decision_path_version' => $session->decision_path_version,
            'catalog_version' => $session->catalog_version,
            'risk_model_version' => $session->risk_model_version,
            'current_question_id' => $session->current_question_id,
            'current_node_key' => $session->current_node_key,
            'target_blocs' => $session->target_blocs,
            'visited_blocs' => $session->visited_blocs,
        ];
    }

    protected function mapInsight(DecisionInsight $insight): array
    {
        return [
            'id' => $insight->id,
            'model_version' => $insight->model_version,
            'calibration_tag' => $insight->calibration_tag,
            'risk_score' => $insight->risk_score,
            'risk_level' => $insight->risk_level,
            'score_total' => $insight->score_total,
            'confidence_score' => $insight->confidence_score,
            'confidence_interval_low' => $insight->confidence_interval_low,
            'confidence_interval_high' => $insight->confidence_interval_high,
            'data_quality' => $insight->data_quality,
            'explanations' => $insight->explanations,
            'suggested_action' => $insight->suggested_action,
            'requires_human_validation' => $insight->requires_human_validation,
            'human_validation_reason' => $insight->human_validation_reason,
            'final_decision' => $insight->final_decision,
        ];
    }

    protected function resolveCurrentQuestionPayload(EvaluationSession $session): ?array
    {
        if (! $session->decisionPath || ! $session->current_question_id) {
            return null;
        }

        $node = $session->decisionPath->nodes()
            ->where('question_id', $session->current_question_id)
            ->first();

        if (! $node) {
            $node = $session->decisionPath->nodes()
                ->where('dynamic_question_template_id', $session->current_question_id)
                ->first();
        }

        return $node?->getQuestionPayload();
    }

    protected function resolveQuestionId(DecisionNode $node): ?int
    {
        if (! empty($node->question_id)) {
            return (int) $node->question_id;
        }

        return isset($node->dynamic_question_template_id)
            ? (int) $node->dynamic_question_template_id
            : null;
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

    protected function markCompleted(EvaluationSession $session): void
    {
        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
            'current_question_id' => null,
            'current_node_key' => null,
        ]);
    }
}
