<?php

namespace App\Http\Middleware;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Identity\Infrastructure\Persistence\Models\IdentitySecurityGate;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityGateMiddleware
{
    public function handle(Request $request, Closure $next): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $identity = $request->user();

        if (! $identity instanceof Identity) {
            return $next($request);
        }

        $gate = IdentitySecurityGate::firstOrCreate([
            'identity_id' => $identity->id,
        ], [
            'state' => 'entry',
        ]);

        $isLocked = ($gate->panic_locked_at !== null)
            || ($gate->locked_until !== null && $gate->locked_until->isFuture());

        if (! $isLocked && $gate->state === 'full') {
            return $next($request);
        }

        return response()->json([
            'message' => 'Security gate is locked.',
            'security_gate' => [
                'state' => $isLocked ? 'locked' : $gate->state,
                'gate_required' => true,
                'locked_until' => $gate->locked_until,
                'panic_locked_at' => $gate->panic_locked_at,
            ],
        ], 423);
    }
}
