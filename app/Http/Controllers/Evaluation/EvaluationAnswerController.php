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
  public function store(
    Request $request,
    AnswerService $answerService,
    ProfileUpdaterService $profileUpdater) 
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
        // 1️⃣ Sauvegarder la réponse
        $answer = $answerService->saveAnswer($validated);
        $answer->load(['question', 'session']);

        // 2️⃣ Si la réponse cible un profil, on le synchronise
        if ($answer->question && in_array($answer->question->answer_target, ['personal_profile', 'medical_profile'])) {
            $profileUpdater->syncFromAnswers($session);
        }

        // 3️⃣ Mise à jour éventuelle des blocs visités
        $blocKey = $answer->question->bloc_key;
        if ($blocKey) {
            $answeredIds = $session->answers->pluck('dynamic_question_template_id')->toArray();

            $remaining = DynamicQuestionTemplate::where('bloc_key', $blocKey)
                ->whereNotIn('id', $answeredIds)
                ->get()
                ->filter(function ($q) use ($session) {
                    $flow = app(\App\Services\Evaluation\QuestionFlowService::class);
                    $context = $flow->getSessionContext($session);
                    return $flow->shouldBeVisible($q, $context['answers'], $context['profile']);
                });

            if ($remaining->isEmpty()) {
                $session->addVisitedBloc($blocKey);
            }
        }

        return response()->json([
            'message' => 'Réponse enregistrée avec succès.',
            'answer' => $answer,
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
