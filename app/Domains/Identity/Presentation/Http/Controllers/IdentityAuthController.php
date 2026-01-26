<?php

namespace App\Domains\Identity\Presentation\Http\Controllers;

use App\Domains\Identity\Application\Services\IdentityAuthService;
use App\Domains\Identity\Presentation\Http\Requests\LoginIdentityRequest;
use App\Domains\Identity\Presentation\Http\Requests\RegisterIdentityRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class IdentityAuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/identity/register",
     *     operationId="identityRegister",
     *     summary="Register a new identity with a credential",
     *     tags={"Identity"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/IdentityRegisterRequest")
     *     ),
     *     @OA\Response(response=201, description="Identity created", @OA\JsonContent(ref="#/components/schemas/IdentityRegisterResponse")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(RegisterIdentityRequest $request, IdentityAuthService $service): JsonResponse
    {
        $payload = $service->register($request->validated());

        return response()->json($payload, 201);
    }

    /**
     * @OA\Post(
     *     path="/identity/login",
     *     operationId="identityLogin",
     *     summary="Login using a credential",
     *     tags={"Identity"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/IdentityLoginRequest")
     *     ),
     *     @OA\Response(response=200, description="Login successful", @OA\JsonContent(ref="#/components/schemas/IdentityLoginResponse")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function login(LoginIdentityRequest $request, IdentityAuthService $service): JsonResponse
    {
        $payload = $service->login($request->validated());

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/identity/logout",
     *     operationId="identityLogout",
     *     summary="Logout the current identity session",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Logged out"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
