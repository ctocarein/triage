<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Evaluation\EvaluationSession;
use Illuminate\Support\Facades\Auth;

class EvaluationSessionController extends Controller
{
    /**
     * Lister les sessions de l'utilisateur connecté
     */
    public function index(Request $request)
    {
        $sessions = $request->user()
            ->evaluationSessions()
            ->latest()
            ->withCount('answers')
            ->get();

        return response()->json($sessions);
    }

    /**
     * Démarrer une nouvelle session (si aucune en cours)
     */
    public function start(Request $request)
    {
        $account = $request->user();

        $existing = $account->evaluationSessions()
            ->where('status', 'in_progress')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Une session est déjà en cours.',
                'session' => $existing
            ], 200);
        }

        $session = $account->evaluationSessions()->create([
            'status' => 'in_progress',
            'started_at' => now(),
            'context' => $request->input('context', []),
        ]);

        return response()->json($session, 201);
    }

    /**
     * Terminer une session
     */
    public function complete(EvaluationSession $session)
    {
        if ($session->status === 'completed') {
            return response()->json(['message' => 'Session déjà complétée.'], 400);
        }

        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json(['message' => 'Session complétée.']);
    }

    public function store(){
        // Récupération de la question et son poids
    $question = DynamicQuestionTemplate::find($validated['dynamic_question_template_id']);

    $scoreIncrement = 0;
    if (!$validated['is_skipped'] && $question && $question->risk_weight > 0) {
        $scoreIncrement = (int) $question->risk_weight;
    }

    // Mettre à jour le score total
    $session = EvaluationSession::find($validated['evaluation_session_id']);
    $session->increment('score', $scoreIncrement);
    }

    /**
     * Voir une session spécifique
     */
    public function show(EvaluationSession $session)
    {
        return response()->json($session->load('answers.question'));
    }
}