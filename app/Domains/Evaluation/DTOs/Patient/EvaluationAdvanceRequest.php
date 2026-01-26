<?php

namespace App\Domains\Evaluation\DTOs\Patient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluationAdvanceRequest extends FormRequest
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
            'question_id' => ['required', 'integer', 'exists:dynamic_question_templates,id'],
            'value' => ['nullable'],
            'file_ids' => ['nullable', 'array'],
            'file_ids.*' => ['uuid', Rule::exists('uploads', 'file_id')],
            'is_skipped' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $value = $this->input('value');
            $fileIds = $this->input('file_ids');
            $isSkipped = filter_var($this->input('is_skipped'), FILTER_VALIDATE_BOOLEAN);

            if ($value === null && empty($fileIds) && ! $isSkipped) {
                $validator->errors()->add('value', 'value or file_ids is required unless is_skipped is true.');
            }
        });
    }
}
