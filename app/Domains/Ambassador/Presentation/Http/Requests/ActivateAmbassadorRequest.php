<?php

namespace App\Domains\Ambassador\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivateAmbassadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identity_id' => ['required', 'uuid', 'exists:identities,id'],
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
        ];
    }
}
