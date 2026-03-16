<?php

namespace App\Domains\Evaluation\Controllers\Patient;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Services\Navigation\QuestionSequencer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @deprecated Utiliser les routes canoniques du domaine Evaluation.
 *             Ce contrôleur sera supprimé dans une prochaine version majeure.
 */
class QuestionFlowsController extends Controller
{
    protected QuestionSequencer $flow;

    public function __construct(QuestionSequencer $flow)
    {
        $this->flow = $flow;
    }

    /**
     * Point d'entree pour demarrer avec des questions ciblees.
     *
     * @deprecated
     */
    public function entryPoints(Request $request): JsonResponse
    {
        $target = $request->query('answer_target');

        return response()->json(
            $this->flow->getEntryPoints($target)
        )->header('Deprecation', 'true');
    }

    /**
     * Recuperer les prochaines questions visibles du bloc courant.
     *
     * @deprecated
     */
    public function flow(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);
        if (! $session) {
            return response()->json(['error' => 'Session active non trouvee.'], 400)
                ->header('Deprecation', 'true');
        }

        if (! $session->currentQuestion) {
            return response()->json(['error' => 'Aucune question en cours.'], 400)
                ->header('Deprecation', 'true');
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
                ])->header('Deprecation', 'true');
            }

            $next = $this->flow->goToNextQuestion($session);
            if (! $next) {
                $result = $this->flow->evaluateGlobalRisk($session);
                return response()->json([
                    'message' => 'Evaluation terminee.',
                    'score' => $result['total_score'],
                    'level' => $result['level'],
                    'blocs' => $result['blocs'],
                ])->header('Deprecation', 'true');
            }

            return response()->json([
                'message' => 'Passage a un nouveau bloc.',
                'question' => $next,
            ])->header('Deprecation', 'true');
        }

        return response()->json([
            'question' => $visibleQuestions[0],
        ])->header('Deprecation', 'true');
    }

    /**
     * Forcer le passage au bloc suivant.
     *
     * @deprecated
     */
    public function nextGroup(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);
        if (! $session) {
            return response()->json(['error' => 'Session active non trouvee.'], 400)
                ->header('Deprecation', 'true');
        }

        $next = $this->flow->goToNextQuestion($session);
        if (! $next) {
            $result = $this->flow->evaluateGlobalRisk($session);
            return response()->json([
                'message' => 'Evaluation terminee.',
                'score' => $result['total_score'],
                'level' => $result['level'],
                'blocs' => $result['blocs'],
            ])->header('Deprecation', 'true');
        }

        return response()->json([
            'message' => 'Passage a un nouveau bloc.',
            'question' => $next,
        ])->header('Deprecation', 'true');
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


