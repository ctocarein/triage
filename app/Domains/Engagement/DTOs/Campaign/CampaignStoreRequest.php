<?php

namespace App\Domains\Engagement\DTOs\Campaign;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'objective' => ['required', Rule::in(['sensibilisation', 'depistage', 'suivi_personnel', 'rse', 'medical'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'ongoing', 'completed', 'archived'])],
        ];
    }
}
