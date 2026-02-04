<?php

namespace App\Domains\Ambassador\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral
 */
class ReferralResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ambassador_id' => $this->ambassador_id,
            'code' => $this->code,
            'channel' => $this->channel,
            'expires_at' => $this->expires_at,
            'revoked_at' => $this->revoked_at,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_revoked' => $this->revoked_at !== null,
            'is_expired' => $this->expires_at !== null && $this->expires_at->isPast(),
        ];
    }
}
