<?php

namespace App\Http\Controllers\Evaluation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Evaluation\ThematicBloc;
use App\Models\Evaluation\QuestionGroup;
use App\Models\Evaluation\EvaluationSession;
use App\Services\Evaluation\QuestionFlowService;
use App\Models\Evaluation\DynamicQuestionTemplate;
use App\Services\Evaluation\ReportGenerationService;

class EvaluationSessionController extends Controller
{
    /**
     * 🧾 Lister les sessions de l'utilisateur connecté
     */
    public function index(Request $request)
    {
        $query = $request->user()->evaluationSessions()->withCount('answers')->with('report')->latest();

        if ($status = $request->input('status')) $query->where('status', $status);
        if ($type = $request->input('type')) $query->where('type', $type);

        $groupLabels = QuestionGroup::pluck('label', 'key');

        $sessions = $query->get()->map(function ($session) use ($groupLabels) {
            $thematics = $session->target_blocs
                ? collect($session->target_blocs)->map(fn($k) => $groupLabels[$k] ?? $k)->toArray()
                : null;
            return [
                'id' => $session->id,
                'type' => $session->type,
                'status' => $session->status,
                'started_at' => $session->started_at,
                'completed_at' => $session->completed_at,
                'target_blocs' => $session->target_blocs,
                'thematics' => $thematics,
                'title' => $session->type === 'complete'
                                ? "Votre évaluation complète réussie"
                                : ($thematics ? implode(', ', $thematics) : ''),
                'answers_count' => $session->answers_count ?? $session->answers()->count(),
                'report' => $session->report->recommendation ?? null,
            ];
        });

        return response()->json($sessions);
    }


    /**
     * 🆕 Démarrer une nouvelle session d’évaluation
     */
/**
 * 🆕 Démarrer une nouvelle session d’évaluation (complète ou thématique)
 */
public function start(Request $request, QuestionFlowService $flow)
{
    $account = $request->user();
    $type = $request->input('type', 'complete'); // 'complete' ou 'thematic'
    $blocKeys = $request->input('bloc_keys', []);
    $groupIds = null;

    // 1️⃣ — Empêche d’accéder aux thématiques sans avoir fini la complète
    if ($type !== 'complete' && !$account->hasCompletedFullEvaluation()) {
        return response()->json([
            'message' => 'Merci de compléter une évaluation complète avant d\'accéder aux évaluations thématiques.'
        ], 403);
    }

    // 2️⃣ — Refuse si une session complète est en cours
    $inProgressFull = $account->getInProgressFullEvaluation();
    if ($inProgressFull && $type !== 'complete') {
        return response()->json([
            'message' => 'Vous avez une évaluation complète en cours. Veuillez la terminer avant de continuer.',
            'session_id' => $inProgressFull->id
        ], 403);
    }
    if ($type === 'complete' && $inProgressFull) {
        return response()->json([
            'message' => 'Vous avez déjà une évaluation complète en cours. Veuillez la terminer.',
            'session_id' => $inProgressFull->id
        ], 403);
    }

    // 3️⃣ — Gestion spécifique pour les thématiques
    if ($type === 'thematic') {
        if (empty($blocKeys)) {
            return response()->json([
                'message' => "Veuillez sélectionner au moins un bloc thématique."
            ], 422);
        }
        // Vérifie les clés et récupère les blocs actifs
        $blocs = ThematicBloc::whereIn('key', $blocKeys)->where('is_active', true)->get();
        if ($blocs->isEmpty() || count($blocs) !== count($blocKeys)) {
            return response()->json([
                'message' => "Un ou plusieurs blocs sélectionnés ne sont pas valides ou actifs."
            ], 422);
        }
        // Récupère les IDs des groupes liés à ces blocs
        $groupIds = $blocs->pluck('question_group_id')->unique()->values();
    }

    // 4️⃣ — Stocke la sélection et le contexte dans la session (toujours prêt pour next)
    $context = $request->input('context', []);
    if ($type === 'thematic') {
        $context['selected_blocs'] = $blocKeys;
        $context['selected_group_ids'] = $groupIds;
    }

    $session = $account->evaluationSessions()->create([
        'status' => 'in_progress',
        'type' => $type,
        'started_at' => now(),
        'context' => $context,
        'target_blocs' => $type === 'thematic' ? $blocKeys : null,
    ]);

    // 5️⃣ — Récupère la 1ʳᵉ question à poser (selon les groupes si thématique)
    $firstQuestion = $flow->goToNextQuestion($session, $groupIds ?? []);
    if ($firstQuestion) {
        $session->update(['current_question_id' => $firstQuestion->id]);
    }

    // 6️⃣ — Retourne la session et la question au front
    return response()->json([
        'message' => 'Session démarrée.',
        'session' => $session,
        'question' => $firstQuestion,
    ], 201);
}



    /**
     * ▶️ Aller à la prochaine question
     */
    public function next(EvaluationSession $session, QuestionFlowService $flow)
    {
        // 1️⃣ — Vérifie l’état de la session
        if (!$session->isInProgress()) {
            return response()->json([
                'message' => 'Session déjà terminée.',
                'isCompleted' => true,
            ]);
        }

        // 2️⃣ — Récupère les filtres depuis le contexte pour thématique
        $groupIds = [];
        if ($session->type === 'thematic') {
            $groupIds = $session->context['selected_group_ids'] ?? [];
        }

        // 3️⃣ — Passe toujours les bons filtres au flow
        $nextQuestion = $flow->goToNextQuestion($session, $groupIds);

        // 4️⃣ — Si plus de question, termine la session proprement
        if (!$nextQuestion) {
            $session->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Évaluation terminée.',
                'isCompleted' => true,
            ]);
        }

        // 5️⃣ — Met à jour la session avec la nouvelle question
        $session->update(['current_question_id' => $nextQuestion->id]);

        // 6️⃣ — Infos de progression dans le bloc courant
        $context = $flow->getSessionContext($session);
        $blocKey = $nextQuestion->bloc_key;
        $blocQuestions = DynamicQuestionTemplate::where('bloc_key', $blocKey)->get();
        $answeredCount = $blocQuestions->whereIn('id', $context['answeredIds'])->count();

        return response()->json([
            'currentQuestion' => $nextQuestion,
            'isCompleted' => false,
            'id' => $session->id,
            'bloc_key' => $blocKey,
            'total' => $blocQuestions->count(),
            'answered' => $answeredCount,
            'visited_blocs' => $session->visited_blocs ?? [],
        ]);
    }


    /**
     * ✅ Terminer manuellement une session et générer son rapport
     */
  /**
 * Terminer une session et générer le rapport associé.
 */
        public function complete(
            EvaluationSession $session,
            ReportGenerationService $reportService,
            QuestionFlowService $flow
        ) {
            $account = auth()->user();

            // Vérifier que la session appartient bien à l'utilisateur connecté
            if ($session->account_id !== ($account->id ?? null)) {
                return response()->json([
                    'message' => "Accès interdit à cette session."
                ], 403);
            }

            // Idempotence : si déjà complétée, retourne le rapport existant OU le génère à la volée si manquant
            if ($session->status === 'completed') {
                $report = $session->report;
                if (!$report) {
                    // Rattrapage : générer le rapport si la session est complétée mais le rapport manquant
                    try {
                        $report = $reportService->generate($session);
                    } catch (\Throwable $e) {
                        return response()->json([
                            'message' => "Session complétée mais erreur lors de la génération du rapport : " . $e->getMessage()
                        ], 500);
                    }
                }
                return response()->json([
                    'message'        => 'Cette session est déjà complétée.',
                    'report_id'      => $report->id ?? null,
                    'recommendation' => $report->recommendation ?? null,
                    'risk_level'     => $report->risk_level ?? null,
                    'score'          => $report->score ?? null,
                    'created_at'     => $report->created_at ?? null,
                ]);
            }

            // Transaction pour cohérence (update + génération rapport)
            \DB::beginTransaction();
            try {
                $session->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);

                // Générer le rapport (normal)
                $report = $reportService->generate($session);
                if (!$report) {
                    throw new \Exception("Erreur lors de la génération du rapport.");
                }

                // Calcul des détails du score si nécessaire
                $scoreDetails = $flow->evaluateGlobalRisk($session);

                \DB::commit();

                return response()->json([
                    'message'        => 'Session complétée avec succès.',
                    'report_id'      => $report->id,
                    'recommendation' => $report->recommendation,
                    'risk_level'     => $report->risk_level,
                    'score'          => $report->score,
                    'created_at'     => $report->created_at,
                    'score_details'  => $scoreDetails,
                ], 201);
            } catch (\Throwable $e) {
                \DB::rollBack();
                return response()->json([
                    'message' => "Erreur lors de la complétion de la session : " . $e->getMessage()
                ], 500);
            }
        }



    /**
     * 🔍 Détails d’une session (admin ou historique)
     */
    public function show(EvaluationSession $session)
    {
        return response()->json(
            $session->load([
                'answers.question',
                'report',
            ])
        );
    }
}
