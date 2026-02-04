<?php

namespace App\Domains\Evaluation\Controllers\Patient;

use App\Domains\Evaluation\DTOs\Patient\EvaluationAnswerStoreRequest;
use App\Domains\Evaluation\Models\EvaluationAnswer;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\AnswerService;
use App\Domains\Evaluation\Services\ProfileUpdaterService;
use App\Domains\Evaluation\Services\QuestionFlowService;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class EvaluationAnswerController extends Controller
{
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
     *     summary="Enregistrer une reponse a une question.",
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
        ProfileUpdaterService $profileUpdater,
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
                'routing' => $routing,
            ], 201);
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
     *     summary="Supprimer une reponse (optionnel).",
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

        return response()->json(['message' => 'Reponse supprimee.']);
    }

    private function ensureSessionAccess(Request $request, EvaluationSession $session): void
    {
        try {
            $this->authorize('view', $session);
        } catch (AuthorizationException $exception) {
            abort(404, 'Session introuvable.');
        }

        if ($this->tokenInvalidatesSession($request, $session)) {
            $this->expireSession($session);
            abort(410, 'Session invalidee.');
        }

        if ($session->isExpired()) {
            $this->expireSession($session);
            abort(410, 'Session expiree.');
        }
    }

    private function tokenInvalidatesSession(Request $request, EvaluationSession $session): bool
    {
        $tokenId = $request->user()?->currentAccessToken()?->id;
        $context = $session->context ?? [];
        $sessionTokenId = is_array($context) ? ($context['token_id'] ?? null) : null;

        return $tokenId !== null
            && $sessionTokenId !== null
            && (int) $tokenId !== (int) $sessionTokenId;
    }

    private function expireSession(EvaluationSession $session): void
    {
        if ($session->status !== 'expired') {
            $updates = ['status' => 'expired'];
            if ($session->expires_at === null) {
                $updates['expires_at'] = now();
            }
            $session->update($updates);
        }
    }
}
