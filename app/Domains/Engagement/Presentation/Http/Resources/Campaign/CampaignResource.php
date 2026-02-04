<?php

namespace App\Domains\Engagement\Presentation\Http\Resources\Campaign;

use App\Domains\Engagement\Presentation\Http\Resources\Qr\QrCodeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Engagement\Infrastructure\Persistence\Models\Campaign
 */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'objective' => $this->objective,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'description' => $this->description,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'validated_by' => $this->validated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'qr_codes' => QrCodeResource::collection($this->whenLoaded('qrCodes')),
            'qr_counts' => $this->when(
                isset($this->qr_counts),
                fn () => $this->qr_counts
            ),
        ];
    }
}
