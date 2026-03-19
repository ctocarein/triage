<?php

namespace Tests\Unit;

use App\Domains\Evaluation\DTOs\Patient\EvaluationAnswerStoreRequest;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EvaluationAnswerStoreRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeBirthDateQuestion(): DynamicQuestionTemplate
    {
        return DynamicQuestionTemplate::create([
            'key' => 'birth_date',
            'text' => 'Birth date',
            'type' => 'select_one',
            'bloc_key' => 'profil_identite',
            'answer_target' => 'personal_profile',
            'order' => 1,
            'is_active' => true,
        ]);
    }

    private function validateValue(mixed $value): array
    {
        $question = $this->makeBirthDateQuestion();

        $request = EvaluationAnswerStoreRequest::create(
            '/fake',
            'POST',
            [
                'dynamic_question_template_id' => $question->id,
                'value' => $value,
                'is_skipped' => false,
            ],
        );

        $validator = Validator::make($request->all(), $request->rules());

        return [$validator->passes(), $validator->errors()->all()];
    }

    public function test_birth_date_accepts_year_format(): void
    {
        [$passes, $errors] = $this->validateValue('1998');

        $this->assertTrue($passes, implode("\n", $errors));
    }

    public function test_birth_date_accepts_iso_date_format(): void
    {
        [$passes, $errors] = $this->validateValue('1998-01-01');

        $this->assertTrue($passes, implode("\n", $errors));
    }

    public function test_birth_date_rejects_ambiguous_format(): void
    {
        [$passes, $errors] = $this->validateValue('01/01/1998');

        $this->assertFalse($passes);
        $this->assertNotEmpty($errors);
    }
}
