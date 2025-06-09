<?php

namespace App\Http\Controllers\Evaluation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Evaluation\DynamicQuestionTemplate;
use App\Models\Evaluation\EvaluationSession;
use App\Services\Evaluation\QuestionFlowService;
use App\Services\ReportGenerationService;

class EvaluationSessionController extends Controller
{
    /**
     * 🧾 Lister les sessions de l'utilisateur connecté
     */
    public function index(Request $request)
    {
        $sessions = $request->user()
            ->evaluationSessions()
            ->withCount('answers')
            ->with('report')
            ->latest()
            ->get();

        return response()->json($sessions);
    }

    /**
     * 🆕 Démarrer une nouvelle session d’évaluation
     */
    public function start(Request $request, QuestionFlowService $flow)
    {
        $account = $request->user();

        // Vérifie s’il y a déjà une session active
        $existing = $account->activeEvaluationSession();
        if ($existing) {
            return response()->json([
                'message' => 'Une session est déjà en cours.',
                'session' => $existing,
                'question' => $existing->currentQuestion,
            ]);
        }

        // ➕ Crée la session
        $session = $account->evaluationSessions()->create([
            'status' => 'in_progress',
            'started_at' => now(),
            'context' => $request->input('context', []),
            'target_blocs' => $request->input('bloc_keys', null), // tableau facultatif
        ]);

        // 🧭 Première question selon la logique des blocs
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
    public function complete(EvaluationSession $session, ReportGenerationService $reportService, QuestionFlowService $flow)
    {
        if ($session->status === 'completed') {
            return response()->json([
                'message' => 'Cette session est déjà complétée.',
                'report' => $session->report,
            ]);
        }

        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $report = $reportService->generate($session);
        $scoreDetails = $flow->evaluateGlobalRisk($session);

        return response()->json([
            'message' => 'Session complétée avec succès.',
            'score' => $scoreDetails['total_score'],
            'risk_level' => $scoreDetails['level'],
            'blocs' => $scoreDetails['blocs'],
            'report_id' => $report->id,
        ]);
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
