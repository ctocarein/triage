<?php

namespace App\Domains\Profile\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Profile\Infrastructure\Persistence\Models\PersonalProfile
 */
class PersonalProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identity_id' => $this->identity_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'birth_date' => $this->birth_date,
            'age' => $this->age,
            'language' => $this->language,
            'education_level' => $this->education_level,
            'gender' => $this->gender,
            'commune' => $this->commune,
            'cmu' => $this->cmu,
            'height' => $this->height,
            'occupation' => $this->occupation,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
