<?php

namespace App\Domains\Evaluation\Controllers\Admin;

use App\Domains\Evaluation\DTOs\Admin\FactMappingRequest;
use App\Domains\Evaluation\DTOs\Admin\FactRequest;
use App\Domains\Evaluation\Models\Fact;
use App\Domains\Evaluation\Models\FactMapping;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class FactController extends Controller
{
    // ── Fact ───────────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/admin/facts",
     *     operationId="adminFactsIndex",
     *     summary="Lister les faits.",
     *     tags={"Admin - Facts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="q",            in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="inactive_only", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Liste des faits avec comptage mappings"),
     *     @OA\Response(response=401, description="Non authentifie"),
     *     @OA\Response(response=403, description="Acces refuse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Fact::query()->withCount('mappings');

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(fn ($q) =>
                $q->where('key', 'like', "%{$search}%")
                  ->orWhere('label', 'like', "%{$search}%")
            );
        }

        if ($request->boolean('inactive_only')) {
            $query->where('is_active', false);
        }

        return response()->json($query->orderBy('key')->get());
    }

    /**
     * @OA\Get(
     *     path="/admin/facts/{fact}",
     *     operationId="adminFactsShow",
     *     summary="Detail d'un fait avec ses mappings.",
     *     tags={"Admin - Facts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="fact", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Fait avec mappings et comptage signaux/flags"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function show(Fact $fact): JsonResponse
    {
        $fact->load('mappings');
        $fact->loadCount(['riskSignals', 'riskFlags']);

        return response()->json($fact);
    }

    /**
     * @OA\Post(
     *     path="/admin/facts",
     *     operationId="adminFactsStore",
     *     summary="Creer un fait.",
     *     tags={"Admin - Facts"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"key","label"},
     *         @OA\Property(property="key",       type="string"),
     *         @OA\Property(property="label",     type="string"),
     *         @OA\Property(property="is_active", type="boolean")
     *     )),
     *     @OA\Response(response=201, description="Fait cree"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function store(FactRequest $request): JsonResponse
    {
        $fact = Fact::create($request->validated());

        return response()->json($fact, 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/facts/{fact}",
     *     operationId="adminFactsUpdate",
     *     summary="Mettre a jour un fait.",
     *     tags={"Admin - Facts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="fact", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/QuestionTemplateWriteRequest")),
     *     @OA\Response(response=200, description="Fait mis a jour"),
     *     @OA\Response(response=404, description="Non trouve"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function update(FactRequest $request, Fact $fact): JsonResponse
    {
        $fact->update($request->validated());

        return response()->json($fact->fresh());
    }

    /**
     * @OA\Delete(
     *     path="/admin/facts/{fact}",
     *     operationId="adminFactsDestroy",
     *     summary="Supprimer un fait.",
     *     tags={"Admin - Facts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="fact", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Fait supprime"),
     *     @OA\Response(response=409, description="Fait utilise dans des signaux ou flags de risque"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function destroy(Fact $fact): JsonResponse
    {
        if ($fact->riskSignals()->exists() || $fact->riskFlags()->exists()) {
            return response()->json([
                'error' => 'Ce fait est utilise dans des signaux ou flags de risque et ne peut pas etre supprime.',
            ], 409);
        }

        $fact->mappings()->delete();
        $fact->delete();

        return response()->json(['message' => 'Fait supprime.']);
    }

    /**
     * @OA\Patch(
     *     path="/admin/facts/{fact}/toggle",
     *     operationId="adminFactsToggle",
     *     summary="Activer / desactiver un fait.",
     *     tags={"Admin - Facts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="fact", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Statut mis a jour", @OA\JsonContent(@OA\Property(property="id", type="integer"), @OA\Property(property="key", type="string"), @OA\Property(property="is_active", type="boolean")))
     * )
     */
    public function toggle(Fact $fact): JsonResponse
    {
        $fact->update(['is_active' => ! $fact->is_active]);

        return response()->json([
            'id'        => $fact->id,
            'key'       => $fact->key,
            'is_active' => $fact->is_active,
        ]);
    }

    // ── FactMapping ────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/admin/facts/{fact}/mappings",
     *     operationId="adminFactMappingsIndex",
     *     summary="Lister les mappings d'un fait.",
     *     tags={"Admin - Facts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="fact", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Liste des mappings"),
     *     @OA\Response(response=404, description="Fait non trouve")
     * )
     */
    public function mappings(Fact $fact): JsonResponse
    {
        return response()->json(
            $fact->mappings()->orderBy('question_key')->get()
        );
    }

    /**
     * @OA\Post(
     *     path="/admin/facts/{fact}/mappings",
     *     operationId="adminFactMappingsStore",
     *     summary="Ajouter un mapping a un fait.",
     *     tags={"Admin - Facts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="fact", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"question_key","answer_value","operator"},
     *         @OA\Property(property="question_key",  type="string"),
     *         @OA\Property(property="answer_value",  type="array", @OA\Items(type="string")),
     *         @OA\Property(property="operator",      type="string", enum={"=","!=","in","not_in",">=","<=",">","<"}),
     *         @OA\Property(property="is_active",     type="boolean")
     *     )),
     *     @OA\Response(response=201, description="Mapping cree"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function storeMapping(FactMappingRequest $request, Fact $fact): JsonResponse
    {
        $mapping = $fact->mappings()->create(
            array_merge($request->validated(), ['fact_key' => $fact->key])
        );

        return response()->json($mapping, 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/facts/{fact}/mappings/{mapping}",
     *     operationId="adminFactMappingsUpdate",
     *     summary="Mettre a jour un mapping.",
     *     tags={"Admin - Facts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="fact",    in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="mapping", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/QuestionTemplateWriteRequest")),
     *     @OA\Response(response=200, description="Mapping mis a jour"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function updateMapping(FactMappingRequest $request, Fact $fact, FactMapping $mapping): JsonResponse
    {
        abort_if($mapping->fact_key !== $fact->key, 404);

        $mapping->update($request->validated());

        return response()->json($mapping->fresh());
    }

    /**
     * @OA\Delete(
     *     path="/admin/facts/{fact}/mappings/{mapping}",
     *     operationId="adminFactMappingsDestroy",
     *     summary="Supprimer un mapping.",
     *     tags={"Admin - Facts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="fact",    in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="mapping", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Mapping supprime"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function destroyMapping(Fact $fact, FactMapping $mapping): JsonResponse
    {
        abort_if($mapping->fact_key !== $fact->key, 404);

        $mapping->delete();

        return response()->json(['message' => 'Mapping supprime.']);
    }
}
