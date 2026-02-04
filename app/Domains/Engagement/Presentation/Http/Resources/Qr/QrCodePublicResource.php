<?php

namespace App\Domains\Engagement\Presentation\Http\Resources\Qr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Engagement\Infrastructure\Persistence\Models\QrCode
 */
class QrCodePublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'type' => $this->type,
            'status' => $this->status,
            'state' => $this->runtime_state ?? null,
            'organization_id' => $this->organization_id,
            'organization' => $this->when($this->relationLoaded('organization'), function () {
                return $this->organization?->only(['id', 'name']);
            }),
            'campaign_id' => $this->campaign_id,
            'campaign' => $this->when($this->relationLoaded('campaign'), function () {
                return $this->campaign?->only(['id', 'name', 'status']);
            }),
            'activated_at' => $this->activated_at,
            'scanned_at' => $this->scanned_at,
            'expired_at' => $this->expired_at,
            'metadata' => $this->metadata,
            'url' => $this->url,
        ];
    }
}
