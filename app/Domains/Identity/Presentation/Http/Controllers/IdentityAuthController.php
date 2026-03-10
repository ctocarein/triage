<?php

namespace App\Domains\Identity\Presentation\Http\Controllers;

use App\Domains\Identity\Application\Services\IdentityAuthService;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Identity\Presentation\Http\Requests\LoginIdentityRequest;
use App\Domains\Identity\Presentation\Http\Requests\PhoneAuthRequest;
use App\Domains\Identity\Presentation\Http\Requests\RegisterIdentityRequest;
use App\Domains\Identity\Presentation\Http\Requests\SetCredentialSecretRequest;
use App\Domains\Identity\Presentation\Http\Requests\UnlockSecurityGateRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class IdentityAuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/identity/phone",
     *     operationId="identityPhoneAuth",
     *     summary="Login or create an identity via phone number (loginOrCreate)",
     *     tags={"Identity"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string", example="+2250709584445"),
     *             @OA\Property(property="device_fingerprint", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Auth result with token and security gate status")
     * )
     */
    public function phoneAuth(PhoneAuthRequest $request, IdentityAuthService $service): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'credential_type' => 'phone',
            'identifier'      => $request->validated()['phone'],
        ]);

        $payload = $service->login($data);

        return response()->json($payload);
    }

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
     * @OA\Patch(
     *     path="/identity/secret",
     *     operationId="identitySetSecret",
     *     summary="Set a phone credential secret",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/IdentitySetSecretRequest")
     *     ),
     *     @OA\Response(response=200, description="Secret set", @OA\JsonContent(ref="#/components/schemas/IdentitySetSecretResponse")),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function setSecret(SetCredentialSecretRequest $request, IdentityAuthService $service): JsonResponse
    {
        $identity = $request->user();
        if (! $identity instanceof Identity) {
            abort(403, 'Forbidden.');
        }

        $credential = $service->setPhoneSecret($identity, (string) $request->validated()['secret']);

        return response()->json(['credential' => $credential]);
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

    /**
     * @OA\Post(
     *     path="/identity/unlock",
     *     operationId="identityUnlock",
     *     summary="Unlock the security gate using pin/agent/biometric",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/IdentityUnlockRequest")
     *     ),
     *     @OA\Response(response=200, description="Gate unlocked", @OA\JsonContent(ref="#/components/schemas/IdentityUnlockResponse")),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function unlock(UnlockSecurityGateRequest $request, IdentityAuthService $service): JsonResponse
    {
        $identity = $request->user();
        if (! $identity instanceof Identity) {
            abort(403, 'Forbidden.');
        }

        $payload = $service->unlockSecurityGate($identity, $request->validated());

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/identity/panic-lock",
     *     operationId="identityPanicLock",
     *     summary="Panic lock the security gate and revoke devices",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Gate locked", @OA\JsonContent(ref="#/components/schemas/IdentityPanicLockResponse")),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function panicLock(Request $request, IdentityAuthService $service): JsonResponse
    {
        $identity = $request->user();
        if (! $identity instanceof Identity) {
            abort(403, 'Forbidden.');
        }

        $payload = $service->panicLock($identity);

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/identity/security/status",
     *     operationId="identitySecurityStatus",
     *     summary="Get the current security gate status",
     *     tags={"Identity"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="device_fingerprint",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Security status", @OA\JsonContent(ref="#/components/schemas/IdentitySecurityStatusResponse")),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function securityStatus(Request $request, IdentityAuthService $service): JsonResponse
    {
        $identity = $request->user();
        if (! $identity instanceof Identity) {
            abort(403, 'Forbidden.');
        }

        $fingerprint = $request->query('device_fingerprint');
        $payload = $service->getSecurityStatus($identity, is_string($fingerprint) ? $fingerprint : null);

        return response()->json($payload);
    }
}
