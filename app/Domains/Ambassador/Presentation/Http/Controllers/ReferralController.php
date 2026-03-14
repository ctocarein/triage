<?php

namespace App\Domains\Ambassador\Presentation\Http\Controllers;

use App\Domains\Ambassador\Application\Handlers\GenerateReferralHandler;
use App\Domains\Ambassador\Application\Handlers\RevokeReferralHandler;
use App\Domains\Ambassador\Application\Queries\GetAmbassadorProfileQuery;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral;
use App\Domains\Ambassador\Presentation\Http\Requests\GenerateReferralLinkRequest;
use App\Domains\Ambassador\Presentation\Http\Resources\ReferralResource;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenApi\Annotations as OA;

class ReferralController extends Controller
{
    public function __construct(
        private readonly GenerateReferralHandler $generateHandler,
        private readonly RevokeReferralHandler $revokeHandler,
        private readonly GetAmbassadorProfileQuery $profileQuery,
    ) {}

    /**
     * @OA\Get(
     *     path="/ambassador/referrals",
     *     operationId="ambassadorReferralsIndex",
     *     summary="List ambassador referrals",
     *     tags={"Ambassador"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Referral list", @OA\JsonContent(ref="#/components/schemas/AmbassadorReferralCollection")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError"))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can list referrals.');
        }

        $ambassador = $this->profileQuery->handle($actor);
        $referrals = $ambassador->referrals()->latest()->get();

        return ReferralResource::collection($referrals)->response();
    }

    /**
     * @OA\Post(
     *     path="/ambassador/referrals",
     *     operationId="ambassadorReferralsStore",
     *     summary="Generate or reuse a referral link",
     *     tags={"Ambassador"},
     *     security={{"sanctum":{}}},
     *     description="A 'started' usage event is recorded automatically when the link is opened.",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/GenerateReferralLinkRequest")),
     *     @OA\Response(response=201, description="Referral link generated", @OA\JsonContent(ref="#/components/schemas/AmbassadorReferralLinkResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
     *     @OA\Response(response=429, description="Rate limit", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function store(GenerateReferralLinkRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can generate referrals.');
        }

        $result = $this->generateHandler->handleSmartLink($actor, $request->validated());
        $referral = $result['referral'];
        $reused = $result['reused'];
        $remainingWeekly = $result['remaining_weekly'] ?? null;
        $baseUrl = config('ambassador.referrals.base_url', config('app.url'));
        $url = rtrim((string) $baseUrl, '/').'/r/'.$referral->code;

        $shortUrl = $url;
        try {
            $response = Http::timeout(3)->get('https://tinyurl.com/api-create.php', ['url' => $url]);
            if ($response->successful() && str_starts_with($response->body(), 'https://')) {
                $shortUrl = trim($response->body());
            }
        } catch (\Throwable) {
            // TinyURL indisponible — on garde l'URL originale
        }

        return (new ReferralResource($referral))
            ->additional([
                'message' => $reused ? 'Referral link reused.' : 'Referral link generated.',
                'url' => $shortUrl,
                'reused' => $reused,
                'remaining_weekly' => $remainingWeekly,
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Post(
     *     path="/ambassador/referrals/{referral}/revoke",
     *     operationId="ambassadorReferralsRevoke",
     *     summary="Revoke a referral",
     *     tags={"Ambassador"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="referral", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Referral revoked", @OA\JsonContent(ref="#/components/schemas/AmbassadorReferralResponseWithMessage")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenError")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/ApiMessage"))
     * )
     */
    public function revoke(Request $request, Referral $referral): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403, 'Only identities can revoke referrals.');
        }

        $referral = $this->revokeHandler->handle($actor, $referral);

        return (new ReferralResource($referral))
            ->additional(['message' => 'Referral revoked.'])
            ->response();
    }

}
