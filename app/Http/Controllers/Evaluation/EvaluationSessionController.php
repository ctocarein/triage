<?php

namespace App\Http\Controllers\Evaluation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Decisions\DecisionPath;
// use App\Models\Evaluation\ThematicBloc;
use App\Models\Evaluation\QuestionGroup;
use App\Models\Evaluation\EvaluationSession;
use App\Services\Decisions\DecisionPathEngine;
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
 * 🆕 Démarrer une nouvelle session d’évaluation (complète ou thématique)
 */
 public function start(Request $request)
{
        $validated = $request->validate([
            'type' => 'required|in:complete,thematic',
            'bloc_keys' => 'array|required_if:type,thematic',
            'bloc_keys.*' => 'string',
        ]);

        $account = $request->user();
        $type = $validated['type'];
        $blocKeys = $validated['bloc_keys'] ?? [];
        if (is_string($blocKeys)) {
            $blocKeys = array_filter(array_map('trim', explode(',', $blocKeys)));
        }
        if (!is_array($blocKeys)) {
            $blocKeys = [$blocKeys];
        }
        $decisionPath = null;

    // 1️⃣ — Bloque les thématiques si pas de full déjà complétée
    if ($type !== 'complete' && !$account->hasCompletedFullEvaluation()) {
        return response()->json([
            'message' => "Merci de compléter une évaluation complète avant d'accéder aux évaluations thématiques."
        ], 403);
    }

    // 2️⃣ — Refuse si une session complète est en cours
    $inProgressFull = $account->getInProgressFullEvaluation();
    if ($inProgressFull && $type !== 'complete') {
        return response()->json([
            'message' => "Vous avez une évaluation complète en cours. Veuillez la terminer avant de continuer.",
            'session_id' => $inProgressFull->id
        ], 403);
    }
    if ($type === 'complete' && $inProgressFull) {
        $node = null;
        $decisionPath = $inProgressFull->decisionPath;
        if ($decisionPath && $inProgressFull->current_question_id) {
            $node = $decisionPath->nodes()
                ->where('dynamic_question_template_id', $inProgressFull->current_question_id)
                ->first();
        }

        $questionPayload = $node && method_exists($node, 'getQuestionPayload')
            ? $node->getQuestionPayload()
            : null;
        return response()->json([
            'message' => 'Session déjà en cours.',
            'session_id' => $inProgressFull->id,
            'decision_path_id' => $inProgressFull->decision_path_id,
            'question' => $questionPayload,
        ], 200);;
    }

    // 3️⃣ — Gestion DecisionPath
    if ($type === 'complete') {
        $decisionPath = DecisionPath::where('key', 'dp_full_assessment')->first();
        if (!$decisionPath) {
            return response()->json([
                'message' => "Le parcours d'évaluation complète n'existe pas (clé dp_full_assessment manquante en base)."
            ], 500);
        }
        if (!$decisionPath->is_active) {
            return response()->json([
                'message' => "Le parcours d'évaluation complète n'est pas actif."
            ], 422);
        }
    } else { // "thematic"
        if (empty($blocKeys)) {
            return response()->json([
                'message' => "Veuillez sélectionner au moins un bloc thématique."
            ], 422);
        }
        $paths = DecisionPath::whereIn('key', $blocKeys)->where('is_active', true)->get();
        if ($paths->isEmpty() || count($paths) !== count($blocKeys)) {
            return response()->json([
                'message' => "Un ou plusieurs DecisionPath ne sont pas valides ou actifs."
            ], 422);
        }
        $decisionPath = $paths->first();
        if (!$decisionPath) {
            return response()->json([
                'message' => "Aucun parcours thématique actif trouvé."
            ], 500);
        }
    }

    // 4️⃣ — Création de la session
    $session = $account->evaluationSessions()->create([
        'decision_path_id' => $decisionPath->id,
        'type' => $type,
        'status' => 'in_progress',
        'started_at' => now(),
        'context' => [
            'bloc_keys' => $blocKeys,
        ],
    ]);

    // 5️⃣ — Démarrage du chemin : 1ère question
    $engine = new DecisionPathEngine($decisionPath);

    $firstNode = $engine->getFirstNode();

    // Vérifie l'existence du premier node et de la question liée
    if (!$firstNode) {
        return response()->json([
            'message' => "Le parcours d'évaluation n'a pas de première étape définie.",
            'session_id' => $session->id,
            'decision_path_id' => $decisionPath->id,
            'question' => null,
        ], 500);
    }

    
    if (method_exists($firstNode, 'getQuestionPayload') && $firstNode->dynamic_question_template_id) {
        $session->update(['current_question_id' => $firstNode->dynamic_question_template_id]);
        $questionPayload = $firstNode->getQuestionPayload();
    } else {
        $questionPayload = null;
    }

    // 6️⃣ — Retourne la réponse clean
    return response()->json([
        'message' => 'Session démarrée.',
        'session_id' => $session->id,
        'decision_path_id' => $decisionPath->id,
        'question' => $questionPayload,
    ], 201);
}



 /**
 * ▶️ Aller à la prochaine question (DecisionPath Engine)
 */
public function next(EvaluationSession $session)
{
  
    // 1️⃣ — Vérifie l’état de la session
    if (!$session->isInProgress()) {
        return response()->json([
            'message' => 'Session déjà terminée.',
            'isCompleted' => true,
        ]);
    }

    // 2️⃣ — Vérifie la validité du parcours
    $decisionPath = $session->decisionPath;

    if (!$decisionPath) {
        return response()->json([
            'message' => 'Aucun parcours décisionnel associé à cette session.',
            'isCompleted' => true,
        ], 500);
    }
 // 🔄 S'assure que la question courante a une réponse avant d'avancer
    if ($session->current_question_id) {
        $hasAnswer = $session->answers()
            ->where('dynamic_question_template_id', $session->current_question_id)
            ->exists();

        if (!$hasAnswer) {
            $node = $decisionPath->nodes()
                ->where('dynamic_question_template_id', $session->current_question_id)
                ->first();

            if ($node && method_exists($node, 'getQuestionPayload')) {
                return response()->json([
                    'currentQuestion' => $node->getQuestionPayload(),
                    'isCompleted' => false,
                    'id' => $session->id,
                ]);
            }
        }
    }
    // 2️⃣ — Instancie le moteur de décision avec le parcours
    $engine = new DecisionPathEngine($decisionPath);

    // 3️⃣ — Récupère la prochaine “node” selon les réponses accumulées
    $nextNode = $engine->getNextNode($session);

    // 4️⃣ — S’il n’y a plus de question (fin du flow)
    if (!$nextNode) {
        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
            'current_question_id' => null,
        ]);

        return response()->json([
            'message' => 'Évaluation terminée.',
            'isCompleted' => true,
        ]);
    }

    // 5️⃣ — Si le node est de type “end”/“recommendation”
    if (in_array($nextNode->node_type, ['end', 'recommendation'])) {
        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
            'current_question_id' => null,
        ]);
        // Génération immédiate du rapport
        $report = app(ReportGenerationService::class)->generate($session);

        return response()->json([
            'message'    => $nextNode->outcome_message ?? 'Fin du parcours.',
            'isCompleted'=> true,
            'report'     => $report,
        ]);
    }

    // 6️⃣ — Sinon, c’est une nouvelle question
    // ATTENTION : question_id doit être présent sinon bug front
    $question_id = $nextNode->question_id ?? $nextNode->dynamic_question_template_id ?? null;

    if (!$question_id) {
        return response()->json([
            'message' => "Problème de configuration: node sans question_id.",
            'isCompleted' => false,
        ], 500);
    }

    $session->update(['current_question_id' => $question_id]);

    // 7️⃣ — Retourne le payload question/étape
    $questionPayload = method_exists($nextNode, 'getQuestionPayload')
        ? $nextNode->getQuestionPayload()
        : null;

    if (!$questionPayload) {
        return response()->json([
            'message' => "Impossible de générer la question, données manquantes.",
            'isCompleted' => false,
        ], 500);
    }

    // 8️⃣ — (Optionnel) Calcul de progression, infos UX, etc.
    // $progress = ...

    return response()->json([
        'currentQuestion' => $questionPayload,
        'isCompleted' => false,
        'id' => $session->id,
        // 'progress' => $progress,
        // ... autres infos utiles pour le front ...
    ]);
}

  /**
 * Terminer une session et générer le rapport associé.
 */
 public function complete(
        EvaluationSession $session,
        ReportGenerationService $reportService,
        QuestionFlowService $flow
    ) {
    $account = auth()->user();

    // 1️⃣ — Sécurité : la session appartient-elle bien à l'utilisateur ?
    if ($session->account_id !== ($account->id ?? null)) {
        return response()->json([
            'message' => "Accès interdit à cette session."
        ], 403);
    }

    // 2️⃣ — Idempotence : si déjà complétée, retourne le rapport (ou tente de le générer si absent)
    if ($session->status === 'completed') {
        $report = $session->report;
        if (!$report) {
            // Rattrapage : génère le rapport si session complétée mais rapport manquant
            try {
                $report = $reportService->generate($session);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => "Session complétée mais erreur lors de la génération du rapport : " . $e->getMessage()
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

    // 3️⃣ — Transaction pour la complétion et la génération du rapport
    \DB::beginTransaction();
    try {
        $session->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Génère le rapport (normal)
        $report = $reportService->generate($session);
        if (!$report) {
            throw new \Exception("Erreur lors de la génération du rapport.");
        }

        // Détail du score si besoin
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
            'message' => "Erreur lors de la complétion de la session : " . $e->getMessage()
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
