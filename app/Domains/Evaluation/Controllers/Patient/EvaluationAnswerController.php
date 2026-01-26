<?php

namespace App\Domains\Evaluation\Controllers\Patient;

use App\Domains\Evaluation\DTOs\Patient\EvaluationAnswerStoreRequest;
use App\Domains\Evaluation\Models\EvaluationAnswer;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\AnswerService;
use App\Domains\Evaluation\Services\ProfileUpdaterService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class EvaluationAnswerController extends Controller
{
    /**
     * Lister toutes les reponses d'une session.
     */
    public function index(int $sessionId): JsonResponse
    {
        $session = EvaluationSession::with('answers.question')->findOrFail($sessionId);

        return response()->json([
            'session_id' => $session->id,
            'answers' => $session->answers,
        ]);
    }

    /**
     * Enregistrer une reponse a une question.
     */
    public function store(
        EvaluationAnswerStoreRequest $request,
        AnswerService $answerService,
        ProfileUpdaterService $profileUpdater
    ): JsonResponse {
        $session = $request->get('active_evaluation_session');
        if (! $session) {
            return response()->json(['error' => 'Session non trouvee.'], 400);
        }

        $validated = $request->validated();
        $validated['evaluation_session_id'] = $session->id;
        $validated['is_skipped'] = filter_var($validated['is_skipped'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $question = DynamicQuestionTemplate::findOrFail($validated['dynamic_question_template_id']);

        if ($question->type === 'file' && $request->hasFile('value')) {
            $file = $request->file('value');
            $validated['value'] = $file->store('uploads/responses', 'public');
        }

        if ($question->type === 'select_multiple' && is_array($request->value)) {
            $validated['value'] = json_encode($request->value);
        }

        try {
            $answer = $answerService->saveAnswer($validated);
            $answer->load(['question', 'session']);

            if (
                $answer->question &&
                in_array($answer->question->answer_target, ['personal_profile', 'medical_profile'])
            ) {
                $profileUpdater->syncFromAnswers($session);
            }

            $blocKey = $answer->question->bloc_key;
            if ($blocKey) {
                $answeredIds = $session->answers()->pluck('dynamic_question_template_id')->toArray();

                $remaining = DynamicQuestionTemplate::where('bloc_key', $blocKey)
                    ->whereNotIn('id', $answeredIds)
                    ->get()
                    ->filter(function ($q) use ($session) {
                        $flow = app(\App\Domains\Evaluation\Services\QuestionFlowService::class);
                        $context = $flow->getSessionContext($session);
                        return $flow->shouldBeVisible($q, $context['answers'], $context['profile']);
                    });

                if ($remaining->isEmpty()) {
                    $session->addVisitedBloc($blocKey);
                }
            }

            return response()->json([
                'message' => 'Reponse enregistree avec succes.',
                'answer' => $answer,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Supprimer une reponse (optionnel).
     */
    public function destroy(int $id): JsonResponse
    {
        $answer = EvaluationAnswer::findOrFail($id);
        $answer->delete();

        return response()->json(['message' => 'Reponse supprimee.']);
    }
}


