<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
            $session = $request->get('active_evaluation_session');
            $question = $session->currentQuestion;

            if (!$question || !$question->question_group_id) {
                return response()->json(['error' => 'Aucune question active ou groupe introuvable.'], 400);
            }

            $answers = $request->input('answers', []);
            $groupId = $question->question_group_id;

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