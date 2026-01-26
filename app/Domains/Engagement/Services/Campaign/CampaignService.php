<?php

namespace App\Domains\Engagement\Services\Campaign;

use App\Domains\Engagement\Models\Campaign;
use App\Domains\Engagement\Models\QrCode;
use App\Domains\Engagement\Models\ScanEvent;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    public function listForIdentity(Identity $actor, array $filters = []): LengthAwarePaginator
    {
        $query = Campaign::query();

        if ($this->isPlatformAdmin($actor)) {
            if (isset($filters['organization_id'])) {
                $query->where('organization_id', $filters['organization_id']);
            }
        } else {
            $organizationIds = $this->organizationIdsForRole($actor, 'OrgManager');
            if (empty($organizationIds)) {
                abort(403, 'You are not allowed to list campaigns.');
            }

            $query->whereIn('organization_id', $organizationIds);

            if (isset($filters['organization_id'])) {
                $query->where('organization_id', $filters['organization_id']);
            }
        }

        $paginator = $this->applyFilters($query, $filters)
            ->withCount('qrCodes')
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        if (! empty($filters['with_qr_counts'])) {
            $paginator->getCollection()->transform(function (Campaign $campaign) {
                $campaign->qr_counts = $this->getQrStatusCounts($campaign);

                return $campaign;
            });
        }

        return $paginator;
    }

    public function create(Identity $actor, array $data): Campaign
    {
        $organizationId = $this->resolveOrganizationId($actor, $data);
        $this->assertOrganizationAccess($actor, $organizationId);

        return DB::transaction(function () use ($actor, $data, $organizationId) {
            $campaign = Campaign::query()->create([
                'organization_id' => $organizationId,
                'name' => $data['name'],
                'objective' => $data['objective'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'created_by' => $actor->id,
            ]);

            return $campaign->fresh();
        });
    }

    public function update(Identity $actor, Campaign $campaign, array $data): Campaign
    {
        $this->assertOrganizationAccess($actor, $campaign->organization_id);

        $campaign->fill($data);
        $campaign->save();

        return $campaign->fresh();
    }

    public function archive(Identity $actor, Campaign $campaign): Campaign
    {
        $this->assertOrganizationAccess($actor, $campaign->organization_id);

        $campaign->forceFill([
            'status' => 'archived',
        ])->save();

        return $campaign->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function getReport(Campaign $campaign): array
    {
        $qrCodesQuery = QrCode::query()
            ->where('campaign_id', $campaign->id);

        $totalQr = (clone $qrCodesQuery)->count();
        $qrByStatus = (clone $qrCodesQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $scanEvents = ScanEvent::query()
            ->whereHas('qrCode', fn (Builder $query) => $query->where('campaign_id', $campaign->id));

        $totalScans = (clone $scanEvents)->count();
        $successScans = (clone $scanEvents)->where('result', 'success')->count();
        $invalidScans = (clone $scanEvents)->where('result', 'invalid')->count();
        $expiredScans = (clone $scanEvents)->where('result', 'expired')->count();
        $lastScan = (clone $scanEvents)->max('scanned_at');

        $uniqueIdentities = (clone $scanEvents)
            ->whereNotNull('identity_id')
            ->distinct('identity_id')
            ->count('identity_id');

        $evaluationSessions = (clone $scanEvents)
            ->whereNotNull('evaluation_session_id')
            ->distinct('evaluation_session_id')
            ->count('evaluation_session_id');

        return [
            'campaign_id' => $campaign->id,
            'total_qr_codes' => $totalQr,
            'qr_codes_by_status' => $qrByStatus,
            'scans' => [
                'total' => $totalScans,
                'success' => $successScans,
                'invalid' => $invalidScans,
                'expired' => $expiredScans,
                'last_scan_at' => $lastScan,
            ],
            'unique_identities' => $uniqueIdentities,
            'evaluation_sessions' => $evaluationSessions,
        ];
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['objective'])) {
            $query->where('objective', $filters['objective']);
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    protected function getQrStatusCounts(Campaign $campaign): array
    {
        return $campaign->qrCodes()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }

    protected function resolveOrganizationId(Identity $actor, array $data): string
    {
        if (! empty($data['organization_id'])) {
            return $data['organization_id'];
        }

        $organizationIds = $this->organizationIdsForRole($actor, 'OrgManager');
        if (count($organizationIds) === 1) {
            return $organizationIds[0];
        }

        abort(422, 'organization_id is required.');
    }

    /**
     * @return array<int, string>
     */
    protected function organizationIdsForRole(Identity $actor, string $role): array
    {
        return $actor->memberships()
            ->where('role', $role)
            ->where('status', 'active')
            ->pluck('organization_id')
            ->filter()
            ->values()
            ->all();
    }

    protected function isPlatformAdmin(Identity $actor): bool
    {
        return $actor->hasMembershipRole('PlatformAdmin');
    }

    protected function assertOrganizationAccess(Identity $actor, ?string $organizationId): void
    {
        if ($organizationId === null) {
            abort(403, 'Organization is required.');
        }

        if ($this->isPlatformAdmin($actor)) {
            return;
        }

        if (! $actor->hasMembershipRole('OrgManager', $organizationId)) {
            abort(403, 'You are not allowed to manage this campaign.');
        }
    }
}
