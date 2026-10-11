<?php

namespace App\Domains\Organization\Presentation\Http\Controllers;

use App\Domains\Organization\Models\HealthFacility;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Les etablissements ou orienter une femme, avec ou sans mammographe.
 *
 * Ouvert a tout utilisateur authentifie : c'est une information publique,
 * utile a l'evaluatrice comme, demain, a la beneficiaire elle-meme.
 */
class HealthFacilityController extends Controller
{
    /**
     * GET /health-facilities
     *
     * ?mammography=1|0 : avec ou sans mammographe. ?search= : nom ou commune.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mammography' => ['nullable', 'boolean'],
            'search'      => ['nullable', 'string', 'max:100'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HealthFacility::query()->with('district');

        if ($request->filled('mammography')) {
            $query->where('has_mammography', $request->boolean('mammography'));
        }

        if (! empty($validated['search'])) {
            $term = trim($validated['search']);
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('municipality', 'like', "%{$term}%");
            });
        }

        // Les etablissements reels avant les exemples, et parmi eux ceux qui
        // ont un mammographe d'abord : c'est l'orientation la plus recherchee.
        $page = $query
            ->orderBy('is_sample')
            ->orderByDesc('has_mammography')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => $page->getCollection()
                ->map(fn (HealthFacility $facility) => [
                    'id'              => $facility->id,
                    'name'            => $facility->name,
                    'kind'            => $facility->kind,
                    'municipality'    => $facility->municipality,
                    'health_district' => $facility->district?->name,
                    'address'         => $facility->address,
                    'phone'           => $facility->phone,
                    'latitude'        => $facility->latitude,
                    'longitude'       => $facility->longitude,
                    'has_mammography' => $facility->has_mammography,
                    'is_sample'       => $facility->is_sample,
                ])
                ->all(),
            'meta' => [
                'total'        => $page->total(),
                'per_page'     => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }
}
