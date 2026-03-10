<?php

namespace App\Domains\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrgManagerRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason_code'   => ['required', Rule::in(['incomplete_info', 'unknown_org', 'duplicate', 'other'])],
            'reason_detail' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason_code.required' => 'Le motif de rejet est obligatoire.',
            'reason_code.in'       => 'Le motif de rejet n\'est pas valide.',
        ];
    }
}
