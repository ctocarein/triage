<?php

namespace App\Domains\Evaluation\Controllers\Admin;

use App\Domains\Evaluation\DTOs\Admin\QuestionGroupRequest;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class QuestionGroupController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/question-groups",
     *     operationId="adminQuestionGroupsIndex",
     *     summary="Lister les groupes de questions.",
     *     tags={"Admin - Question Groups"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="q",            in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="inactive_only", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Liste des groupes"),
     *     @OA\Response(response=401, description="Non authentifie"),
     *     @OA\Response(response=403, description="Acces refuse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = QuestionGroup::query()->withCount('questions');

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

        $groups = $query->orderBy('order')->get();

        return response()->json($groups);
    }

    /**
     * @OA\Get(
     *     path="/admin/question-groups/{id}",
     *     operationId="adminQuestionGroupsShow",
     *     summary="Detail d'un groupe de questions.",
     *     tags={"Admin - Question Groups"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Groupe avec blocs thematiques"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function show(QuestionGroup $group): JsonResponse
    {
        $group->load('thematicBlocs:id,key,label,is_active,order');
        $group->loadCount('questions');

        return response()->json($group);
    }

    /**
     * @OA\Post(
     *     path="/admin/question-groups",
     *     operationId="adminQuestionGroupsStore",
     *     summary="Creer un groupe de questions.",
     *     tags={"Admin - Question Groups"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"key","label"},
     *         @OA\Property(property="key",        type="string"),
     *         @OA\Property(property="label",       type="string"),
     *         @OA\Property(property="description", type="string", nullable=true),
     *         @OA\Property(property="order",       type="integer"),
     *         @OA\Property(property="parent_key",  type="string", nullable=true),
     *         @OA\Property(property="is_active",   type="boolean")
     *     )),
     *     @OA\Response(response=201, description="Groupe cree"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function store(QuestionGroupRequest $request): JsonResponse
    {
        $group = QuestionGroup::create($request->validated());

        return response()->json($group, 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/question-groups/{id}",
     *     operationId="adminQuestionGroupsUpdate",
     *     summary="Mettre a jour un groupe de questions.",
     *     tags={"Admin - Question Groups"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/QuestionTemplateWriteRequest")),
     *     @OA\Response(response=200, description="Groupe mis a jour"),
     *     @OA\Response(response=404, description="Non trouve"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function update(QuestionGroupRequest $request, QuestionGroup $group): JsonResponse
    {
        $group->update($request->validated());

        return response()->json($group->fresh());
    }

    /**
     * @OA\Delete(
     *     path="/admin/question-groups/{id}",
     *     operationId="adminQuestionGroupsDestroy",
     *     summary="Supprimer un groupe de questions.",
     *     tags={"Admin - Question Groups"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Groupe supprime"),
     *     @OA\Response(response=409, description="Groupe contient des questions ou des blocs"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function destroy(QuestionGroup $group): JsonResponse
    {
        if ($group->questions()->exists()) {
            return response()->json([
                'error' => 'Ce groupe contient des questions et ne peut pas etre supprime.',
            ], 409);
        }

        if ($group->thematicBlocs()->exists()) {
            return response()->json([
                'error' => 'Ce groupe est associe a des blocs thematiques et ne peut pas etre supprime.',
            ], 409);
        }

        $group->delete();

        return response()->json(['message' => 'Groupe supprime.']);
    }

    /**
     * @OA\Patch(
     *     path="/admin/question-groups/{id}/toggle",
     *     operationId="adminQuestionGroupsToggle",
     *     summary="Activer / desactiver un groupe de questions.",
     *     tags={"Admin - Question Groups"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Statut mis a jour", @OA\JsonContent(@OA\Property(property="id", type="integer"), @OA\Property(property="key", type="string"), @OA\Property(property="is_active", type="boolean")))
     * )
     */
    public function toggle(QuestionGroup $group): JsonResponse
    {
        $group->update(['is_active' => ! $group->is_active]);

        return response()->json([
            'id'        => $group->id,
            'key'       => $group->key,
            'is_active' => $group->is_active,
        ]);
    }
}
