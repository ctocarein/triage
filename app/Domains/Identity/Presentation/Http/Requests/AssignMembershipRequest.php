<?php

namespace App\Domains\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->input('role');

        return [
            'identity_id' => ['required', 'uuid', 'exists:identities,id'],
            'role' => ['required', Rule::in([
                'Beneficiary',
                'FieldAgent',
                'Practitioner',
                'OrgManager',
                'PlatformAdmin',
                'System',
            ])],
            'organization_id' => array_filter([
                $this->organizationRequired($role) ? 'required' : 'nullable',
                'uuid',
                'exists:organizations,id',
            ]),
        ];
    }

    private function organizationRequired(?string $role): bool
    {
        return in_array($role, ['FieldAgent', 'Practitioner', 'OrgManager'], true);
    }
}
