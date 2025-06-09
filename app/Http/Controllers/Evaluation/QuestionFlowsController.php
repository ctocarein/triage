<?php

namespace App\Http\Controllers\Evaluation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Evaluation\EvaluationSession;
use App\Services\Evaluation\QuestionFlowService;

class QuestionFlowsController extends Controller
{
    protected QuestionFlowService $flow;

    public function __construct(QuestionFlowService $flow)
    {
        $this->flow = $flow;
    }

    /**
     * 🎯 Point d’entrée pour démarrer avec des questions ciblées.
     */
    public function entryPoints(Request $request)
    {
        $target = $request->query('answer_target');
        return response()->json(
            $this->flow->getEntryPoints($target)
        );
    }

    /**
     * 🔄 Récupère les prochaines questions visibles du bloc courant.
     */
    public function flow(Request $request)
    {
        $session = $this->resolveSession($request);
        if (!$session) {
            return response()->json(['error' => 'Session active non trouvée.'], 400);
        }

        if (!$session->currentQuestion) {
            return response()->json(['error' => 'Aucune question en cours.'], 400);
        }

        $visibleQuestions = $this->flow->getNextQuestions($session);

        if (empty($visibleQuestions)) {
            if ($session->is_completed) {
                $result = $this->flow->evaluateGlobalRisk($session);
                return response()->json([
                    'message' => '✅ Évaluation terminée.',
                    'score' => $result['total_score'],
                    'level' => $result['level'],
                    'blocs' => $result['blocs']
                ]);
            }

            $next = $this->flow->goToNextQuestion($session);
            if (!$next) {
                $result = $this->flow->evaluateGlobalRisk($session);
                return response()->json([
                    'message' => '✅ Évaluation terminée.',
                    'score' => $result['total_score'],
                    'level' => $result['level'],
                    'blocs' => $result['blocs']
                ]);
            }

            return response()->json([
                'message' => '▶️ Passage à un nouveau bloc.',
                'question' => $next
            ]);
        }

        return response()->json([
            'question' => $visibleQuestions[0]
        ]);
    }

    /**
     * 🔍 Méthode utilitaire pour retrouver une session (via ID ou user courant).
     */
    protected function resolveSession(Request $request): ?EvaluationSession
    {
        return $request->get('evaluation_session_id')
            ? EvaluationSession::with(['answers', 'currentQuestion'])->find($request->get('evaluation_session_id'))
            : $request->user()?->evaluationSessions()->with(['answers', 'currentQuestion'])->latest()->first();
    }
}
