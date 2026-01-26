<?php

namespace App\Domains\Evaluation\Controllers\Patient;

use App\Domains\Evaluation\DTOs\Patient\EvaluationAdvanceRequest;
use App\Domains\Evaluation\DTOs\Patient\EvaluationSessionStartRequest;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Services\EvaluationFlowService;
use App\Domains\Evaluation\Services\IdempotencyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class EvaluationsController extends Controller
{
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
     *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="integer")),
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
        $this->ensureSessionAccess($request, $session);

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
     *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="integer")),
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
        $this->ensureSessionAccess($request, $session);

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
     *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="integer")),
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
        $this->ensureSessionAccess($request, $session);

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

    private function ensureSessionAccess(Request $request, EvaluationSession $session): void
    {
        $identityId = $request->user()?->id;

        if ($session->identity_id !== $identityId) {
            abort(403, 'Acces interdit a cette session.');
        }
    }
}
