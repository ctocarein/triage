<?php

namespace App\Domains\Evaluation\Controllers\Patient;

use App\Domains\Evaluation\DTOs\Patient\EvaluationAnswerStoreRequest;
use App\Domains\Evaluation\Enums\QuestionType;
use App\Domains\Evaluation\Models\EvaluationAnswer;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\AnswerService;
use App\Domains\Evaluation\Services\QuestionFlowService;
use App\Domains\Evaluation\Http\Concerns\ValidatesEvaluationSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class EvaluationAnswerController extends Controller
{
    use ValidatesEvaluationSession;

    /**
     * @OA\Get(
     *     path="/answers/session/{session}",
     *     operationId="getAnswersBySession",
     *     summary="Lister toutes les reponses d'une session.",
     *     tags={"Answers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des reponses",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="session_id", type="string", format="uuid"),
     *             @OA\Property(property="answers", type="array", @OA\Items(ref="#/components/schemas/EvaluationAnswer"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifie", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=404, description="Session non trouvee")
     * )
     */
    public function index(Request $request, EvaluationSession $session): JsonResponse
    {
        $this->ensureSessionAccess($request, $session);
        $session->load('answers.question');

        return response()->json([
            'session_id' => $session->public_id,
            'answers' => $session->answers,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/answers",
     *     operationId="storeAnswer",
     *     summary="[DEPRECATED] Enregistrer une reponse a une question.",
     *     description="Route deprecie. Utiliser POST /evaluations/{session}/advance a la place.",
     *     deprecated=true,
     *     tags={"Answers"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/EvaluationAnswerStoreRequest")),
     *     @OA\Response(
     *         response=201,
     *         description="Reponse enregistree",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Reponse enregistree avec succes."),
     *             @OA\Property(property="answer", ref="#/components/schemas/EvaluationAnswer")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Erreur de validation ou session non trouvee"),
     *     @OA\Response(response=401, description="Non authentifie", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError"))
     * )
     */
    public function store(
        EvaluationAnswerStoreRequest $request,
        AnswerService $answerService,
        QuestionFlowService $flow
    ): JsonResponse {
        $session = $request->get('active_evaluation_session');
        if (! $session) {
            return response()->json(['error' => 'Session non trouvee.'], 400);
        }

        $validated = $request->validated();
        $validated['evaluation_session_id'] = $session->id;
        $validated['is_skipped'] = filter_var($validated['is_skipped'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $question = DynamicQuestionTemplate::findOrFail($validated['dynamic_question_template_id']);

        if ($question->type === QuestionType::File && $request->hasFile('value')) {
            $file = $request->file('value');
            $validated['value'] = $file->store('uploads/responses', 'public');
        }

        if ($question->type === QuestionType::SelectMultiple && is_array($request->value)) {
            $validated['value'] = json_encode($request->value);
        }

        try {
            $answer = $answerService->saveAnswer($validated);
            $answer->load(['question', 'session']);

            $routing = null;
            if ($answer->question) {
                $routing = $flow->applyRoutingRules($session, $answer->question, $answer->value);
            }

            $blocKey = $answer->question->bloc_key;
            if ($blocKey) {
                $answeredIds = $session->answers()->pluck('dynamic_question_template_id')->toArray();

                $remaining = DynamicQuestionTemplate::where('bloc_key', $blocKey)
                    ->whereNotIn('id', $answeredIds)
                    ->get()
                    ->filter(function ($q) use ($session, $flow) {
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
                'routing' => $routing,
            ], 201)->header('Deprecation', 'true')
                   ->header('Link', '</evaluations/{session}/advance>; rel="successor-version"');
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @OA\Delete(
     *     path="/answers/{id}",
     *     operationId="deleteAnswer",
     *     summary="[DEPRECATED] Supprimer une reponse.",
     *     description="Route deprecie. La suppression de reponse n'est plus supportee dans le flux canonical.",
     *     deprecated=true,
     *     tags={"Answers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Reponse supprimee",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Reponse supprimee.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifie", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=404, description="Reponse non trouvee")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $answer = EvaluationAnswer::findOrFail($id);
        $answer->delete();

        return response()->json(['message' => 'Reponse supprimee.'])
            ->header('Deprecation', 'true');
    }

}
