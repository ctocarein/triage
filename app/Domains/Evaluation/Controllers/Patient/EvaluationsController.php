<?php

namespace App\Domains\Evaluation\Controllers\Patient;

use App\Domains\Evaluation\DTOs\Patient\EvaluationAdvanceRequest;
use App\Domains\Evaluation\DTOs\Patient\EvaluationSessionStartRequest;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Services\EvaluationFlowService;
use App\Domains\Evaluation\Services\Support\IdempotencyService;
use App\Domains\Evaluation\Http\Concerns\ValidatesEvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class EvaluationsController extends Controller
{
    use ValidatesEvaluationSession;

    /**
     * @OA\Post(
     *     path="/evaluations/start",
     *     operationId="evaluationsStart",
     *     summary="Start an evaluation session",
     *     tags={"Evaluations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Idempotency-Key",
     *         in="header",
     *         required=false,
     *         description="Optional. If absent, the server generates a UUID and returns it in the response header.",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/EvaluationStartRequest")),
     *     @OA\Response(
     *         response=201,
     *         description="Session started",
     *         @OA\Header(header="Idempotency-Key", description="Generated or reused idempotency key", @OA\Schema(type="string", format="uuid")),
     *         @OA\JsonContent(ref="#/components/schemas/EvaluationFlowResponse")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Session already in progress",
     *         @OA\Header(header="Idempotency-Key", description="Generated or reused idempotency key", @OA\Schema(type="string", format="uuid")),
     *         @OA\JsonContent(ref="#/components/schemas/EvaluationFlowResponse")
     *     ),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function start(
        EvaluationSessionStartRequest $request,
        EvaluationFlowService $flow,
        IdempotencyService $idempotency
    ): JsonResponse {
        $key = $idempotency->requireKey($request);
        $hash = $idempotency->hashRequest($request);
        $cached = $idempotency->getResponse($key, 'evaluations.start', $hash);

        if ($cached) {
            return response()
                ->json($cached['body'], $cached['status'])
                ->header('Idempotency-Key', $key);
        }

        $result = $flow->start($request->user(), $request->validated());
        $idempotency->store($key, 'evaluations.start', $hash, $result['payload'], $result['status']);

        return response()
            ->json($result['payload'], $result['status'])
            ->header('Idempotency-Key', $key);
    }

    /**
     * @OA\Post(
     *     path="/evaluations/{session}/advance",
     *     operationId="evaluationsAdvance",
     *     summary="Advance an evaluation session",
     *     tags={"Evaluations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(
     *         name="Idempotency-Key",
     *         in="header",
     *         required=false,
     *         description="Optional. If absent, the server generates a UUID and returns it in the response header.",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/EvaluationAdvanceRequest")),
     *     @OA\Response(
     *         response=200,
     *         description="Session advanced",
     *         @OA\Header(header="Idempotency-Key", description="Generated or reused idempotency key", @OA\Schema(type="string", format="uuid")),
     *         @OA\JsonContent(ref="#/components/schemas/EvaluationFlowResponse")
     *     ),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function advance(
        EvaluationAdvanceRequest $request,
        EvaluationSession $session,
        EvaluationFlowService $flow,
        IdempotencyService $idempotency
    ): JsonResponse {
        try {
            $this->authorize('view', $session);
        } catch (AuthorizationException $exception) {
            logger()->warning('Evaluation session access denied.', [
                'session_id' => $session->id,
                'identity_id' => $request->user()?->id,
            ]);
            abort(403, 'You are not allowed to access this session.');
        }

        if ($this->tokenInvalidatesSession($request, $session)) {
            $this->expireSession($session);
            abort(410, 'Session invalidee.');
        }

        if ($session->isExpired()) {
            $this->expireSession($session);
            abort(410, 'Session expiree.');
        }

        $key = $idempotency->requireKey($request);
        $hash = $idempotency->hashRequest($request);
        $scope = 'evaluations.advance.'.$session->id;
        $cached = $idempotency->getResponse($key, $scope, $hash);

        if ($cached) {
            return response()
                ->json($cached['body'], $cached['status'])
                ->header('Idempotency-Key', $key);
        }

        $result = $flow->advance($session, $request->validated());
        $idempotency->store($key, $scope, $hash, $result['payload'], $result['status']);

        return response()
            ->json($result['payload'], $result['status'])
            ->header('Idempotency-Key', $key);
    }

    /**
     * @OA\Get(
     *     path="/evaluations/{session}/state",
     *     operationId="evaluationsState",
     *     summary="Get evaluation session state",
     *     tags={"Evaluations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(
     *         name="Idempotency-Key",
     *         in="header",
     *         required=false,
     *         description="Optional. If absent, the server generates a UUID and returns it in the response header.",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Session state", @OA\JsonContent(ref="#/components/schemas/EvaluationFlowResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function state(
        Request $request,
        EvaluationSession $session,
        EvaluationFlowService $flow
    ): JsonResponse {
        try {
            $this->authorize('view', $session);
        } catch (AuthorizationException $exception) {
            logger()->warning('Evaluation session access denied.', [
                'session_id' => $session->id,
                'identity_id' => $request->user()?->id,
            ]);
            abort(403, 'You are not allowed to access this session.');
        }

        if ($this->tokenInvalidatesSession($request, $session)) {
            $this->expireSession($session);
            abort(410, 'Session invalidee.');
        }

        if ($session->isExpired()) {
            $this->expireSession($session);
            abort(410, 'Session expiree.');
        }

        $result = $flow->state($session);

        return response()->json($result['payload'], $result['status']);
    }

    /**
     * @OA\Post(
     *     path="/evaluations/{session}/complete",
     *     operationId="evaluationsComplete",
     *     summary="Complete an evaluation session",
     *     tags={"Evaluations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(
     *         name="Idempotency-Key",
     *         in="header",
     *         required=false,
     *         description="Optional. If absent, the server generates a UUID and returns it in the response header.",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Session completed",
     *         @OA\Header(header="Idempotency-Key", description="Generated or reused idempotency key", @OA\Schema(type="string", format="uuid")),
     *         @OA\JsonContent(ref="#/components/schemas/EvaluationFlowResponse")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Session already completed",
     *         @OA\Header(header="Idempotency-Key", description="Generated or reused idempotency key", @OA\Schema(type="string", format="uuid")),
     *         @OA\JsonContent(ref="#/components/schemas/EvaluationFlowResponse")
     *     ),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function complete(
        Request $request,
        EvaluationSession $session,
        EvaluationFlowService $flow,
        IdempotencyService $idempotency
    ): JsonResponse {
        try {
            $this->authorize('view', $session);
        } catch (AuthorizationException $exception) {
            logger()->warning('Evaluation session access denied.', [
                'session_id' => $session->id,
                'identity_id' => $request->user()?->id,
            ]);
            abort(403, 'You are not allowed to access this session.');
        }

        if ($this->tokenInvalidatesSession($request, $session)) {
            $this->expireSession($session);
            abort(410, 'Session invalidee.');
        }

        if ($session->isExpired()) {
            $this->expireSession($session);
            abort(410, 'Session expiree.');
        }

        $key = $idempotency->requireKey($request);
        $hash = $idempotency->hashRequest($request);
        $scope = 'evaluations.complete.'.$session->id;
        $cached = $idempotency->getResponse($key, $scope, $hash);

        if ($cached) {
            return response()
                ->json($cached['body'], $cached['status'])
                ->header('Idempotency-Key', $key);
        }

        $result = $flow->complete($session);
        $idempotency->store($key, $scope, $hash, $result['payload'], $result['status']);

        return response()
            ->json($result['payload'], $result['status'])
            ->header('Idempotency-Key', $key);
    }

    /**
     * @OA\Get(
     *     path="/evaluations/current",
     *     operationId="evaluationsCurrent",
     *     summary="Get the current in-progress evaluation session",
     *     tags={"Evaluations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="subject_id",
     *         in="query",
     *         required=false,
     *         description="Beneficiary whose session is requested. Reserved to evaluators conducting it.",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Current session", @OA\JsonContent(ref="#/components/schemas/EvaluationFlowResponse")),
     *     @OA\Response(response=404, description="No active session")
     * )
     */
    public function current(
        Request $request,
        EvaluationFlowService $flow
    ): JsonResponse {
        $actor = $request->user();

        $validated = $request->validate([
            'subject_id' => ['nullable', 'uuid', 'exists:identities,id'],
        ]);

        $session = $actor instanceof Identity
            ? $this->resolveCurrentSession($actor, $validated['subject_id'] ?? null)
            : null;

        if (! $session) {
            return response()->json(['message' => 'Aucune session active.'], 404);
        }

        $this->ensureSessionAccess($request, $session);

        $result = $flow->state($session);

        return response()->json($result['payload'], $result['status']);
    }

    /**
     * Resout la session « courante » d'un acteur, du plus explicite au plus large.
     *
     * 1. subject_id fourni : la session active de cette beneficiaire ;
     * 2. sinon la session dont l'acteur est lui-meme le sujet ;
     * 3. sinon la derniere session qu'il conduit pour quelqu'un d'autre.
     *
     * Le troisieme cas est ce qui permet a un evaluateur de reprendre une visite
     * apres une coupure sans avoir memorise pour qui il la menait -- notamment
     * quand la beneficiaire venait d'etre creee par le demarrage lui-meme et
     * que le client n'a pas eu le temps d'enregistrer son identifiant.
     *
     * Aucun de ces chemins n'accorde d'acces : la policy est appliquee ensuite
     * par ensureSessionAccess, qui repond 404 plutot que de reveler l'existence
     * d'une session que l'acteur n'a pas le droit de voir.
     */
    protected function resolveCurrentSession(Identity $actor, ?string $subjectId): ?EvaluationSession
    {
        if ($subjectId !== null) {
            return EvaluationSession::query()
                ->where('identity_id', $subjectId)
                ->active()
                ->latest('started_at')
                ->first();
        }

        $own = $actor->evaluationSessions()
            ->active()
            ->latest('started_at')
            ->first();

        if ($own) {
            return $own;
        }

        return EvaluationSession::query()
            ->where('context->agent_id', (string) $actor->id)
            ->active()
            ->latest('started_at')
            ->first();
    }

}
