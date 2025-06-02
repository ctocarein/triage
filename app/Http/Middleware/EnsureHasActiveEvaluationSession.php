<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasActiveEvaluationSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        $activeSession = $account->activeEvaluationSession();

        if (!$activeSession) {
            return response()->json([
                'error' => 'No active evaluation session found.'
            ], 403);
        }

        // Injecte la session dans la requête pour usage facile
        $request->merge(['active_evaluation_session' => $activeSession]);

        return $next($request);
    }
}
