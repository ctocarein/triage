<?php

namespace App\Domains\Report\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Report\Models\Report;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Report\Services\ReportGenerationService;

class ReportController extends Controller
{
    protected $reportGenerationService;

    public function __construct(ReportGenerationService $service)
    {
        $this->reportGenerationService = $service;
    }

    /**
     * Liste des rapports de l'utilisateur
     */
    public function index(Request $request)
    {
        $identityId = $request->user()?->id;
        if (!$identityId) {
            return response()->json([
                'message' => 'Aucun compte liÃ© Ã  cet utilisateur.'
            ], 403);
        }

        $reports = Report::where('identity_id', $identityId)
            ->with(['evaluationSession', 'decisionPath'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $reports,
        ]);
    }

    /**
     * DÃ©tail dâ€™un rapport prÃ©cis
     */
    public function show(Request $request, $id)
    {
        $identityId = $request->user()?->id;
        if (!$identityId) {
            return response()->json([
                'message' => 'Aucun compte liÃ© Ã  cet utilisateur.'
            ], 403);
        }

        $report = Report::with(['evaluationSession', 'decisionPath'])
            ->where('identity_id', $identityId)
            ->find($id);

        if (!$report) {
            return response()->json([
                'message' => 'Rapport non trouvÃ© ou accÃ¨s interdit.'
            ], 404);
        }

        return response()->json([
            'data' => $report,
        ]);
    }

    /**
     * GÃ©nÃ©rer un rapport Ã  partir d'une session d'Ã©valuation
     * @param Request $request (doit contenir l'id de la session)
     */
    public function store(Request $request)
    {
        $identityId = $request->user()?->id;
        $sessionId = $request->input('evaluation_session_id');
        if (!$sessionId) {
            return response()->json([
                'message' => 'Session d\'Ã©valuation non spÃ©cifiÃ©e.'
            ], 422);
        }

        $session = EvaluationSession::where('identity_id', $identityId)->find($sessionId);
        if (!$session) {
            return response()->json([
                'message' => 'Session d\'Ã©valuation introuvable ou accÃ¨s interdit.'
            ], 404);
        }

        // VÃ©rifie l'existence d'un rapport
        $existingReport = $session->report;
        if ($existingReport) {
            return response()->json([
                'data' => $existingReport,
                'message' => "Un rapport existe dÃ©jÃ  pour cette session.",
            ], 200);
        }

        // GÃ©nÃ©ration du rapport via le service dÃ©diÃ©
        $report = $this->reportGenerationService->generate($session);

        return response()->json([
            'data' => $report,
            'message' => "Rapport gÃ©nÃ©rÃ© avec succÃ¨s.",
        ], 201);
    }
    /**
     * Supprimer un rapport
     */
    public function destroy(Request $request, $id)
    {
        $identityId = $request->user()?->id;
        if (!$identityId) {
            return response()->json([
                'message' => 'Aucun compte liÃ© Ã  cet utilisateur.'
            ], 403);
        }

        $report = Report::where('identity_id', $identityId)->find($id);
        if (!$report) {
            return response()->json([
                'message' => 'Rapport non trouvÃ© ou accÃ¨s interdit.'
            ], 404);
        }

        // Suppression du rapport
        $report->delete();

        return response()->json([
            'message' => 'Rapport supprimÃ© avec succÃ¨s.',
        ]);
    }
}



