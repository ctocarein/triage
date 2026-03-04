<?php

namespace App\Domains\Evaluation\Controllers\Admin;

use App\Domains\Evaluation\DTOs\Admin\ModelVersionRequest;
use App\Domains\Evaluation\Models\ModelVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class ModelVersionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/model-versions",
     *     operationId="adminModelVersionsIndex",
     *     summary="Lister les versions de modele.",
     *     tags={"Admin - Model Versions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="name", in="query", @OA\Schema(type="string", enum={"catalog","risk_model"})),
     *     @OA\Response(response=200, description="Liste des versions"),
     *     @OA\Response(response=401, description="Non authentifie"),
     *     @OA\Response(response=403, description="Acces refuse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = ModelVersion::query();

        if ($request->filled('name')) {
            $query->where('name', $request->input('name'));
        }

        $versions = $query->orderBy('name')->orderByDesc('released_at')->get();

        return response()->json($versions);
    }

    /**
     * @OA\Get(
     *     path="/admin/model-versions/{model_version}",
     *     operationId="adminModelVersionsShow",
     *     summary="Detail d'une version de modele.",
     *     tags={"Admin - Model Versions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="model_version", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Version"),
     *     @OA\Response(response=404, description="Non trouvee")
     * )
     */
    public function show(ModelVersion $model_version): JsonResponse
    {
        return response()->json($model_version);
    }

    /**
     * @OA\Post(
     *     path="/admin/model-versions",
     *     operationId="adminModelVersionsStore",
     *     summary="Creer une version de modele.",
     *     tags={"Admin - Model Versions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name","version"},
     *         @OA\Property(property="name",        type="string", enum={"catalog","risk_model"}),
     *         @OA\Property(property="version",     type="string", example="1.0.0"),
     *         @OA\Property(property="description", type="string", nullable=true),
     *         @OA\Property(property="checksum",    type="string", nullable=true),
     *         @OA\Property(property="metadata",    type="object", nullable=true),
     *         @OA\Property(property="released_at", type="string", format="date-time", nullable=true)
     *     )),
     *     @OA\Response(response=201, description="Version creee"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function store(ModelVersionRequest $request): JsonResponse
    {
        $version = ModelVersion::create(
            array_merge($request->validated(), ['is_active' => false])
        );

        return response()->json($version, 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/model-versions/{model_version}",
     *     operationId="adminModelVersionsUpdate",
     *     summary="Mettre a jour une version de modele.",
     *     tags={"Admin - Model Versions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="model_version", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/QuestionTemplateWriteRequest")),
     *     @OA\Response(response=200, description="Version mise a jour"),
     *     @OA\Response(response=404, description="Non trouvee"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function update(ModelVersionRequest $request, ModelVersion $model_version): JsonResponse
    {
        $model_version->update($request->validated());

        return response()->json($model_version->fresh());
    }

    /**
     * @OA\Patch(
     *     path="/admin/model-versions/{model_version}/activate",
     *     operationId="adminModelVersionsActivate",
     *     summary="Activer une version de modele (desactive les autres du meme nom).",
     *     tags={"Admin - Model Versions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="model_version", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Version activee", @OA\JsonContent(
     *         @OA\Property(property="id",        type="integer"),
     *         @OA\Property(property="name",      type="string"),
     *         @OA\Property(property="version",   type="string"),
     *         @OA\Property(property="is_active", type="boolean")
     *     )),
     *     @OA\Response(response=404, description="Non trouvee")
     * )
     */
    public function activate(ModelVersion $model_version): JsonResponse
    {
        // Désactive toutes les autres versions du même nom
        ModelVersion::where('name', $model_version->name)
            ->where('id', '!=', $model_version->id)
            ->update(['is_active' => false]);

        $model_version->update(['is_active' => true]);

        return response()->json([
            'id'         => $model_version->id,
            'name'       => $model_version->name,
            'version'    => $model_version->version,
            'is_active'  => true,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/model-versions/{model_version}",
     *     operationId="adminModelVersionsDestroy",
     *     summary="Supprimer une version de modele.",
     *     tags={"Admin - Model Versions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="model_version", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Version supprimee"),
     *     @OA\Response(response=409, description="Impossible de supprimer une version active"),
     *     @OA\Response(response=404, description="Non trouvee")
     * )
     */
    public function destroy(ModelVersion $model_version): JsonResponse
    {
        if ($model_version->is_active) {
            return response()->json([
                'error' => 'Impossible de supprimer une version active. Activez une autre version d\'abord.',
            ], 409);
        }

        $model_version->delete();

        return response()->json(['message' => 'Version supprimee.']);
    }
}
