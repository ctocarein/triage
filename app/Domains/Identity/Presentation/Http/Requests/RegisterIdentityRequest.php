<?php

namespace App\Domains\Identity\Presentation\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RegisterIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('credential_type');
        $role = $this->input('role');

        return [
            'kind' => ['nullable', Rule::in(['person', 'system'])],
            'credential_type' => ['required', Rule::in(['email', 'phone'])],
            'identifier' => array_filter([
                'required',
                'string',
                $type === 'email' ? 'email' : null,
                $type === 'phone' ? 'min:8' : null,
                $type === 'phone' ? 'max:20' : null,
            ]),
            'secret' => array_filter([
                $type === 'phone' ? 'nullable' : 'required',
                'string',
                $type === 'phone' ? 'min:4' : null,
                $type === 'phone' ? 'max:6' : null,
                $type === 'email' ? 'min:6' : null,
            ]),
            'device_fingerprint' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in([
                'Beneficiary',
                'FieldAgent',
                'Ambassador',
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
        return in_array($role, ['FieldAgent', 'Ambassador', 'Practitioner', 'OrgManager'], true);
    }

    protected function passedValidation(): void
    {
        $payload = $this->validated();
        unset($payload['secret']);

        if (isset($payload['identifier'])) {
            $payload['identifier'] = $this->maskIdentifier((string) $payload['identifier']);
        }

        Log::info('Identity register validation passed', $payload);
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('Identity register validation failed', [
            'identifier' => $this->maskIdentifier((string) $this->input('identifier')),
            'errors' => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }

    private function maskIdentifier(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        if (str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);
            $first = substr($local, 0, 1);
            $last = strlen($local) > 1 ? substr($local, -1) : '';
            $mask = str_repeat('*', max(0, strlen($local) - 2));

            return $first . $mask . $last . '@' . $domain;
        }

        $suffix = strlen($value) > 4 ? substr($value, -4) : $value;
        $mask = str_repeat('*', max(0, strlen($value) - strlen($suffix)));

        return $mask . $suffix;
    }
}
