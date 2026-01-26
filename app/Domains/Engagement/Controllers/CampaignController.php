<?php

namespace App\Domains\Engagement\Controllers;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Illuminate\Http\JsonResponse;
use App\Domains\Engagement\Models\Campaign;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Http\Controllers\Controller;
use App\Domains\Engagement\Services\Campaign\CampaignService;
use App\Domains\Engagement\DTOs\Campaign\CampaignIndexRequest;
use App\Domains\Engagement\DTOs\Campaign\CampaignStoreRequest;
use App\Domains\Engagement\DTOs\Campaign\CampaignUpdateRequest;
use App\Domains\Engagement\Resources\Campaign\CampaignResource;
use App\Domains\Engagement\Resources\Campaign\CampaignReportResource;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $service) {}

    /**
     * @OA\Get(
     *     path="/campaigns",
     *     operationId="campaignsIndex",
     *     summary="List campaigns",
     *     tags={"Campaigns"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", description="Filter by status", @OA\Schema(type="string", enum={"draft","ongoing","completed","archived"})),
     *     @OA\Parameter(name="objective", in="query", description="Filter by objective", @OA\Schema(type="string", enum={"sensibilisation","depistage","suivi_personnel","rse","medical"})),
     *     @OA\Parameter(name="organization_id", in="query", description="Organization identifier (platform admin only)", @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="with_qr_counts", in="query", description="Include QR code counts per status", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page (1-100)", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="Paginated list of campaigns", @OA\JsonContent(ref="#/components/schemas/CampaignCollection")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function index(CampaignIndexRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'You are not allowed to list campaigns.');
        }

        $campaigns = $this->service->listForIdentity($actor, $request->validated());

        return CampaignResource::collection($campaigns)->response();
    }

    /**
     * @OA\Post(
     *     path="/campaigns",
     *     operationId="campaignsStore",
     *     summary="Create a campaign",
     *     tags={"Campaigns"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CampaignStoreRequest")),
     *     @OA\Response(response=201, description="Campaign created", @OA\JsonContent(ref="#/components/schemas/CampaignResourceWithMessage")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(CampaignStoreRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can create campaigns.');
        }

        $campaign = $this->service->create($actor, $request->validated());

        return (new CampaignResource($campaign))
            ->additional(['message' => 'Campaign created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *     path="/campaigns/{campaign}",
     *     operationId="campaignsShow",
     *     summary="Show a campaign",
     *     tags={"Campaigns"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="campaign", in="path", required=true, description="Campaign identifier", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Campaign details", @OA\JsonContent(ref="#/components/schemas/Campaign")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=404, description="Campaign not found", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeAccess($actor, $campaign);

        $campaign->load([
            'qrCodes' => fn($query) => $query->with(['campaign'])->orderByDesc('created_at'),
        ]);

        return (new CampaignResource($campaign))->response();
    }

    /**
     * @OA\Put(
     *     path="/campaigns/{campaign}",
     *     operationId="campaignsUpdate",
     *     summary="Update a campaign",
     *     tags={"Campaigns"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="campaign", in="path", required=true, description="Campaign identifier", @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CampaignUpdateRequest")),
     *     @OA\Response(response=200, description="Campaign updated", @OA\JsonContent(ref="#/components/schemas/CampaignResourceWithMessage")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=404, description="Campaign not found", @OA\JsonContent(ref="#/components/schemas/ApiMessage")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(CampaignUpdateRequest $request, Campaign $campaign): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can update campaigns.');
        }

        $this->authorizeAccess($actor, $campaign);

        $campaign = $this->service->update($actor, $campaign, $request->validated());

        return (new CampaignResource($campaign))
            ->additional(['message' => 'Campaign updated successfully.'])
            ->response();
    }

    /**
     * @OA\Post(
     *     path="/campaigns/{campaign}/archive",
     *     operationId="campaignsArchive",
     *     summary="Archive a campaign",
     *     tags={"Campaigns"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="campaign", in="path", required=true, description="Campaign identifier", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Campaign archived", @OA\JsonContent(ref="#/components/schemas/CampaignResourceWithMessage")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=404, description="Campaign not found", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function archive(Request $request, Campaign $campaign): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can archive campaigns.');
        }

        $this->authorizeAccess($actor, $campaign);

        $campaign = $this->service->archive($actor, $campaign);

        return (new CampaignResource($campaign))
            ->additional(['message' => 'Campaign archived successfully.'])
            ->response();
    }

    /**
     * @OA\Get(
     *     path="/campaigns/{campaign}/report",
     *     operationId="campaignsReport",
     *     summary="Retrieve campaign report",
     *     tags={"Campaigns"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="campaign", in="path", required=true, description="Campaign identifier", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Campaign analytics report", @OA\JsonContent(ref="#/components/schemas/CampaignReport")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=404, description="Campaign not found", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function report(Request $request, Campaign $campaign): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeAccess($actor, $campaign);

        $report = $this->service->getReport($campaign);

        return (new CampaignReportResource($report))->response();
    }

    private function authorizeAccess(mixed $actor, Campaign $campaign): void
    {
        if (! $actor instanceof Identity) {
            abort(403, 'You are not allowed to access this campaign.');
        }

        if ($actor->hasMembershipRole('PlatformAdmin')) {
            return;
        }

        if ($actor->hasMembershipRole('OrgManager', $campaign->organization_id)) {
            return;
        }

        abort(403, 'You are not allowed to access this campaign.');
    }
}
