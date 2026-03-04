<?php

namespace App\Domains\Evaluation\Controllers\Admin;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class EvaluationSessionAdminController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/sessions",
     *     operationId="adminSessionsIndex",
     *     summary="Lister les sessions d'evaluation (supervision).",
     *     tags={"Admin - Sessions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status",     in="query", @OA\Schema(type="string", enum={"in_progress","completed","cancelled","expired"})),
     *     @OA\Parameter(name="type",       in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="risk_level", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="from",       in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to",         in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="per_page",   in="query", @OA\Schema(type="integer", default=30)),
     *     @OA\Response(response=200, description="Liste paginee des sessions", @OA\JsonContent(ref="#/components/schemas/PaginationMeta")),
     *     @OA\Response(response=401, description="Non authentifie"),
     *     @OA\Response(response=403, description="Acces refuse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = EvaluationSession::query()
            ->with('identity:id,kind,status')
            ->withCount('answers');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->input('risk_level'));
        }

        if ($request->filled('from')) {
            $query->where('started_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('started_at', '<=', $request->input('to'));
        }

        $sessions = $query
            ->orderByDesc('started_at')
            ->paginate($request->integer('per_page', 30))
            ->through(fn ($s) => $s->makeVisible(['id', 'identity_id']));

        return response()->json($sessions);
    }

    /**
     * @OA\Get(
     *     path="/admin/sessions/{session}",
     *     operationId="adminSessionsShow",
     *     summary="Detail d'une session d'evaluation.",
     *     tags={"Admin - Sessions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Session avec reponses, rapport et insight"),
     *     @OA\Response(response=404, description="Non trouvee")
     * )
     */
    public function show(EvaluationSession $session): JsonResponse
    {
        $session->load([
            'identity:id,kind,status',
            'answers.question:id,key,text,bloc_key',
            'report:id,evaluation_session_id,risk_level,score',
            'latestDecisionInsight:id,evaluation_session_id,risk_score,risk_level,confidence_score',
        ]);

        return response()->json($session->makeVisible(['id', 'identity_id']));
    }

    /**
     * @OA\Patch(
     *     path="/admin/sessions/{session}/cancel",
     *     operationId="adminSessionsCancel",
     *     summary="Annuler une session d'evaluation en cours.",
     *     tags={"Admin - Sessions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="reason", type="string", nullable=true))),
     *     @OA\Response(response=200, description="Session annulee", @OA\JsonContent(
     *         @OA\Property(property="message",      type="string"),
     *         @OA\Property(property="public_id",    type="string"),
     *         @OA\Property(property="status",       type="string"),
     *         @OA\Property(property="completed_at", type="string", format="date-time", nullable=true)
     *     )),
     *     @OA\Response(response=409, description="Session non annulable (pas en cours)"),
     *     @OA\Response(response=404, description="Non trouvee")
     * )
     */
    public function cancel(Request $request, EvaluationSession $session): JsonResponse
    {
        if (! $session->isInProgress()) {
            return response()->json([
                'error' => 'Seules les sessions en cours peuvent etre annulees.',
            ], 409);
        }

        $reason = $request->input('reason');
        $session->markCancelled($reason);

        return response()->json([
            'message'      => 'Session annulee.',
            'public_id'    => $session->public_id,
            'status'       => $session->status,
            'completed_at' => $session->completed_at,
        ]);
    }
}
