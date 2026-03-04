<?php

namespace App\Domains\Evaluation\Controllers\Admin;

use App\Domains\Evaluation\DTOs\Admin\RiskFlagRequest;
use App\Domains\Evaluation\DTOs\Admin\RiskModelRequest;
use App\Domains\Evaluation\DTOs\Admin\RiskSignalRequest;
use App\Domains\Evaluation\Models\RiskFlag;
use App\Domains\Evaluation\Models\RiskModel;
use App\Domains\Evaluation\Models\RiskSignal;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class RiskModelController extends Controller
{
    // ── RiskModel ──────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/admin/risk-models",
     *     operationId="adminRiskModelsIndex",
     *     summary="Lister les modeles de risque.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Liste des modeles avec comptage signaux/flags"),
     *     @OA\Response(response=401, description="Non authentifie"),
     *     @OA\Response(response=403, description="Acces refuse")
     * )
     */
    public function index(): JsonResponse
    {
        $models = RiskModel::query()
            ->withCount(['signals', 'flags'])
            ->orderBy('key')
            ->get();

        return response()->json($models);
    }

    /**
     * @OA\Get(
     *     path="/admin/risk-models/{risk_model}",
     *     operationId="adminRiskModelsShow",
     *     summary="Detail d'un modele de risque avec ses signaux et flags.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="risk_model", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Modele avec signaux et flags"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function show(RiskModel $risk_model): JsonResponse
    {
        $risk_model->load([
            'signals.fact:key,label',
            'signals.modelVersion:id,name,version',
            'flags.fact:key,label',
            'flags.modelVersion:id,name,version',
        ]);

        return response()->json($risk_model);
    }

    /**
     * @OA\Post(
     *     path="/admin/risk-models",
     *     operationId="adminRiskModelsStore",
     *     summary="Creer un modele de risque.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"key","label"},
     *         @OA\Property(property="key",         type="string"),
     *         @OA\Property(property="label",        type="string"),
     *         @OA\Property(property="description",  type="string", nullable=true),
     *         @OA\Property(property="is_active",    type="boolean")
     *     )),
     *     @OA\Response(response=201, description="Modele cree"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function store(RiskModelRequest $request): JsonResponse
    {
        $model = RiskModel::create($request->validated());

        return response()->json($model, 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/risk-models/{risk_model}",
     *     operationId="adminRiskModelsUpdate",
     *     summary="Mettre a jour un modele de risque.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="risk_model", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/QuestionTemplateWriteRequest")),
     *     @OA\Response(response=200, description="Modele mis a jour"),
     *     @OA\Response(response=404, description="Non trouve"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function update(RiskModelRequest $request, RiskModel $risk_model): JsonResponse
    {
        $risk_model->update($request->validated());

        return response()->json($risk_model->fresh());
    }

    /**
     * @OA\Delete(
     *     path="/admin/risk-models/{risk_model}",
     *     operationId="adminRiskModelsDestroy",
     *     summary="Supprimer un modele de risque.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="risk_model", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Modele supprime"),
     *     @OA\Response(response=409, description="Le modele contient des signaux ou flags"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function destroy(RiskModel $risk_model): JsonResponse
    {
        if ($risk_model->signals()->exists() || $risk_model->flags()->exists()) {
            return response()->json([
                'error' => 'Ce modele contient des signaux ou des flags. Supprimez-les d\'abord.',
            ], 409);
        }

        $risk_model->delete();

        return response()->json(['message' => 'Modele de risque supprime.']);
    }

    /**
     * @OA\Patch(
     *     path="/admin/risk-models/{risk_model}/toggle",
     *     operationId="adminRiskModelsToggle",
     *     summary="Activer / desactiver un modele de risque.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="risk_model", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Statut mis a jour", @OA\JsonContent(@OA\Property(property="id", type="integer"), @OA\Property(property="key", type="string"), @OA\Property(property="is_active", type="boolean")))
     * )
     */
    public function toggle(RiskModel $risk_model): JsonResponse
    {
        $risk_model->update(['is_active' => ! $risk_model->is_active]);

        return response()->json([
            'id'        => $risk_model->id,
            'key'       => $risk_model->key,
            'is_active' => $risk_model->is_active,
        ]);
    }

    // ── RiskSignal ─────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/admin/risk-models/{risk_model}/signals",
     *     operationId="adminRiskSignalsIndex",
     *     summary="Lister les signaux d'un modele de risque.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="risk_model", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Liste des signaux"),
     *     @OA\Response(response=404, description="Modele non trouve")
     * )
     */
    public function signals(RiskModel $risk_model): JsonResponse
    {
        $signals = $risk_model->signals()
            ->with('fact:key,label', 'modelVersion:id,name,version')
            ->orderBy('weight', 'desc')
            ->get();

        return response()->json($signals);
    }

    /**
     * @OA\Post(
     *     path="/admin/risk-models/{risk_model}/signals",
     *     operationId="adminRiskSignalsStore",
     *     summary="Ajouter un signal a un modele de risque.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="risk_model", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"fact_key","weight"},
     *         @OA\Property(property="fact_key",         type="string"),
     *         @OA\Property(property="weight",           type="number", minimum=0, maximum=100),
     *         @OA\Property(property="model_version_id", type="integer", nullable=true),
     *         @OA\Property(property="is_active",        type="boolean")
     *     )),
     *     @OA\Response(response=201, description="Signal cree"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function storeSignal(RiskSignalRequest $request, RiskModel $risk_model): JsonResponse
    {
        $signal = $risk_model->signals()->create($request->validated());
        $signal->load('fact:key,label');

        return response()->json($signal, 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/risk-models/{risk_model}/signals/{signal}",
     *     operationId="adminRiskSignalsUpdate",
     *     summary="Mettre a jour un signal.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="risk_model", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="signal",     in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/QuestionTemplateWriteRequest")),
     *     @OA\Response(response=200, description="Signal mis a jour"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function updateSignal(RiskSignalRequest $request, RiskModel $risk_model, RiskSignal $signal): JsonResponse
    {
        abort_if($signal->risk_model_id !== $risk_model->id, 404);

        $signal->update($request->validated());

        return response()->json($signal->fresh('fact'));
    }

    /**
     * @OA\Delete(
     *     path="/admin/risk-models/{risk_model}/signals/{signal}",
     *     operationId="adminRiskSignalsDestroy",
     *     summary="Supprimer un signal.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="risk_model", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="signal",     in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Signal supprime"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function destroySignal(RiskModel $risk_model, RiskSignal $signal): JsonResponse
    {
        abort_if($signal->risk_model_id !== $risk_model->id, 404);

        $signal->delete();

        return response()->json(['message' => 'Signal supprime.']);
    }

    // ── RiskFlag ───────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/admin/risk-models/{risk_model}/flags",
     *     operationId="adminRiskFlagsIndex",
     *     summary="Lister les flags d'un modele de risque.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="risk_model", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Liste des flags"),
     *     @OA\Response(response=404, description="Modele non trouve")
     * )
     */
    public function flags(RiskModel $risk_model): JsonResponse
    {
        $flags = $risk_model->flags()
            ->with('fact:key,label', 'modelVersion:id,name,version')
            ->orderBy('priority', 'desc')
            ->get();

        return response()->json($flags);
    }

    /**
     * @OA\Post(
     *     path="/admin/risk-models/{risk_model}/flags",
     *     operationId="adminRiskFlagsStore",
     *     summary="Ajouter un flag a un modele de risque.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="risk_model", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"fact_key","type","label"},
     *         @OA\Property(property="fact_key",         type="string"),
     *         @OA\Property(property="type",             type="string", enum={"warning","critical","info","exclusion","urgent"}),
     *         @OA\Property(property="label",            type="string"),
     *         @OA\Property(property="description",      type="string", nullable=true),
     *         @OA\Property(property="priority",         type="integer"),
     *         @OA\Property(property="model_version_id", type="integer", nullable=true),
     *         @OA\Property(property="is_active",        type="boolean")
     *     )),
     *     @OA\Response(response=201, description="Flag cree"),
     *     @OA\Response(response=422, description="Validation echouee")
     * )
     */
    public function storeFlag(RiskFlagRequest $request, RiskModel $risk_model): JsonResponse
    {
        $flag = $risk_model->flags()->create($request->validated());
        $flag->load('fact:key,label');

        return response()->json($flag, 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/risk-models/{risk_model}/flags/{flag}",
     *     operationId="adminRiskFlagsUpdate",
     *     summary="Mettre a jour un flag.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="risk_model", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="flag",       in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/QuestionTemplateWriteRequest")),
     *     @OA\Response(response=200, description="Flag mis a jour"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function updateFlag(RiskFlagRequest $request, RiskModel $risk_model, RiskFlag $flag): JsonResponse
    {
        abort_if($flag->risk_model_id !== $risk_model->id, 404);

        $flag->update($request->validated());

        return response()->json($flag->fresh('fact'));
    }

    /**
     * @OA\Delete(
     *     path="/admin/risk-models/{risk_model}/flags/{flag}",
     *     operationId="adminRiskFlagsDestroy",
     *     summary="Supprimer un flag.",
     *     tags={"Admin - Risk Models"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="risk_model", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="flag",       in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Flag supprime"),
     *     @OA\Response(response=404, description="Non trouve")
     * )
     */
    public function destroyFlag(RiskModel $risk_model, RiskFlag $flag): JsonResponse
    {
        abort_if($flag->risk_model_id !== $risk_model->id, 404);

        $flag->delete();

        return response()->json(['message' => 'Flag supprime.']);
    }
}
