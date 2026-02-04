<?php

namespace App\Domains\Ambassador\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Ambassador\Infrastructure\Persistence\Models\AmbassadorBadge
 */
class BadgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ambassador_id' => $this->ambassador_id,
            'badge_code' => $this->badge_code,
            'status' => $this->status,
            'earned_at' => $this->earned_at,
            'revoked_at' => $this->revoked_at,
            'evidence' => $this->evidence,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
