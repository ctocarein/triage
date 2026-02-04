<?php

namespace App\Domains\Profile\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Profile\Infrastructure\Persistence\Models\ProfessionalProfile
 */
class ProfessionalProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identity_id' => $this->identity_id,
            'organization_id' => $this->organization_id,
            'full_name' => $this->full_name,
            'job_title' => $this->job_title,
            'address' => $this->address,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
