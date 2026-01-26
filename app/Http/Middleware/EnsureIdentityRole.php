<?php

namespace App\Http\Middleware;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdentityRole
{
    /**
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $actor = $request->user();

        if (! $actor instanceof Identity) {
            abort(403, 'Forbidden.');
        }

        $organizationId = $request->input('organization_id') ?? $request->query('organization_id');

        foreach ($roles as $role) {
            if ($actor->hasMembershipRole($role, $organizationId)) {
                return $next($request);
            }
        }

        abort(403, 'Forbidden.');
    }
}
