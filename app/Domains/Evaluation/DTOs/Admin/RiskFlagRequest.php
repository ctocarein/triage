<?php

namespace App\Domains\Evaluation\DTOs\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RiskFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'fact_key'         => ['required', 'string', 'exists:facts,key'],
            'model_version_id' => ['nullable', 'integer', 'exists:model_versions,id'],
            'type'             => ['required', 'string', Rule::in(['warning', 'critical', 'info', 'exclusion', 'urgent'])],
            'priority'         => ['required', 'integer', 'min:0'],
            'is_active'        => ['boolean'],
        ];
    }
}
