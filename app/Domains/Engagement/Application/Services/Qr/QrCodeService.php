<?php

namespace App\Domains\Engagement\Application\Services\Qr;

use App\Domains\Engagement\Infrastructure\Persistence\Models\Campaign;
use App\Domains\Engagement\Infrastructure\Persistence\Models\QrCode;
use App\Domains\Engagement\Infrastructure\Persistence\Models\ScanEvent;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QrCodeService
{
    public function listForIdentity(Identity $actor, array $filters = []): LengthAwarePaginator
    {
        $query = QrCode::query();

        if ($this->isPlatformAdmin($actor)) {
            if (isset($filters['organization_id'])) {
                $query->where('organization_id', $filters['organization_id']);
            }
        } else {
            $organizationIds = $this->organizationIdsForRole($actor, 'OrgManager');
            if (empty($organizationIds)) {
                abort(403, 'You are not allowed to list QR codes.');
            }

            $query->whereIn('organization_id', $organizationIds);

            if (isset($filters['organization_id'])) {
                $query->where('organization_id', $filters['organization_id']);
            }
        }

        return $this->paginate($query, $filters);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Engagement\Infrastructure\Persistence\Models\QrCode>
     */
    public function createForIdentity(Identity $actor, array $data): EloquentCollection
    {
        $organizationId = $this->resolveOrganizationId($actor, $data);
        $this->assertOrganizationAccess($actor, $organizationId);

        $quantity = max(1, min((int) ($data['quantity'] ?? 1), 500));
        $type = $this->resolveType($data['type'] ?? null);
        $metadata = $this->prepareMetadata($data['metadata'] ?? null);
        $status = $this->resolveStatus($data['status'] ?? 'active');
        $campaignId = $this->resolveCampaignId($organizationId, $data['campaign_id'] ?? null);
        $template = $data['url_template'] ?? null;

        return DB::transaction(function () use ($actor, $organizationId, $quantity, $type, $metadata, $status, $campaignId, $template, $data) {
            $collection = new EloquentCollection();

            for ($index = 0; $index < $quantity; $index++) {
                $codeValue = $this->generateUniqueCode();
                $url = $this->buildUrl($codeValue, $template);
                $timestamps = $this->statusTimestamps($status, [
                    'activated_at' => $data['activated_at'] ?? null,
                    'scanned_at' => $data['scanned_at'] ?? null,
                    'expired_at' => $data['expired_at'] ?? $data['expires_at'] ?? null,
                    'revoked_at' => $data['revoked_at'] ?? null,
                ]);

                $qrCode = QrCode::query()->create([
                    'organization_id' => $organizationId,
                    'type' => $type,
                    'code' => $codeValue,
                    'url' => $url,
                    'campaign_id' => $campaignId,
                    'status' => $status,
                    'activated_at' => $timestamps['activated_at'],
                    'scanned_at' => $timestamps['scanned_at'],
                    'expired_at' => $timestamps['expired_at'],
                    'revoked_at' => $timestamps['revoked_at'],
                    'metadata' => $metadata,
                    'created_by' => $actor->id,
                ]);

                $collection->push($qrCode->fresh(['organization:id,name', 'campaign:id,name,status']));
            }

            return $collection;
        });
    }

    public function updateForIdentity(Identity $actor, QrCode $qrCode, array $data): QrCode
    {
        $this->assertOrganizationAccess($actor, $qrCode->organization_id);

        return DB::transaction(function () use ($qrCode, $data) {
            if (array_key_exists('campaign_id', $data)) {
                $qrCode->campaign_id = $this->resolveCampaignId($qrCode->organization_id, $data['campaign_id']);
            }

            if (array_key_exists('type', $data)) {
                $qrCode->type = $this->resolveType($data['type']);
            }

            if (array_key_exists('metadata', $data)) {
                $qrCode->metadata = $this->prepareMetadata($data['metadata']);
            }

            if (array_key_exists('url_template', $data)) {
                $qrCode->url = $this->buildUrl($qrCode->code, $data['url_template']);
            }

            if (array_key_exists('status', $data)) {
                $status = $this->resolveStatus($data['status']);
                $qrCode->status = $status;
                $timestamps = $this->statusTimestamps($status, [
                    'activated_at' => $data['activated_at'] ?? null,
                    'scanned_at' => $data['scanned_at'] ?? null,
                    'expired_at' => $data['expired_at'] ?? $data['expires_at'] ?? null,
                    'revoked_at' => $data['revoked_at'] ?? null,
                ]);
                $qrCode->fill($timestamps);
            } else {
                foreach (['activated_at', 'scanned_at', 'revoked_at'] as $timestampField) {
                    if (array_key_exists($timestampField, $data)) {
                        $qrCode->{$timestampField} = $data[$timestampField];
                    }
                }

                if (array_key_exists('expired_at', $data) || array_key_exists('expires_at', $data)) {
                    $qrCode->expired_at = $data['expired_at'] ?? $data['expires_at'];
                }
            }

            $qrCode->save();

            return $qrCode->fresh(['organization:id,name', 'campaign:id,name,status']);
        });
    }

    public function deleteForIdentity(Identity $actor, QrCode $qrCode): void
    {
        $this->assertOrganizationAccess($actor, $qrCode->organization_id);

        $hasScans = $qrCode->scanEvents()->exists();
        if ($qrCode->status !== 'active' || $hasScans) {
            abort(422, 'Only unused active QR codes can be deleted.');
        }

        $qrCode->delete();
    }

    public function validate(Identity $actor, QrCode $qrCode): QrCode
    {
        if (! $this->isPlatformAdmin($actor)) {
            abort(403, 'Only platform admins can validate QR codes.');
        }

        if ($qrCode->status === 'revoked') {
            abort(422, 'Cannot activate a revoked QR code.');
        }

        $qrCode->forceFill([
            'status' => 'active',
            'activated_at' => now(),
            'revoked_at' => null,
        ])->save();

        return $qrCode->fresh(['organization:id,name', 'campaign:id,name,status']);
    }

    public function revoke(Identity $actor, QrCode $qrCode): QrCode
    {
        if (! $this->isPlatformAdmin($actor)) {
            abort(403, 'Only platform admins can revoke QR codes.');
        }

        if ($qrCode->status === 'revoked') {
            return $qrCode;
        }

        $qrCode->forceFill([
            'status' => 'revoked',
            'revoked_at' => now(),
        ])->save();

        return $qrCode->fresh(['organization:id,name', 'campaign:id,name,status']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(QrCode $qrCode): array
    {
        $scanQuery = $qrCode->scanEvents();

        $totalScans = (clone $scanQuery)->count();
        $success = (clone $scanQuery)->where('result', 'success')->count();
        $invalid = (clone $scanQuery)->where('result', 'invalid')->count();
        $expired = (clone $scanQuery)->where('result', 'expired')->count();
        $uniqueIdentities = (clone $scanQuery)
            ->whereNotNull('identity_id')
            ->distinct('identity_id')
            ->count('identity_id');
        $evaluationSessions = (clone $scanQuery)
            ->whereNotNull('evaluation_session_id')
            ->distinct('evaluation_session_id')
            ->count('evaluation_session_id');
        $lastScanAt = (clone $scanQuery)->max('scanned_at');

        $successRate = $totalScans > 0 ? round($success / $totalScans, 4) : 0.0;

        return [
            'total_scans' => $totalScans,
            'success' => $success,
            'invalid' => $invalid,
            'expired' => $expired,
            'unique_identities' => $uniqueIdentities,
            'evaluation_sessions' => $evaluationSessions,
            'last_scan_at' => $lastScanAt,
            'success_rate' => $successRate,
        ];
    }

    public function findByCode(string $code, array $relations = []): QrCode
    {
        return QrCode::query()
            ->with($relations)
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveRuntimeStatus(QrCode $qrCode): array
    {
        $status = $qrCode->status;

        if ($status === 'active') {
            if ($qrCode->revoked_at !== null) {
                $status = 'revoked';
            } elseif ($qrCode->expired_at !== null && $qrCode->expired_at->isPast()) {
                $status = 'expired';
            } elseif ($this->shouldConsume($qrCode) && $qrCode->scanned_at !== null) {
                $status = 'used';
            }
        }

        return [
            'status' => $status,
            'can_scan' => $status === 'active',
            'expires_at' => $qrCode->expired_at,
            'metadata' => $qrCode->metadata,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordScan(QrCode $qrCode, array $payload): ScanEvent
    {
        $scan = $qrCode->scanEvents()->create([
            'identity_id' => $payload['identity_id'] ?? null,
            'scanned_at' => now(),
            'location' => $payload['location'] ?? null,
            'device_info' => $payload['device_info'] ?? null,
            'ip_address' => $payload['ip_address'] ?? null,
            'evaluation_session_id' => $payload['evaluation_session_id'] ?? null,
            'result' => $payload['result'] ?? 'success',
        ]);

        $updates = [
            'scanned_at' => $scan->scanned_at,
        ];

        if ($payload['result'] === 'expired') {
            $updates['status'] = 'expired';
        } elseif ($payload['result'] === 'success' && $this->shouldConsume($qrCode)) {
            $updates['status'] = 'used';
        }

        $qrCode->forceFill($updates)->save();

        return $scan;
    }

    public function generatePng(QrCode $qrCode): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            abort(500, 'GD extension is required to generate QR code images.');
        }

        $size = 512;
        $image = imagecreatetruecolor($size, $size);

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefilledrectangle($image, 0, 0, $size, $size, $white);

        $margin = 24;
        imagerectangle($image, $margin, $margin, $size - $margin, $size - $margin, $black);

        $title = strtoupper($qrCode->type);
        $titleFont = 4;
        $titleWidth = imagefontwidth($titleFont) * strlen($title);
        imagestring($image, $titleFont, intval(($size - $titleWidth) / 2), 32, $title, $black);

        $codeFont = 5;
        $codeText = $qrCode->code;
        $codeWidth = imagefontwidth($codeFont) * strlen($codeText);
        imagestring($image, $codeFont, intval(($size - $codeWidth) / 2), intval($size / 2) - 8, $codeText, $black);

        $urlFont = 2;
        $url = $qrCode->url;
        $chunks = str_split($url, 30);
        $y = intval($size / 2) + 32;
        foreach ($chunks as $chunk) {
            $chunkWidth = imagefontwidth($urlFont) * strlen($chunk);
            imagestring($image, $urlFont, intval(($size - $chunkWidth) / 2), $y, $chunk, $black);
            $y += 14;
        }

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrganizationStats(Identity $actor): array
    {
        $organizationId = $this->resolveOrganizationId($actor, []);
        $this->assertOrganizationAccess($actor, $organizationId);

        $statusCounts = QrCode::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->where('organization_id', $organizationId)
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($value) => (int) $value)
            ->all();

        $statuses = [
            'active' => $statusCounts['active'] ?? 0,
            'used' => $statusCounts['used'] ?? 0,
            'expired' => $statusCounts['expired'] ?? 0,
            'revoked' => $statusCounts['revoked'] ?? 0,
        ];

        $scanQuery = ScanEvent::query()
            ->whereHas('qrCode', fn (Builder $builder) => $builder->where('organization_id', $organizationId));

        $totalScans = (clone $scanQuery)->count();
        $todayScans = (clone $scanQuery)->whereDate('scanned_at', now()->toDateString())->count();
        $weekScans = (clone $scanQuery)->where('scanned_at', '>=', now()->subDays(7))->count();
        $lastScanAt = (clone $scanQuery)->max('scanned_at');

        return [
            'status_counts' => $statuses,
            'total_codes' => array_sum($statuses),
            'scan_metrics' => [
                'total' => $totalScans,
                'today' => $todayScans,
                'last_7_days' => $weekScans,
                'last_scan_at' => $lastScanAt,
            ],
        ];
    }

    protected function paginate(Builder $query, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        return $this->applyFilters($query, $filters)
            ->with(['organization:id,name', 'campaign:id,name,status'])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (! empty($filters['code'])) {
            $value = $filters['code'];
            $query->where(function (Builder $builder) use ($value) {
                $builder->where('code', 'like', $value . '%')
                    ->orWhere('url', 'like', '%' . $value . '%');
            });
        }

        return $query;
    }

    protected function resolveCampaignId(string $organizationId, mixed $campaignId): ?int
    {
        if ($campaignId === null) {
            return null;
        }

        $campaign = Campaign::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($campaignId);

        return $campaign->id;
    }

    protected function resolveType(?string $requested): string
    {
        $allowed = config('qr.types', ['evaluation']);
        $type = $requested ?? 'evaluation';

        if (! in_array($type, $allowed, true)) {
            return 'evaluation';
        }

        return $type;
    }

    protected function resolveStatus(?string $requested): string
    {
        $allowed = ['active', 'used', 'expired', 'revoked'];

        if ($requested !== null && in_array($requested, $allowed, true)) {
            return $requested;
        }

        return 'active';
    }

    /**
     * @param array<string, mixed>|null $metadata
     *
     * @return array<string, mixed>|null
     */
    protected function prepareMetadata($metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        if (! is_array($metadata)) {
            abort(422, 'Metadata must be an object.');
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, ?\Illuminate\Support\Carbon>
     */
    protected function statusTimestamps(string $status, array $overrides = []): array
    {
        $now = now();

        return [
            'activated_at' => $overrides['activated_at'] ?? ($status === 'active' ? $now : null),
            'scanned_at' => $overrides['scanned_at'] ?? ($status === 'used' ? $now : null),
            'expired_at' => $overrides['expired_at'] ?? ($status === 'expired' ? $now : null),
            'revoked_at' => $overrides['revoked_at'] ?? ($status === 'revoked' ? $now : null),
        ];
    }

    protected function buildUrl(string $code, ?string $template): string
    {
        if ($template === null || $template === '') {
            return rtrim(config('app.url'), '/') . '/qr/' . $code;
        }

        if (str_contains($template, '{code}')) {
            return str_replace('{code}', $code, $template);
        }

        return rtrim($template, '/') . '/' . $code;
    }

    protected function generateUniqueCode(): string
    {
        do {
            $candidate = sprintf('CHR-%s-%s', now()->format('y'), Str::upper(Str::random(6)));
        } while (QrCode::query()->where('code', $candidate)->exists());

        return $candidate;
    }

    protected function shouldConsume(QrCode $qrCode): bool
    {
        $metadataFlag = Arr::get($qrCode->metadata ?? [], 'single_use');

        if ($metadataFlag !== null) {
            return (bool) $metadataFlag;
        }

        return $qrCode->type === 'anonymous';
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
            abort(403, 'You are not allowed to manage this QR code.');
        }
    }
}
