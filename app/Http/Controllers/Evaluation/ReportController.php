<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\Evaluation\EvaluationSession;
use App\Services\Evaluation\ReportGenerationService;

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
        $user = $request->user();
        $accountId = $user->account_id ?? $user->account->id ?? null;

        if (!$accountId) {
            return response()->json([
                'message' => 'Aucun compte lié à cet utilisateur.'
            ], 403);
        }

        $reports = "report data";

        return response()->json([
            'data' => $reports,
        ]);
    }

    /**
     * Détail d’un rapport précis
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $accountId = $user->account_id ?? $user->account->id ?? null;

        if (!$accountId) {
            return response()->json([
                'message' => 'Aucun compte lié à cet utilisateur.'
            ], 403);
        }

        $report = Report::with(['evaluationSession', 'decisionPath'])
            ->where('account_id', $accountId)
            ->find($id);

        if (!$report) {
            return response()->json([
                'message' => 'Rapport non trouvé ou accès interdit.'
            ], 404);
        }

        return response()->json([
            'data' => $report,
        ]);
    }

    /**
     * Générer un rapport à partir d'une session d'évaluation
     * @param Request $request (doit contenir l'id de la session)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $accountId = $user->account_id ?? $user->account->id ?? null;

        $sessionId = $request->input('evaluation_session_id');
        if (!$sessionId) {
            return response()->json([
                'message' => 'Session d\'évaluation non spécifiée.'
            ], 422);
        }

        $session = EvaluationSession::where('account_id', $accountId)->find($sessionId);
        if (!$session) {
            return response()->json([
                'message' => 'Session d\'évaluation introuvable ou accès interdit.'
            ], 404);
        }

        // Vérifie l'existence d'un rapport
        $existingReport = $session->report;
        if ($existingReport) {
            return response()->json([
                'data' => $existingReport,
                'message' => "Un rapport existe déjà pour cette session.",
            ], 200);
        }

        // Génération du rapport via le service dédié
        $report = $this->reportGenerationService->generate($session);

        return response()->json([
            'data' => $report,
            'message' => "Rapport généré avec succès.",
        ], 201);
    }
    /**
     * Supprimer un rapport
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $accountId = $user->account_id ?? $user->account->id ?? null;

        if (!$accountId) {
            return response()->json([
                'message' => 'Aucun compte lié à cet utilisateur.'
            ], 403);
        }

        $report = Report::where('account_id', $accountId)->find($id);
        if (!$report) {
            return response()->json([
                'message' => 'Rapport non trouvé ou accès interdit.'
            ], 404);
        }

        // Suppression du rapport
        $report->delete();

        return response()->json([
            'message' => 'Rapport supprimé avec succès.',
        ]);
    }
}
