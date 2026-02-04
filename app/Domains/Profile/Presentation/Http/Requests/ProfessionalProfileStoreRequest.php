<?php

namespace App\Domains\Profile\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfessionalProfileStoreRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'organization_id' => [
                Rule::requiredIf($this->organizationRequired()),
                'uuid',
                'exists:organizations,id',
            ],
        ];
    }

    private function organizationRequired(): bool
    {
        $actor = $this->user();

        if (! $actor) {
            return false;
        }

        $roles = [
            'FieldAgent',
            'Ambassador',
            'Practitioner',
            'OrgManager',
            'PlatformAdmin',
            'System',
        ];

        foreach ($roles as $role) {
            if ($actor->hasMembershipRole($role)) {
                return true;
            }
        }

        return false;
    }
}
