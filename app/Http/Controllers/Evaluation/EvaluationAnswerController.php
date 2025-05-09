<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Models\Evaluation\EvaluationAnswer;
use App\Models\Evaluation\EvaluationSession;
use App\Models\Evaluation\DynamicQuestionTemplate;
use Illuminate\Http\Request;

class EvaluationAnswerController extends Controller
{
    /**
     * Lister les réponses d'une session
     */
    public function index($sessionId)
    {
        $answers = EvaluationAnswer::with('question')
            ->where('evaluation_session_id', $sessionId)
            ->get();

        return response()->json($answers);
    }

    /**
     * Enregistrer ou mettre à jour une réponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'evaluation_session_id' => 'required|exists:evaluation_sessions,id',
            'dynamic_question_template_id' => 'required|exists:dynamic_question_templates,id',
            'value' => 'nullable',
            'is_skipped' => 'boolean',
        ]);
    
        $answer = EvaluationAnswer::create($validated);
    
        // Appel du service de score
        app(\App\Services\Evaluation\ScoreService::class)
            ->updateScore($answer->session, $answer->dynamic_question_template_id, $validated['is_skipped'] ?? false);
    
        return response()->json($answer, 201);
    }
    
    /**
     * Supprimer une réponse (optionnel, pour reprise de session)
     */
    public function destroy($id)
    {
        $answer = EvaluationAnswer::findOrFail($id);
        $answer->delete();

        return response()->json(['message' => 'Réponse supprimée']);
    }

}