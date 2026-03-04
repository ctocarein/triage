<?php

namespace App\Domains\Evaluation\Controllers\Admin;

use App\Domains\Evaluation\DTOs\Admin\QuestionTemplateRequest;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\CatalogConsistencyChecker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function store(QuestionTemplateRequest $request, CatalogConsistencyChecker $checker): JsonResponse
    {
        $question = DynamicQuestionTemplate::create($request->validated());

        $warnings = $checker->check();

        return response()->json([
            'question' => $question,
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
        CatalogConsistencyChecker $checker
    ): JsonResponse {
        $question->update($request->validated());

        $warnings = $checker->check();

        return response()->json([
            'question' => $question->fresh('group'),
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
     *     @OA\Response(response=200, description="Question supprimee"),
     *     @OA\Response(response=409, description="Question referencee dans des reponses existantes"),
     *     @OA\Response(response=404, description="Non trouvee")
     * )
     */
    public function destroy(DynamicQuestionTemplate $question): JsonResponse
    {
        if ($question->answers()->exists()) {
            return response()->json([
                'error' => 'Cette question est referencee dans des reponses existantes et ne peut pas etre supprimee.',
            ], 409);
        }

        $question->delete();

        return response()->json(['message' => 'Question supprimee.']);
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
}
