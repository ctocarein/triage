<?php

namespace App\Domains\Evaluation\Controllers\Admin;

use App\Domains\Evaluation\DTOs\Admin\QuestionTemplateRequest;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\Catalog\CatalogConsistencyChecker;
use App\Domains\Evaluation\Services\Catalog\ConditionContractValidator;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

class QuestionTemplateController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/questions",
     *     operationId="adminQuestionsIndex",
     *     summary="Lister les questions du catalogue.",
     *     tags={"Admin - Questions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="bloc_key",    in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="type",        in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="answer_target",in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="inactive_only",in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="q",           in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page",    in="query", @OA\Schema(type="integer", default=50)),
     *     @OA\Response(response=200, description="Liste paginee", @OA\JsonContent(ref="#/components/schemas/PaginationMeta")),
     *     @OA\Response(response=401, description="Non authentifie"),
     *     @OA\Response(response=403, description="Acces refuse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = DynamicQuestionTemplate::query()
            ->with('group:id,key,label');

        if ($request->filled('bloc_key')) {
            $query->where('bloc_key', $request->input('bloc_key'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('answer_target')) {
            $query->where('answer_target', $request->input('answer_target'));
        }

        if ($request->boolean('inactive_only')) {
            $query->where('is_active', false);
        }

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(fn ($q) =>
                $q->where('key', 'like', "%{$search}%")
                  ->orWhere('text', 'like', "%{$search}%")
            );
        }

        $questions = $query
            ->orderBy('bloc_key')
            ->orderBy('order')
            ->paginate($request->integer('per_page', 50));

        return response()->json($questions);
    }

    /**
     * @OA\Get(
     *     path="/admin/questions/{id}",
     *     operationId="adminQuestionsShow",
     *     summary="Detail d'une question.",
     *     tags={"Admin - Questions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Question", @OA\JsonContent(ref="#/components/schemas/EvaluationQuestion")),
     *     @OA\Response(response=404, description="Non trouvee")
     * )
     */
    public function show(DynamicQuestionTemplate $question): JsonResponse
    {
        $question->load('group:id,key,label');

        return response()->json($question);
    }

    /**
     * @OA\Post(
     *     path="/admin/questions",
     *     operationId="adminQuestionsStore",
     *     summary="Creer une question dans le catalogue.",
     *     tags={"Admin - Questions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/QuestionTemplateWriteRequest")),
     *     @OA\Response(response=201, description="Question creee", @OA\JsonContent(ref="#/components/schemas/EvaluationQuestion")),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function store(
        QuestionTemplateRequest $request,
        CatalogConsistencyChecker $checker,
        QuestionTemplateContractValidator $contractValidator
    ): JsonResponse {
        $data     = $contractValidator->normalize($request->validated());
        $question = DynamicQuestionTemplate::create($data);

        $warnings = $checker->check();

        return response()->json([
            'question'         => $question,
            'catalog_warnings' => $warnings,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/questions/{id}",
     *     operationId="adminQuestionsUpdate",
     *     summary="Mettre a jour une question.",
     *     tags={"Admin - Questions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/QuestionTemplateWriteRequest")),
     *     @OA\Response(response=200, description="Question mise a jour", @OA\JsonContent(ref="#/components/schemas/EvaluationQuestion")),
     *     @OA\Response(response=404, description="Non trouvee"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function update(
        QuestionTemplateRequest $request,
        DynamicQuestionTemplate $question,
        CatalogConsistencyChecker $checker,
        QuestionTemplateContractValidator $contractValidator
    ): JsonResponse {
        $data = $contractValidator->normalize($request->validated());
        $question->update($data);

        $warnings = $checker->check();

        return response()->json([
            'question'         => $question->fresh('group'),
            'catalog_warnings' => $warnings,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/questions/{id}",
     *     operationId="adminQuestionsDestroy",
     *     summary="Supprimer une question.",
     *     tags={"Admin - Questions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Question supprimee (avec avertissements optionnels si referencee dans visible_if)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="warnings",
     *                 nullable=true,
     *                 type="object",
     *                 @OA\Property(property="message", type="string"),
     *                 @OA\Property(property="referenced_by", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Suppression bloquee : reponses existantes ou ciblee dans routing_rules d'autres questions",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string"),
     *             @OA\Property(property="referenced_by", type="array", nullable=true, @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Non trouvee")
     * )
     */
    public function destroy(
        DynamicQuestionTemplate $question,
        ConditionContractValidator $conditionValidator
    ): JsonResponse {
        if ($question->answers()->exists()) {
            return response()->json([
                'error' => 'Cette question est referencee dans des reponses existantes et ne peut pas etre supprimee.',
            ], 409);
        }

        // Bloquer si d'autres questions ciblent cette clé dans routing_rules
        $routingRefs = DynamicQuestionTemplate::where('key', '!=', $question->key)
            ->where('routing_rules', 'like', "%question:{$question->key}%")
            ->pluck('key')
            ->all();

        if ($routingRefs !== []) {
            return response()->json([
                'error'        => 'Cette question est ciblee dans les routing_rules d\'autres questions. Corrigez-les d\'abord.',
                'referenced_by' => $routingRefs,
            ], 409);
        }

        // Avertissement si d'autres questions la référencent dans visible_if
        $visibleIfWarnings = $this->findVisibleIfRefs($question->key, $conditionValidator);

        $question->delete();

        $response = ['message' => 'Question supprimee.'];
        if ($visibleIfWarnings !== []) {
            $response['warnings'] = [
                'message'       => 'Des questions referencent cette cle dans leur visible_if. Verifiez le catalogue.',
                'referenced_by' => $visibleIfWarnings,
            ];
        }

        return response()->json($response);
    }

    /**
     * @OA\Patch(
     *     path="/admin/questions/{id}/toggle",
     *     operationId="adminQuestionsToggle",
     *     summary="Activer / desactiver une question.",
     *     tags={"Admin - Questions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Statut mis a jour", @OA\JsonContent(@OA\Property(property="id", type="integer"), @OA\Property(property="key", type="string"), @OA\Property(property="is_active", type="boolean")))
     * )
     */
    public function toggle(DynamicQuestionTemplate $question): JsonResponse
    {
        $question->update(['is_active' => ! $question->is_active]);

        return response()->json([
            'id'        => $question->id,
            'key'       => $question->key,
            'is_active' => $question->is_active,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/questions/catalog/validate",
     *     operationId="adminQuestionsCatalogValidate",
     *     summary="Verifier la coherence du catalogue de questions.",
     *     tags={"Admin - Questions"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Resultat de la validation", @OA\JsonContent(@OA\Property(property="errors", type="array", @OA\Items(type="string"))))
     * )
     */
    public function validate(Request $request, CatalogConsistencyChecker $checker): JsonResponse
    {
        return response()->json([
            'errors' => $checker->check(),
        ]);
    }

    /**
     * Toutes les références à une question dans le catalogue.
     * GET /admin/questions/{question}/references
     *
     * Retourne :
     *   - routing_rules : questions qui ciblent cette clé dans next (question:key ou queue:key)
     *   - visible_if    : questions dont la visibilité dépend de cette clé
     */
    public function references(
        DynamicQuestionTemplate $question,
        ConditionContractValidator $conditionValidator
    ): JsonResponse {
        $key = $question->key;

        $routingRefs = DynamicQuestionTemplate::where('key', '!=', $key)
            ->where(fn ($q) =>
                $q->where('routing_rules', 'like', "%question:{$key}%")
                  ->orWhere('routing_rules', 'like', "%queue:%{$key}%")
            )
            ->pluck('key');

        $visibleIfRefs = $this->findVisibleIfRefs($key, $conditionValidator);

        return response()->json([
            'key'           => $key,
            'routing_rules' => $routingRefs,
            'visible_if'    => $visibleIfRefs,
            'total'         => $routingRefs->count() + count($visibleIfRefs),
        ]);
    }

    /**
     * Activer / désactiver plusieurs questions en lot.
     * PATCH /admin/questions/bulk-toggle
     * Body: { "ids": [1, 2, 3], "active": true }
     */
    public function bulkToggle(Request $request): JsonResponse
    {
        $request->validate([
            'ids'        => ['required', 'array', 'min:1'],
            'ids.*'      => ['integer', 'exists:dynamic_question_templates,id'],
            'active'     => ['required', 'boolean'],
        ]);

        $count = DynamicQuestionTemplate::whereIn('id', $request->input('ids'))
            ->update(['is_active' => $request->boolean('active')]);

        return response()->json([
            'updated' => $count,
            'active'  => $request->boolean('active'),
        ]);
    }

    /**
     * Réordonner les questions d'un bloc (drag & drop).
     * PATCH /admin/questions/reorder
     * Body: { "bloc_key": "symptom_breast", "order": [{"id": 5, "order": 1}, ...] }
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'bloc_key'      => ['required', 'string'],
            'order'         => ['required', 'array', 'min:1'],
            'order.*.id'    => ['required', 'integer', 'exists:dynamic_question_templates,id'],
            'order.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $blocKey = $request->input('bloc_key');

        DB::transaction(function () use ($request, $blocKey) {
            foreach ($request->input('order') as $item) {
                DynamicQuestionTemplate::where('id', $item['id'])
                    ->where('bloc_key', $blocKey)
                    ->update(['order' => $item['order']]);
            }
        });

        return response()->json(
            DynamicQuestionTemplate::where('bloc_key', $blocKey)
                ->orderBy('order')
                ->get(['id', 'key', 'text', 'order', 'is_active'])
        );
    }

    /**
     * Retourne les clés des questions qui référencent $targetKey dans leur visible_if.
     *
     * @return array<int, string>
     */
    private function findVisibleIfRefs(string $targetKey, ConditionContractValidator $validator): array
    {
        // Chargement ciblé : seulement les questions dont visible_if contient la clé
        $candidates = DynamicQuestionTemplate::where('key', '!=', $targetKey)
            ->whereNotNull('visible_if')
            ->where('visible_if', 'like', "%{$targetKey}%")
            ->get(['key', 'visible_if']);

        $matches = [];
        foreach ($candidates as $candidate) {
            $decoded = is_string($candidate->visible_if)
                ? json_decode($candidate->visible_if, true)
                : $candidate->visible_if;

            if (!is_array($decoded)) {
                continue;
            }

            if (in_array($targetKey, $validator->extractQuestionRefs($decoded), true)) {
                $matches[] = $candidate->key;
            }
        }

        return $matches;
    }
}
