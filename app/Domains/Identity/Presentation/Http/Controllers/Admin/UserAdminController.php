<?php

namespace App\Domains\Identity\Presentation\Http\Controllers\Admin;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor   = $request->user();
        $isAdmin = $actor instanceof Identity && $actor->hasMembershipRole('PlatformAdmin');

        $orgIds = [];
        if (! $isAdmin && $actor instanceof Identity) {
            $orgIds = $actor->memberships()
                ->where('role', 'OrgManager')
                ->pluck('organization_id')
                ->filter()
                ->values()
                ->all();
        }

        $allowedRoles = ['Practitioner', 'FieldAgent'];
        $roles = $request->filled('role') && in_array($request->input('role'), $allowedRoles)
            ? [$request->input('role')]
            : $allowedRoles;

        $query = Identity::query()
            ->with([
                'credentials',
                'memberships.organization:id,name',
            ])
            ->where('kind', 'person')
            ->whereHas('memberships', function ($q) use ($roles, $orgIds) {
                $q->whereIn('role', $roles);
                if (! empty($orgIds)) {
                    $q->whereIn('organization_id', $orgIds);
                }
            });

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('credentials', fn ($q) =>
                $q->where('identifier', 'like', "%{$search}%")
            );
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginated = $query
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        $paginated->getCollection()->transform(function (Identity $identity) {
            $membership = $identity->memberships->first();
            return [
                'id'           => $identity->id,
                'status'       => $identity->status,
                'created_at'   => $identity->created_at,
                '_email'       => $identity->credentials->where('type', 'email')->first()?->identifier,
                '_phone'       => $identity->credentials->where('type', 'phone')->first()?->identifier,
                'role'         => $membership?->role,
                'organization' => $membership?->organization
                    ? ['id' => $membership->organization->id, 'name' => $membership->organization->name]
                    : null,
            ];
        });

        return response()->json($paginated);
    }
}
