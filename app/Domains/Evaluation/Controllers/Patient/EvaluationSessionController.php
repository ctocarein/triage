<?php

namespace App\Domains\Evaluation\Controllers\Patient;

use App\Domains\Evaluation\DTOs\Patient\EvaluationSessionStartRequest;
use App\Domains\Decision\Models\DecisionPath;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Domain\Policies\IdentityEvaluationPolicy;
use App\Domains\Evaluation\Services\QuestionFlowService;
use App\Domains\Report\Services\ReportGenerationService;
use App\Services\Decisions\DecisionPathEngine;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationSessionController extends Controller
{
    /**
     * Lister les sessions de l'utilisateur connecte.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->evaluationSessions()->withCount('answers')->with('report')->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $groupLabels = QuestionGroup::pluck('label', 'key');

        $sessions = $query->get()->map(function ($session) use ($groupLabels) {
            $thematics = $session->target_blocs
                ? collect($session->target_blocs)->map(fn($k) => $groupLabels[$k] ?? $k)->toArray()
                : null;

            return [
                'id' => $session->id,
                'type' => $session->type,
                'status' => $session->status,
                'started_at' => $session->started_at,
                'completed_at' => $session->completed_at,
                'target_blocs' => $session->target_blocs,
                'thematics' => $thematics,
                'title' => $session->type === 'complete'
                    ? 'Votre evaluation complete reussie'
                    : ($thematics ? implode(', ', $thematics) : ''),
                'answers_count' => $session->answers_count ?? $session->answers()->count(),
                'report' => $session->report->recommendation ?? null,
            ];
        });

        return response()->json($sessions);
    }

    /**
     * Demarrer une nouvelle session d'evaluation (complete ou thematique).
     */
    public function start(EvaluationSessionStartRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $identity = $request->user();
        $type = $validated['type'];
        $blocKeys = $validated['bloc_keys'] ?? [];
        $decisionPath = null;

        if ($type !== 'complete' && ! IdentityEvaluationPolicy::hasCompletedFullEvaluation($identity)) {
            return response()->json([
                'message' => "Merci de completer une evaluation complete avant d'acceder aux evaluations thematiques.",
            ], 403);
        }

        $inProgressFull = IdentityEvaluationPolicy::getInProgressFullEvaluation($identity);
        if ($inProgressFull && $type !== 'complete') {
            return response()->json([
                'message' => 'Vous avez une evaluation complete en cours. Veuillez la terminer avant de continuer.',
                'session_id' => $inProgressFull->id,
            ], 403);
        }

        if ($type === 'complete' && $inProgressFull) {
            $node = null;
            $decisionPath = $inProgressFull->decisionPath;
            if ($decisionPath && $inProgressFull->current_question_id) {
                $node = $decisionPath->nodes()
                    ->where('dynamic_question_template_id', $inProgressFull->current_question_id)
                    ->first();
            }

            $questionPayload = $node && method_exists($node, 'getQuestionPayload')
                ? $node->getQuestionPayload()
                : null;

            return response()->json([
                'message' => 'Session deja en cours.',
                'session_id' => $inProgressFull->id,
                'decision_path_id' => $inProgressFull->decision_path_id,
                'question' => $questionPayload,
            ], 200);
        }

        if ($type === 'complete') {
            $decisionPath = DecisionPath::where('key', 'dp_full_assessment')->first();
            if (! $decisionPath) {
                return response()->json([
                    'message' => "Le parcours d'evaluation complete n'existe pas (cle dp_full_assessment manquante en base).",
                ], 500);
            }
            if (! $decisionPath->is_active) {
                return response()->json([
                    'message' => "Le parcours d'evaluation complete n'est pas actif.",
                ], 422);
            }
        } else {
            if (empty($blocKeys)) {
                return response()->json([
                    'message' => 'Veuillez selectionner au moins un bloc thematique.',
                ], 422);
            }
            $paths = DecisionPath::whereIn('key', $blocKeys)->where('is_active', true)->get();
            if ($paths->isEmpty() || count($paths) !== count($blocKeys)) {
                return response()->json([
                    'message' => 'Un ou plusieurs DecisionPath ne sont pas valides ou actifs.',
                ], 422);
            }
            $decisionPath = $paths->first();
            if (! $decisionPath) {
                return response()->json([
                    'message' => 'Aucun parcours thematique actif trouve.',
                ], 500);
            }
        }

        $session = $identity->evaluationSessions()->create([
            'decision_path_id' => $decisionPath->id,
            'type' => $type,
            'status' => 'in_progress',
            'started_at' => now(),
            'context' => [
                'bloc_keys' => $blocKeys,
            ],
        ]);

        $engine = new DecisionPathEngine($decisionPath);
        $firstNode = $engine->getFirstNode();

        if (! $firstNode) {
            return response()->json([
                'message' => "Le parcours d'evaluation n'a pas de premiere etape definie.",
                'session_id' => $session->id,
                'decision_path_id' => $decisionPath->id,
                'question' => null,
            ], 500);
        }

        if (method_exists($firstNode, 'getQuestionPayload') && $firstNode->dynamic_question_template_id) {
            $session->update(['current_question_id' => $firstNode->dynamic_question_template_id]);
            $questionPayload = $firstNode->getQuestionPayload();
        } else {
            $questionPayload = null;
        }

        return response()->json([
            'message' => 'Session demarree.',
            'session_id' => $session->id,
            'decision_path_id' => $decisionPath->id,
            'question' => $questionPayload,
        ], 201);
    }

    /**
     * Aller a la prochaine question (DecisionPath Engine).
     */
    public function next(EvaluationSession $session): JsonResponse
    {
        if (! $session->isInProgress()) {
            return response()->json([
                'message' => 'Session deja terminee.',
                'isCompleted' => true,
            ]);
        }

        $decisionPath = $session->decisionPath;

        if (! $decisionPath) {
            return response()->json([
                'message' => 'Aucun parcours decisionnel associe a cette session.',
                'isCompleted' => true,
            ], 500);
        }

        if ($session->current_question_id) {
            $hasAnswer = $session->answers()
                ->where('dynamic_question_template_id', $session->current_question_id)
                ->exists();

            if (! $hasAnswer) {
                $node = $decisionPath->nodes()
                    ->where('dynamic_question_template_id', $session->current_question_id)
                    ->first();

                if ($node && method_exists($node, 'getQuestionPayload')) {
                    return response()->json([
                        'currentQuestion' => $node->getQuestionPayload(),
                        'isCompleted' => false,
                        'id' => $session->id,
                    ]);
                }
            }
        }

        $engine = new DecisionPathEngine($decisionPath);
        $nextNode = $engine->getNextNode($session);

        if (! $nextNode) {
            $session->update([
                'status' => 'completed',
                'completed_at' => now(),
                'current_question_id' => null,
            ]);

            return response()->json([
                'message' => 'Evaluation terminee.',
                'isCompleted' => true,
            ]);
        }

        if (in_array($nextNode->node_type, ['end', 'recommendation'])) {
            $session->update([
                'status' => 'completed',
                'completed_at' => now(),
                'current_question_id' => null,
            ]);

            $report = app(ReportGenerationService::class)->generate($session);

            return response()->json([
                'message' => $nextNode->outcome_message ?? 'Fin du parcours.',
                'isCompleted' => true,
                'report' => $report,
            ]);
        }

        $questionId = $nextNode->question_id ?? $nextNode->dynamic_question_template_id ?? null;

        if (! $questionId) {
            return response()->json([
                'message' => 'Probleme de configuration: node sans question_id.',
                'isCompleted' => false,
            ], 500);
        }

        $session->update(['current_question_id' => $questionId]);

        $questionPayload = method_exists($nextNode, 'getQuestionPayload')
            ? $nextNode->getQuestionPayload()
            : null;

        if (! $questionPayload) {
            return response()->json([
                'message' => 'Impossible de generer la question, donnees manquantes.',
                'isCompleted' => false,
            ], 500);
        }

        return response()->json([
            'currentQuestion' => $questionPayload,
            'isCompleted' => false,
            'id' => $session->id,
        ]);
    }

    /**
     * Terminer une session et generer le rapport associe.
     */
    public function complete(
        EvaluationSession $session,
        ReportGenerationService $reportService,
        QuestionFlowService $flow
    ): JsonResponse {
        $identity = auth()->user();

        if ($session->identity_id !== ($identity->id ?? null)) {
            return response()->json([
                'message' => 'Acces interdit a cette session.',
            ], 403);
        }

        if ($session->status === 'completed') {
            $report = $session->report;
            if (! $report) {
                try {
                    $report = $reportService->generate($session);
                } catch (\Throwable $e) {
                    return response()->json([
                        'message' => 'Session completee mais erreur lors de la generation du rapport : ' . $e->getMessage(),
                    ], 500);
                }
            }

            return response()->json([
                'message' => 'Cette session est deja completee.',
                'report_id' => $report->id ?? null,
                'recommendation' => $report->recommendation ?? null,
                'risk_level' => $report->risk_level ?? null,
                'score' => $report->score ?? null,
                'created_at' => $report->created_at ?? null,
            ]);
        }

        \DB::beginTransaction();
        try {
            $session->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $report = $reportService->generate($session);
            if (! $report) {
                throw new \Exception('Erreur lors de la generation du rapport.');
            }

            $scoreDetails = $flow->evaluateGlobalRisk($session);

            \DB::commit();

            return response()->json([
                'message' => 'Session completee avec succes.',
                'report_id' => $report->id,
                'recommendation' => $report->recommendation,
                'risk_level' => $report->risk_level,
                'score' => $report->score,
                'created_at' => $report->created_at,
                'score_details' => $scoreDetails,
            ], 201);
        } catch (\Throwable $e) {
            \DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la completion de la session : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Details d'une session.
     */
    public function show(EvaluationSession $session): JsonResponse
    {
        return response()->json(
            $session->load([
                'answers.question',
                'report',
            ])
        );
    }
}

