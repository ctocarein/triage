<?php

namespace App\Domains\Decision\Controllers\Patient;

use App\Domains\Decision\DTOs\Patient\DecisionFinalizeRequest;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Decision\Services\DecisionInsightService;
use App\Domains\Evaluation\Services\EvaluationFlowService;
use App\Http\Controllers\Controller;
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
        $identityId = $request->user()?->id;

        if ($session->identity_id !== $identityId) {
            abort(403, 'Acces interdit a cette session.');
        }
    }
}
