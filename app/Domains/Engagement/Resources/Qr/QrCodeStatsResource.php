<?php

namespace App\Domains\Engagement\Resources\Qr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QrCodeStatsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_scans' => $this['total_scans'] ?? 0,
            'success' => $this['success'] ?? 0,
            'invalid' => $this['invalid'] ?? 0,
            'expired' => $this['expired'] ?? 0,
            'unique_identities' => $this['unique_identities'] ?? 0,
            'evaluation_sessions' => $this['evaluation_sessions'] ?? 0,
            'last_scan_at' => $this['last_scan_at'] ?? null,
            'success_rate' => $this['success_rate'] ?? 0.0,
        ];
    }
}
