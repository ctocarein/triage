<?php

namespace App\Domains\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MembershipIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identity_id' => ['sometimes', 'uuid', 'exists:identities,id'],
            'organization_id' => ['sometimes', 'uuid', 'exists:organizations,id'],
            'role' => ['sometimes', Rule::in([
                'Beneficiary',
                'FieldAgent',
                'Ambassador',
                'Practitioner',
                'OrgManager',
                'PlatformAdmin',
                'System',
            ])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
