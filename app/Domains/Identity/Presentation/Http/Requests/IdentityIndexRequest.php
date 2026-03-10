<?php

namespace App\Domains\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IdentityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['sometimes', Rule::in(['person', 'system'])],
            'status' => ['sometimes', Rule::in(['active', 'suspended', 'anonymized'])],
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'phone'    => ['sometimes', 'string', 'max:30'],
        ];
    }
}
