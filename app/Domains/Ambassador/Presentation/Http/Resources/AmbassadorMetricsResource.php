<?php

namespace App\Domains\Ambassador\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AmbassadorMetricsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_referrals' => $this['total_referrals'] ?? 0,
            'active_referrals' => $this['active_referrals'] ?? 0,
            'expired_referrals' => $this['expired_referrals'] ?? 0,
            'revoked_referrals' => $this['revoked_referrals'] ?? 0,
            'started_usages' => $this['started_usages'] ?? 0,
            'completed_usages' => $this['completed_usages'] ?? 0,
            'last_used_at' => $this['last_used_at'] ?? null,
            'badges_earned' => $this['badges_earned'] ?? 0,
            'level_code' => $this['level_code'] ?? 'starter',
        ];
    }
}
