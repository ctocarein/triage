<?php

namespace App\Domains\Evaluation\Controllers\Patient;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Services\QuestionFlowService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionFlowsController extends Controller
{
    protected QuestionFlowService $flow;

    public function __construct(QuestionFlowService $flow)
    {
        $this->flow = $flow;
    }

    /**
     * Point d'entree pour demarrer avec des questions ciblees.
     */
    public function entryPoints(Request $request): JsonResponse
    {
        $target = $request->query('answer_target');

        return response()->json(
            $this->flow->getEntryPoints($target)
        );
    }

    /**
     * Recuperer les prochaines questions visibles du bloc courant.
     */
    public function flow(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);
        if (! $session) {
            return response()->json(['error' => 'Session active non trouvee.'], 400);
        }

        if (! $session->currentQuestion) {
            return response()->json(['error' => 'Aucune question en cours.'], 400);
        }

        $visibleQuestions = $this->flow->getNextQuestions($session);

        if (empty($visibleQuestions)) {
            if ($session->is_completed) {
                $result = $this->flow->evaluateGlobalRisk($session);
                return response()->json([
                    'message' => 'Evaluation terminee.',
                    'score' => $result['total_score'],
                    'level' => $result['level'],
                    'blocs' => $result['blocs'],
                ]);
            }

            $next = $this->flow->goToNextQuestion($session);
            if (! $next) {
                $result = $this->flow->evaluateGlobalRisk($session);
                return response()->json([
                    'message' => 'Evaluation terminee.',
                    'score' => $result['total_score'],
                    'level' => $result['level'],
                    'blocs' => $result['blocs'],
                ]);
            }

            return response()->json([
                'message' => 'Passage a un nouveau bloc.',
                'question' => $next,
            ]);
        }

        return response()->json([
            'question' => $visibleQuestions[0],
        ]);
    }

    /**
     * Forcer le passage au bloc suivant.
     */
    public function nextGroup(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);
        if (! $session) {
            return response()->json(['error' => 'Session active non trouvee.'], 400);
        }

        $next = $this->flow->goToNextQuestion($session);
        if (! $next) {
            $result = $this->flow->evaluateGlobalRisk($session);
            return response()->json([
                'message' => 'Evaluation terminee.',
                'score' => $result['total_score'],
                'level' => $result['level'],
                'blocs' => $result['blocs'],
            ]);
        }

        return response()->json([
            'message' => 'Passage a un nouveau bloc.',
            'question' => $next,
        ]);
    }

    /**
     * Methode utilitaire pour retrouver une session (via ID ou user courant).
     */
    protected function resolveSession(Request $request): ?EvaluationSession
    {
        return $request->get('evaluation_session_id')
            ? EvaluationSession::with(['answers', 'currentQuestion'])->find($request->get('evaluation_session_id'))
            : $request->user()?->evaluationSessions()->with(['answers', 'currentQuestion'])->latest()->first();
    }
}


