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
     * Point d’entrée pour l’interface : récupère les questions initiales selon le contexte.
     */
    public function entryPoints(Request $request)
    {
        $target = $request->query('answer_target');

        return response()->json(
            $this->flow->getEntryPoints($target)
        );
    }

    /**
     * Gère le flux des questions (navigation par groupe/question)
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

        // Groupe terminé, on passe au suivant
        if (empty($visibleQuestions)) {
            $nextKey = $this->flow->nextGroup($session->currentQuestion->group->key ?? null, $session);

            if (!$nextKey) {
                // Évaluation terminée, on peut retourner un score
                $result = $this->flow->evaluateGlobalRisk($session);

                return response()->json([
                    'message' => '✅ Évaluation terminée. backend',
                    'score' => $result['total_score'],
                    'level' => $result['level'],
                    'blocs' => $result['blocs']
                ]);
            }

            return response()->json([
                'message' => '📍 Passage au groupe suivant.',
                'next_group' => $nextKey
            ]);
        }

        return response()->json([
            'question' => $visibleQuestions[0]
        ]);
    }

    /**
     * Retourne le prochain groupe disponible.
     */
    public function nextGroup(Request $request)
    {
        $current = $request->query('current');
        $session = $this->resolveSession($request);

        if (!$session) {
            return response()->json(['error' => 'Session non trouvée.'], 400);
        }

        $next = $this->flow->nextGroup($current, $session);

        return response()->json(['next_group' => $next]);
    }

    /**
     * Méthode utilitaire pour récupérer la session active.
     */
    protected function resolveSession(Request $request): ?EvaluationSession
    {
        return $request->get('evaluation_session_id')
            ? EvaluationSession::with(['answers', 'currentQuestion'])->find($request->get('evaluation_session_id'))
            : $request->user()?->evaluationSessions()->with(['answers', 'currentQuestion'])->latest()->first();
    }
}
