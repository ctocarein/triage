<?php

namespace App\Http\Controllers\Evaluation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Evaluation\QuestionGroup;
use App\Models\Evaluation\EvaluationSession;
use App\Services\Evaluation\QuestionFlowService;
use App\Models\Evaluation\DynamicQuestionTemplate;
use App\Services\Evaluation\ReportGenerationService;

class EvaluationSessionController extends Controller
{
    /**
     * 🧾 Lister les sessions de l'utilisateur connecté
     */
    public function index(Request $request)
    {
        $query = $request->user()->evaluationSessions()->withCount('answers')->with('report')->latest();

        if ($status = $request->input('status')) $query->where('status', $status);
        if ($type = $request->input('type')) $query->where('type', $type);

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
                                ? "Évaluation complète"
                                : "Éval. thématique : " . ($thematics ? implode(', ', $thematics) : ''),
                'answers_count' => $session->answers_count ?? $session->answers()->count(),
                'title' => $session->type === 'complete'
                    ? "Évaluation complète"
                    : "Éval. thématique : " . ($thematics ? implode(', ', $thematics) : ''),
                'report_id' => $session->report->id ?? null,
            ];
        });

        return response()->json($sessions);
    }


    /**
     * 🆕 Démarrer une nouvelle session d’évaluation
     */
  public function start(Request $request, QuestionFlowService $flow)
    {
        $account = $request->user();
        $type = $request->input('type', 'complete');

        // Refuse une thématique sans complète terminée
        if ($type !== 'complete' && !$account->hasCompletedFullEvaluation()) {
            return response()->json([
                'message' => 'Merci de compléter une évaluation complète avant d\'accéder aux évaluations thématiques.'
            ], 403);
        }

        // Refuse toute création si une complète est en cours
        $inProgressFull = $account->getInProgressFullEvaluation();
        if ($inProgressFull && $type !== 'complete') {
            return response()->json([
                'message' => 'Vous avez une évaluation complète en cours. Veuillez la terminer avant de continuer.',
                'session_id' => $inProgressFull->id
            ], 403);
        }
        if ($type === 'complete' && $inProgressFull) {
            return response()->json([
                'message' => 'Vous avez déjà une évaluation complète en cours. Veuillez la terminer.',
                'session_id' => $inProgressFull->id
            ], 403);
        }

        // Crée la session avec le type explicitement
        $session = $account->evaluationSessions()->create([
            'status' => 'in_progress',
            'type' => $type,
            'started_at' => now(),
            'context' => $request->input('context', []),
            'target_blocs' => $request->input('bloc_keys', null),
        ]);

        $firstQuestion = $flow->goToNextQuestion($session);
        if ($firstQuestion) {
            $session->update(['current_question_id' => $firstQuestion->id]);
        }

        return response()->json([
            'message' => 'Session démarrée.',
            'session' => $session,
            'question' => $firstQuestion,
        ], 201);
    }


    /**
     * ▶️ Aller à la prochaine question
     */
    public function next(EvaluationSession $session, QuestionFlowService $flow)
    {
        if (!$session->isInProgress()) {
            return response()->json([
                'message' => 'Session déjà terminée.',
                'isCompleted' => true,
            ]);
        }

        $nextQuestion = $flow->goToNextQuestion($session);

        if (!$nextQuestion) {
            $session->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Évaluation terminée.',
                'isCompleted' => true,
            ]);
        }

        $session->update(['current_question_id' => $nextQuestion->id]);

        // Infos sur la progression dans le bloc
        $context = $flow->getSessionContext($session);
        $blocKey = $nextQuestion->bloc_key;
        $blocQuestions = DynamicQuestionTemplate::where('bloc_key', $blocKey)->get();
        $answeredCount = $blocQuestions->whereIn('id', $context['answeredIds'])->count();

        return response()->json([
            'currentQuestion' => $nextQuestion,
            'isCompleted' => false,
            'id' => $session->id,
            'bloc_key' => $blocKey,
            'total' => $blocQuestions->count(),
            'answered' => $answeredCount,
            'visited_blocs' => $session->visited_blocs ?? [],
        ]);
    }

    /**
     * ✅ Terminer manuellement une session et générer son rapport
     */
  /**
 * Terminer une session et générer le rapport associé.
 */
    public function complete(
        EvaluationSession $session,
        ReportGenerationService $reportService,
        QuestionFlowService $flow
    ) 
    {
        $account = auth()->user();

        // Vérifier que la session appartient bien à l'utilisateur connecté
        if ($session->account_id !== ($account->id ?? null)) {
            return response()->json([
                'message' => "Accès interdit à cette session."
            ], 403);
        }

        // Idempotence : retourne le rapport s'il existe déjà
        if ($session->status === 'completed') {
            $report = $session->report; // doit exister une relation report() sur EvaluationSession
            return response()->json([
                'message'        => 'Cette session est déjà complétée.',
                'report_id'      => $report->id ?? null,
                'recommendation' => $report->recommendation ?? null,
                'risk_level'     => $report->risk_level ?? null,
                'score'          => $report->score ?? null,
                'created_at'     => $report->created_at ?? null,
            ]);
        }

        // Transaction pour cohérence
        \DB::beginTransaction();
        try {
            $session->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            // Générer le rapport
            $report = $reportService->generate($session);

            // Calculer les détails du score si nécessaire
            $scoreDetails = $flow->evaluateGlobalRisk($session);

            \DB::commit();

            return response()->json([
                'message'        => 'Session complétée avec succès.',
                'report_id'      => $report->id,
                'recommendation' => $report->recommendation,
                'risk_level'     => $report->risk_level,
                'score'          => $report->score,
                'created_at'     => $report->created_at,
                'score_details'  => $scoreDetails,
            ], 201);
        } catch (\Throwable $e) {
            \DB::rollBack();
            return response()->json([
                'message' => "Erreur lors de la complétion de la session : " . $e->getMessage()
            ], 500);
        }
    }


    /**
     * 🔍 Détails d’une session (admin ou historique)
     */
    public function show(EvaluationSession $session)
    {
        return response()->json(
            $session->load([
                'answers.question',
                'report',
            ])
        );
    }
}
