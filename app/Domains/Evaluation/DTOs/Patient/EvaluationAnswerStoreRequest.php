<?php

namespace App\Domains\Evaluation\DTOs\Patient;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
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
            'value' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $questionId = $this->input('dynamic_question_template_id');
                    if (! $questionId) {
                        return;
                    }

                    $question = DynamicQuestionTemplate::query()
                        ->select(['id', 'key'])
                        ->find($questionId);

                    if (! $question || $question->key !== 'birth_date') {
                        return;
                    }

                    if ($this->boolean('is_skipped')) {
                        return;
                    }

                    $birthDate = is_int($value) ? (string) $value : (is_string($value) ? trim($value) : '');

                    if (! preg_match('/^\d{4}$/', $birthDate) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
                        $fail("Le champ birth_date doit etre au format YYYY ou YYYY-MM-DD. Les formats ambigus sont refuses.");
                    }
                },
            ],
            'is_skipped' => ['nullable'],
        ];
    }
}
