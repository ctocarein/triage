<?php

namespace App\Domains\Decision\Controllers\Patient;

use App\Domains\Decision\DTOs\Patient\DecisionFinalizeRequest;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Decision\Services\DecisionInsightService;
use App\Domains\Evaluation\Services\EvaluationFlowService;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DecisionsController extends Controller
{
    public function finalize(
        DecisionFinalizeRequest $request,
        EvaluationSession $session,
        EvaluationFlowService $flow,
        DecisionInsightService $insights
    ): JsonResponse {
        $this->ensureSessionAccess($request, $session);

        if ($session->status !== 'completed') {
            return response()->json([
                'message' => 'La session doit etre completee avant la decision finale.',
            ], 409);
        }

        $report = $session->report;
        $insight = $insights->generate($session, $report);

        $insight->final_decision = [
            'decision' => $request->input('decision'),
            'notes' => $request->input('notes'),
            'metadata' => $request->input('metadata'),
            'decided_by_id' => $request->user()?->id,
            'decided_by_type' => $request->user() ? class_basename($request->user()) : null,
            'decided_at' => now()->toISOString(),
        ];
        $insight->save();

        $payload = $flow->buildPayload(
            $session,
            $insight,
            null,
            $report,
            'Decision finale enregistree.'
        );

        return response()->json($payload);
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
