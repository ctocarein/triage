<?php

namespace App\Http\Controllers\Evaluation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Evaluation\AnswerService;
use App\Models\Evaluation\EvaluationAnswer;
use App\Models\Evaluation\EvaluationSession;
use App\Models\Evaluation\DynamicQuestionTemplate;
use App\Services\Evaluation\ProfileUpdaterService;

class EvaluationAnswerController extends Controller
{
    /**
     * Lister toutes les réponses d’une session
     */
    public function index($sessionId)
    {
        $session = EvaluationSession::with('answers.question')->findOrFail($sessionId);

        return response()->json([
            'session_id' => $session->id,
            'answers' => $session->answers
        ]);
    }

    /**
     * Enregistrer une réponse à une question
     */
    public function store(Request $request, AnswerService $answerService, ProfileUpdaterService $profileUpdater)
    {
        $session = $request->get('active_evaluation_session');

        $validated = $request->validate([
            'dynamic_question_template_id' => 'required|exists:dynamic_question_templates,id',
            'value' => 'nullable|string',
            'is_skipped' => 'sometimes|boolean',
        ]);

        $validated['evaluation_session_id'] = $session->id;
        $validated['is_skipped'] = $validated['is_skipped'] ?? false;

        try {
            // Enregistrement de la réponse
            $answer = $answerService->saveAnswer($validated);
            // Chargement des relations nécessaires
            $answer->load(['question', 'session']);

            // Vérification sécurisée de la cible de profil
            if ($answer->question && in_array($answer->question->answer_target, ['personal_profile', 'medical_profile'])) {
                $profileUpdater->syncFromAnswers($answer->session);
            }

            return response()->json([
                'message' => 'Réponse enregistrée avec succès.',
                'answer' => $answer
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 400);
        }
    }


  

    /**
     * Supprimer une réponse (optionnel)
     */
    public function destroy($id)
    {
        $answer = EvaluationAnswer::findOrFail($id);
        $answer->delete();

        return response()->json(['message' => 'Réponse supprimée.']);
    }
}
