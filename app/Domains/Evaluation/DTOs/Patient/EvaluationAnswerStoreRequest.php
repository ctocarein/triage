<?php

namespace App\Domains\Evaluation\DTOs\Patient;

use Illuminate\Foundation\Http\FormRequest;

class EvaluationAnswerStoreRequest extends FormRequest
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
            'dynamic_question_template_id' => ['required', 'exists:dynamic_question_templates,id'],
            'value' => ['nullable'],
            'is_skipped' => ['nullable'],
        ];
    }
}
