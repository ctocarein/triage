<?php

namespace App\Http\Controllers\Evaluation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
        $answers = $request->input('answers', []);

        return response()->json(
            $this->flow->getVisibleQuestions($answers)
        );
    }
}