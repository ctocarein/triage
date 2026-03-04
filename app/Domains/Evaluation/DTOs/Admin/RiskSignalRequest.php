<?php

namespace App\Domains\Evaluation\DTOs\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RiskSignalRequest extends FormRequest
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
            'weight'           => ['required', 'integer', 'min:0', 'max:100'],
            'is_active'        => ['boolean'],
        ];
    }
}
