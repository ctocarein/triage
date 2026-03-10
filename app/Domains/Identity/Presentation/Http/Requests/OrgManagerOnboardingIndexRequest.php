<?php

namespace App\Domains\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrgManagerOnboardingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'          => ['nullable', Rule::in(['email_unverified', 'pending_validation', 'approved', 'rejected'])],
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
