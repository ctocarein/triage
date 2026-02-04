<?php

namespace App\Domains\Profile\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Profile\Infrastructure\Persistence\Models\MedicalProfile
 */
class MedicalProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identity_id' => $this->identity_id,
            'cmu_number' => $this->cmu_number,
            'has_breast_cancer' => $this->has_breast_cancer,
            'has_ovarian_cancer' => $this->has_ovarian_cancer,
            'has_cervical_cancer' => $this->has_cervical_cancer,
            'has_other_cancer' => $this->has_other_cancer,
            'height' => $this->height,
            'weight' => $this->weight,
            'blood_type' => $this->blood_type,
            'allergies' => $this->allergies,
            'is_pregnant' => $this->is_pregnant,
            'breastfeeding' => $this->breastfeeding,
            'menopause_status' => $this->menopause_status,
            'family_cancer_history' => $this->family_cancer_history,
            'last_screening_date' => $this->last_screening_date,
            'screening_type' => $this->screening_type,
            'first_sexual_intercourse_age' => $this->first_sexual_intercourse_age,
            'number_of_partners' => $this->number_of_partners,
            'number_of_pregnancies' => $this->number_of_pregnancies,
            'number_of_abortions' => $this->number_of_abortions,
            'number_of_children' => $this->number_of_children,
            'exposed_to_smoke' => $this->exposed_to_smoke,
            'exposure_type' => $this->exposure_type,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
