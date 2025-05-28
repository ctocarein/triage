<?php

namespace App\Http\Controllers\Evaluation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Evaluation\QuestionGroup;
use App\Services\ReportGenerationService;
use App\Models\Evaluation\EvaluationSession;

class EvaluationSessionController extends Controller
{
    /**
     * 🧾 Lister les sessions de l'utilisateur connecté
     */
    public function index(Request $request)
    {
        $sessions = $request->user()
            ->evaluationSessions()
            ->withCount('answers')
            ->with('report') // charge le résumé automatiquement
            ->latest()
            ->get();

        return response()->json($sessions);
    }

    /**
     * 🆕 Démarrer une session (1 seule en cours autorisée)
     */

public function start(Request $request)
{
    $account = $request->user();

    // Vérifie si une session est déjà active
    $existing = $account->evaluationSessions()
        ->where('status', 'in_progress')
        ->first();

    if ($existing) {
        return response()->json([
            'message' => 'Une session est déjà en cours.',
            'session' => $existing,
            'question' => $existing->currentQuestion, // relation à définir si pas encore
        ], 200);
    }

    // Étape 1 – Crée la nouvelle session
    $session = $account->evaluationSessions()->create([
        'status' => 'in_progress',
        'started_at' => now(),
        'context' => $request->input('context', []),
    ]);

    // Étape 2 – Sélectionne le premier groupe actif (ordre logique)
    $firstGroup = QuestionGroup::query()
        ->where('is_active', true)
        ->orderBy('order')
        ->first();

    // Étape 3 – Charge la première question de ce groupe
    $firstQuestion = $firstGroup?->questions()
        ->orderBy('order')
        ->first();

    // Étape 4 – Enregistre la première question dans la session
    if ($firstQuestion) {
        $session->update([
            'current_question_id' => $firstQuestion->id,
        ]);
    }

    return response()->json([
        'message' => 'Session démarrée.',
        'session' => $session,
        'question' => $firstQuestion,
    ], 201);
}


    /**
     * ✅ Terminer la session et générer le rapport
     */
    public function complete(EvaluationSession $session, ReportGenerationService $reportService)
    {
        if ($session->status === 'completed') {
            return response()->json([
                'message' => 'Cette session est déjà complétée.',
                'report' => $session->report
            ], 200);
        }

        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $report = $reportService->generate($session);

        return response()->json([
            'message' => 'Session complétée avec succès.',
            'score' => $report->score,
            'risk_level' => $report->risk_level,
            'recommendation' => $report->recommendation,
            'report_id' => $report->id,
        ], 201);
    }

    /**
     * 🔍 Voir une session en détail
     */
    public function show(EvaluationSession $session)
    {
        return response()->json(
            $session->load([
                'answers.question',
                'report'
            ])
        );
    }
}
