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

    public function entryPoints(Request $request)
    {
        $target = $request->query('answer_target');

        return response()->json(
            $this->flow->getEntryPoints($target)
        );
    }

   public function flow(Request $request)
    {
        // 🔐 On récupère la session active (par ID ou utilisateur courant)
        /** @var EvaluationSession|null $session */
        $session = $request->get('evaluation_session_id')
            ? EvaluationSession::with(['answers', 'currentQuestion'])->find($request->get('evaluation_session_id'))
            : $request->user()->evaluationSessions()->with(['answers', 'currentQuestion'])->latest()->first();

        if (!$session) {
            return response()->json(['error' => 'Session active non trouvée.'], 400);
        }

        // 📍 Si aucune question courante, renvoyer une erreur claire
        if (!$session->currentQuestion) {
            return response()->json(['error' => 'Aucune question en cours.'], 400);
        }

        // 🔄 Récupérer dynamiquement les prochaines questions visibles
        $visibleQuestions = $this->flow->getNextQuestions($session);

        // 📦 Cas où le groupe actuel est terminé
        if (empty($visibleQuestions)) {
            // ➕ Essayer d'avancer vers le prochain groupe
            $nextQuestion = $this->flow->goToNextGroup($session);

            if (!$nextQuestion) {
                return response()->json(['message' => '✅ Évaluation terminée.'], 200);
            }

            return response()->json([
                'message' => '📍 Passage au groupe suivant.',
                'question' => $nextQuestion
            ]);
        }

        // ✅ On retourne la prochaine question visible
        return response()->json([
            'question' => $visibleQuestions[0],
        ]);
    }

    /**
     * Récupère le prochain groupe de questions à afficher.
     *
     * @param Request $request
     * @param QuestionFlowService $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function nextGroup(Request $request, QuestionFlowService $service)
    {
        $current = $request->query('current');
        $session = $request->user()->evaluationSessions()->latest()->first();

        $next = $service->nextGroup($current, $session);

        return response()->json(['next_group' => $next]);
    }
}