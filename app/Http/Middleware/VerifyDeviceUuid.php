<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyDeviceUuid
{
    public function handle(Request $request, Closure $next)
    {
        $account = $request->user();

        if ($account && $request->header('X-Phone-UUID') !== $account->phone_uuid) {
            return response()->json(['message' => 'Unauthorized device.'], 403);
        }

        return $next($request);
    }
}
