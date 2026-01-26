<?php

namespace App\Domains\Engagement\DTOs\Campaign;

use Illuminate\Foundation\Http\FormRequest;

class CampaignIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', 'in:draft,ongoing,completed,archived'],
            'objective' => ['sometimes', 'required', 'in:sensibilisation,depistage,suivi_personnel,rse,medical'],
            'organization_id' => ['sometimes', 'required', 'uuid', 'exists:organizations,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'with_qr_counts' => ['sometimes', 'boolean'],
        ];
    }
}
