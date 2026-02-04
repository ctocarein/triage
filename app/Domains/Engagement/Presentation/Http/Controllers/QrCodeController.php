<?php

namespace App\Domains\Engagement\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Engagement\Presentation\Http\Requests\Qr\QrCodeIndexRequest;
use App\Domains\Engagement\Presentation\Http\Requests\Qr\QrCodeStoreRequest;
use App\Domains\Engagement\Presentation\Http\Requests\Qr\QrCodeUpdateRequest;
use App\Domains\Engagement\Presentation\Http\Resources\Qr\QrCodeResource;
use App\Domains\Engagement\Presentation\Http\Resources\Qr\QrCodeStatsResource;
use App\Domains\Engagement\Infrastructure\Persistence\Models\QrCode;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Engagement\Application\Services\Qr\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Annotations as OA;

class QrCodeController extends Controller
{
    public function __construct(private readonly QrCodeService $service)
    {
    }

    /**
     * @OA\Get(
     *     path="/qr-codes",
     *     operationId="qrCodesIndex",
     *     summary="Lister les QR codes",
     *     tags={"QR Codes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", description="Filtrer par statut", @OA\Schema(type="string", enum={"active","used","expired","revoked"})),
     *     @OA\Parameter(name="type", in="query", description="Filtrer par type", @OA\Schema(type="string", enum={"portal","campaign","evaluation","anonymous"})),
     *     @OA\Parameter(name="campaign_id", in="query", description="Limiter aux QR codes d'une campagne", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="organization_id", in="query", description="Reserve aux administrateurs pour filtrer par organisation", @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="per_page", in="query", description="Nombre d'elements par page (1-100)", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginee des QR codes",
     *         @OA\JsonContent(ref="#/components/schemas/QrCodeCollection")
     *     ),
     *     @OA\Response(response=401, description="Non authentifie", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Acces interdit", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function index(QrCodeIndexRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403);
        }

        $qrCodes = $this->service->listForIdentity($actor, $request->validated());

        return QrCodeResource::collection($qrCodes)->response();
    }

    /**
     * @OA\Post(
     *     path="/qr-codes",
     *     operationId="qrCodesStore",
     *     summary="Creer un ou plusieurs QR codes",
     *     tags={"QR Codes"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/QrCodeStoreRequest")),
     *     @OA\Response(
     *         response=201,
     *         description="QR codes crees",
     *         @OA\JsonContent(ref="#/components/schemas/QrCodeCollectionWithMessage")
     *     ),
     *     @OA\Response(response=401, description="Non authentifie", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Acces interdit", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Erreur de validation", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(QrCodeStoreRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can create QR codes.');
        }

        $codes = $this->service->createForIdentity($actor, $request->validated());

        return QrCodeResource::collection($codes)
            ->additional(['message' => 'QR code(s) created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *     path="/qr-codes/{qrCode}",
     *     operationId="qrCodesShow",
     *     summary="Afficher un QR code",
     *     tags={"QR Codes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="qrCode",
     *         in="path",
     *         required=true,
     *         description="Identifiant du QR code",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="QR code detaille", @OA\JsonContent(ref="#/components/schemas/QrCode")),
     *     @OA\Response(response=401, description="Non authentifie", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Acces interdit", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=404, description="QR code introuvable", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function show(Request $request, QrCode $qrCode): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeView($actor, $qrCode);

        $qrCode->load(['organization:id,name', 'campaign:id,name,status']);

        return (new QrCodeResource($qrCode))->response();
    }

    /**
     * @OA\Put(
     *     path="/qr-codes/{qrCode}",
     *     operationId="qrCodesUpdate",
     *     summary="Mettre a jour un QR code",
     *     tags={"QR Codes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="qrCode",
     *         in="path",
     *         required=true,
     *         description="Identifiant du QR code",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/QrCodeUpdateRequest")),
     *     @OA\Response(response=200, description="QR code mis a jour", @OA\JsonContent(ref="#/components/schemas/QrCodeResourceWithMessage")),
     *     @OA\Response(response=401, description="Non authentifie", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Acces interdit", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Erreur de validation", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(QrCodeUpdateRequest $request, QrCode $qrCode): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can update QR codes.');
        }

        $this->authorizeView($actor, $qrCode);

        $qrCode = $this->service->updateForIdentity($actor, $qrCode, $request->validated());

        return (new QrCodeResource($qrCode))
            ->additional(['message' => 'QR code updated successfully.'])
            ->response();
    }

    /**
     * @OA\Delete(
     *     path="/qr-codes/{qrCode}",
     *     operationId="qrCodesDestroy",
     *     summary="Supprimer un QR code en attente",
     *     tags={"QR Codes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="qrCode",
     *         in="path",
     *         required=true,
     *         description="Identifiant du QR code",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Suppression confirmee", @OA\JsonContent(ref="#/components/schemas/ApiMessage")),
     *     @OA\Response(response=401, description="Non authentifie", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Acces interdit", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="QR code dans un etat non supprimable", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function destroy(Request $request, QrCode $qrCode): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can delete QR codes.');
        }

        $this->authorizeView($actor, $qrCode);

        $this->service->deleteForIdentity($actor, $qrCode);

        return response()->json([
            'message' => 'QR code deleted successfully.',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/qr-codes/{qrCode}/validate",
     *     operationId="qrCodesValidate",
     *     summary="Valider un QR code",
     *     tags={"QR Codes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="qrCode",
     *         in="path",
     *         required=true,
     *         description="Identifiant du QR code",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="QR code valide", @OA\JsonContent(ref="#/components/schemas/QrCodeResourceWithMessage")),
     *     @OA\Response(response=401, description="Non authentifie", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Acces interdit", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function validateQr(Request $request, QrCode $qrCode): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can validate QR codes.');
        }

        $qrCode = $this->service->validate($actor, $qrCode);

        return (new QrCodeResource($qrCode))
            ->additional(['message' => 'QR code validated.'])
            ->response();
    }

    /**
     * @OA\Post(
     *     path="/qr-codes/{qrCode}/revoke",
     *     operationId="qrCodesRevoke",
     *     summary="Revoquer un QR code",
     *     tags={"QR Codes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="qrCode",
     *         in="path",
     *         required=true,
     *         description="Identifiant du QR code",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="QR code revoque", @OA\JsonContent(ref="#/components/schemas/QrCodeResourceWithMessage")),
     *     @OA\Response(response=401, description="Non authentifie", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Acces interdit", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function revoke(Request $request, QrCode $qrCode): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can revoke QR codes.');
        }

        $qrCode = $this->service->revoke($actor, $qrCode);

        return (new QrCodeResource($qrCode))
            ->additional(['message' => 'QR code revoked.'])
            ->response();
    }

    /**
     * @OA\Get(
     *     path="/qr-codes/{qrCode}/stats",
     *     operationId="qrCodesStats",
     *     summary="Consulter les statistiques d'un QR code",
     *     tags={"QR Codes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="qrCode",
     *         in="path",
     *         required=true,
     *         description="Identifiant du QR code",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Statistiques d'usage", @OA\JsonContent(ref="#/components/schemas/QrCodeStats")),
     *     @OA\Response(response=401, description="Non authentifie", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Acces interdit", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function stats(Request $request, QrCode $qrCode): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeView($actor, $qrCode);

        $stats = $this->service->getStats($qrCode);

        return (new QrCodeStatsResource($stats))->response();
    }

    /**
     * @OA\Get(
     *     path="/qr-codes/{qrCode}/download",
     *     operationId="qrCodesDownload",
     *     summary="Telecharger un QR code en PNG",
     *     tags={"QR Codes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="qrCode",
     *         in="path",
     *         required=true,
     *         description="Identifiant du QR code",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Format de sortie (png uniquement)",
     *         @OA\Schema(type="string", default="png", enum={"png"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image PNG du QR code",
     *         @OA\MediaType(
     *             mediaType="image/png",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifie", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Acces interdit", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Format non supporte", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function download(Request $request, QrCode $qrCode): Response
    {
        $actor = $request->user();
        $this->authorizeView($actor, $qrCode);

        $format = $request->query('format', 'png');

        if ($format !== 'png') {
            abort(422, 'Only PNG format is currently supported.');
        }

        $binary = $this->service->generatePng($qrCode);
        $filename = sprintf('qr_%s.%s', $qrCode->code, $format);

        return response($binary, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function authorizeView(mixed $actor, QrCode $qrCode): void
    {
        if (! $actor instanceof Identity) {
            abort(403, 'You are not allowed to access this QR code.');
        }

        if ($actor->hasMembershipRole('PlatformAdmin')) {
            return;
        }

        if ($actor->hasMembershipRole('OrgManager', $qrCode->organization_id)) {
            return;
        }

        abort(403, 'You are not allowed to access this QR code.');
    }
}
