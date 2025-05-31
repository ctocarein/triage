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
       /** @var EvaluationSession $session */
        $session = $request->get('current_question_id')
            ? EvaluationSession::with('currentQuestion')->find($request->get('current_question_id'))
            : $request->user()->evaluationSessions()->latest()->first();

        if (!$session) {
            return response()->json(['error' => 'Session active non trouvée.'], 400);
        }

        $session->load('currentQuestion');

        $currentQuestion = $session->currentQuestion;

        if (!$currentQuestion || !$currentQuestion->question_group_id) {
            return response()->json(['error' => 'Impossible de récupérer le groupe actif.'], 400);
        }

        $groupId = $currentQuestion->question_group_id;
        $answers = $request->input('answers', []);

            return response()->json(
                $this->flow->getVisibleQuestions($groupId, $answers)
            );
    }


    public function nextGroup(Request $request, QuestionFlowService $service)
    {
        $current = $request->query('current');
        $session = $request->user()->evaluationSessions()->latest()->first();

        $next = $service->nextGroup($current, $session);

        return response()->json(['next_group' => $next]);
    }
}