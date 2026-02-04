<?php

namespace App\Domains\Engagement\Presentation\Http\Resources\Campaign;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'campaign_id' => $this['campaign_id'],
            'total_qr_codes' => $this['total_qr_codes'],
            'qr_codes_by_status' => $this['qr_codes_by_status'],
            'scans' => [
                'total' => $this['scans']['total'] ?? 0,
                'success' => $this['scans']['success'] ?? 0,
                'invalid' => $this['scans']['invalid'] ?? 0,
                'expired' => $this['scans']['expired'] ?? 0,
                'last_scan_at' => $this['scans']['last_scan_at'] ?? null,
            ],
            'unique_identities' => $this['unique_identities'] ?? 0,
            'evaluation_sessions' => $this['evaluation_sessions'] ?? 0,
        ];
    }
}
