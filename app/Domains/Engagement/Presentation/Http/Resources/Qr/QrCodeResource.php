<?php

namespace App\Domains\Engagement\Presentation\Http\Resources\Qr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Engagement\Infrastructure\Persistence\Models\QrCode
 */
class QrCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'organization' => [
                'id' => $this->organization_id,
                'name' => $this->when($this->relationLoaded('organization'), fn () => $this->organization?->name),
            ],
            'campaign_id' => $this->campaign_id,
            'campaign' => $this->when($this->relationLoaded('campaign'), function () {
                return $this->campaign?->only(['id', 'name', 'status']);
            }),
            'type' => $this->type,
            'code' => $this->code,
            'url' => $this->url,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'activated_at' => $this->activated_at,
            'scanned_at' => $this->scanned_at,
            'expired_at' => $this->expired_at,
            'revoked_at' => $this->revoked_at,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
