<?php

namespace App\Domains\Evaluation\DTOs\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModelVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', Rule::in(['catalog', 'risk_model'])],
            'version'     => ['required', 'string', 'max:50', 'regex:/^\d+\.\d+(\.\d+)?$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'checksum'    => ['nullable', 'string', 'max:128'],
            'metadata'    => ['nullable', 'array'],
            'released_at' => ['nullable', 'date'],
        ];
    }
}
