<?php

namespace App\Domains\Profile\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicalProfileStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cmu_number' => ['nullable', 'string', 'max:255'],
            'height' => ['nullable', 'numeric', 'min:50', 'max:250'],
            'weight' => ['nullable', 'numeric', 'min:20', 'max:300'],
            'blood_type' => ['nullable', 'string', 'max:3'],
            'allergies' => ['nullable', 'string', 'max:500'],
            'is_pregnant' => ['boolean'],
            'breastfeeding' => ['boolean'],
            'menopause_status' => ['nullable', 'string', 'max:255'],
            'family_cancer_history' => ['boolean'],
            'last_screening_date' => ['nullable', 'date'],
            'screening_type' => ['nullable', 'string', 'max:255'],
            'first_sexual_intercourse_age' => ['nullable', 'integer', 'min:10', 'max:60'],
            'number_of_partners' => ['nullable', 'integer', 'min:0', 'max:99'],
            'number_of_pregnancies' => ['nullable', 'integer', 'min:0', 'max:20'],
            'number_of_abortions' => ['nullable', 'integer', 'min:0', 'max:20'],
            'number_of_children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'exposed_to_smoke' => ['boolean'],
            'exposure_type' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
