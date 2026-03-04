<?php

namespace App\Domains\Evaluation\DTOs\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FactMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'question_key' => ['required', 'string', 'exists:dynamic_question_templates,key'],
            'answer_value' => ['required', 'array'],
            'operator'     => ['required', 'string', Rule::in([
                '=', '!=', '>', '<', '>=', '<=', 'in', 'not_in', 'contains',
                'empty', 'not_empty',
            ])],
            'is_active'    => ['boolean'],
        ];
    }
}
