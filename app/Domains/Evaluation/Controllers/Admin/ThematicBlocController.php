<?php

namespace App\Domains\Evaluation\Controllers\Admin;

use App\Domains\Evaluation\DTOs\Admin\ThematicBlocRequest;
use App\Domains\Evaluation\Models\ThematicBloc;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class ThematicBlocController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/thematic-blocs",
     *     operationId="adminThematicBlocsIndex",
     *     summary="Lister les blocs thematiques.",
     *     tags={"Admin - Thematic Blocs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="question_group_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="q",                 in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="inactive_only",      in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Liste des blocs"),
     *     @OA\Response(response=401, description="Non authentifie"),
     *     @OA\Response(response=403, description="Acces refuse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = ThematicBloc::query()->with('questionGroup:id,key,label');

        if ($request->filled('question_group_id')) {
            $query->where('question_group_id', $request->input('question_group_id'));
        }

        if ($request->boolean('inactive_only')) {
            $query->where('is_active', false);
        }

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(fn ($q) =>
                $q->where('key', 'like', "%{$search}%")
                  ->orWhere('label', 'like', "%{$search}%")
            );
        }

        $blocs = $query->orderBy('order')->get();

        return response()->json($blocs);
    }

    /**
     * @OA\Get(
     *     path="/admin/thematic-blocs/{id}",
     *     operationId="adminThematicBlocsShow",
     *     summary="Detail d'un bloc thematique.",
     *     tags={"Admin - Thematic Blocs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Bloc avec groupe"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function show(ThematicBloc $bloc): JsonResponse
    {
        $bloc->load('questionGroup:id,key,label');

        return response()->json($bloc);
    }

    /**
     * @OA\Post(
     *     path="/admin/thematic-blocs",
     *     operationId="adminThematicBlocsStore",
     *     summary="Creer un bloc thematique.",
     *     tags={"Admin - Thematic Blocs"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"key","label","question_group_id"},
     *         @OA\Property(property="key",               type="string"),
     *         @OA\Property(property="label",             type="string"),
     *         @OA\Property(property="description",       type="string", nullable=true),
     *         @OA\Property(property="order",             type="integer"),
     *         @OA\Property(property="question_group_id", type="integer"),
     *         @OA\Property(property="is_active",         type="boolean")
     *     )),
     *     @OA\Response(response=201, description="Bloc cree"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function store(ThematicBlocRequest $request): JsonResponse
    {
        $bloc = ThematicBloc::create($request->validated());

        return response()->json($bloc->load('questionGroup:id,key,label'), 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/thematic-blocs/{id}",
     *     operationId="adminThematicBlocsUpdate",
     *     summary="Mettre a jour un bloc thematique.",
     *     tags={"Admin - Thematic Blocs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/QuestionTemplateWriteRequest")),
     *     @OA\Response(response=200, description="Bloc mis a jour"),
     *     @OA\Response(response=404, description="Non trouve"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function update(ThematicBlocRequest $request, ThematicBloc $bloc): JsonResponse
    {
        $bloc->update($request->validated());

        return response()->json($bloc->fresh('questionGroup'));
    }

    /**
     * @OA\Delete(
     *     path="/admin/thematic-blocs/{id}",
     *     operationId="adminThematicBlocsDestroy",
     *     summary="Supprimer un bloc thematique.",
     *     tags={"Admin - Thematic Blocs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Bloc supprime"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function destroy(ThematicBloc $bloc): JsonResponse
    {
        $bloc->delete();

        return response()->json(['message' => 'Bloc thematique supprime.']);
    }

    /**
     * @OA\Patch(
     *     path="/admin/thematic-blocs/{id}/toggle",
     *     operationId="adminThematicBlocsToggle",
     *     summary="Activer / desactiver un bloc thematique.",
     *     tags={"Admin - Thematic Blocs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Statut mis a jour", @OA\JsonContent(@OA\Property(property="id", type="integer"), @OA\Property(property="key", type="string"), @OA\Property(property="is_active", type="boolean")))
     * )
     */
    public function toggle(ThematicBloc $bloc): JsonResponse
    {
        $bloc->update(['is_active' => ! $bloc->is_active]);

        return response()->json([
            'id'        => $bloc->id,
            'key'       => $bloc->key,
            'is_active' => $bloc->is_active,
        ]);
    }
}
