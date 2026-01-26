<?php

namespace App\Domains\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IdentityUpdateRequest extends FormRequest
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
        ];
    }
}
