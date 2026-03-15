<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Enums\QuestionType;
use App\Domains\Evaluation\Models\EvaluationSession;
use Carbon\Carbon;

class ProfileUpdaterService
{
    public function syncFromAnswers(EvaluationSession $session): void
    {
        $answers = $session->answers()->with('question')->get();

        $personalData = [];
        $medicalData = [];

        foreach ($answers as $answer) {
            if (! $answer->question) {
                continue;
            }

            // Ignore questions that do not target profile persistence.
            $target = $answer->question->answer_target;
            $key = $answer->question->key;
            $value = $this->castValue($key, $answer->question->type, $answer->value);

            if ($target === 'personal_profile') {
                $personalData[$key] = $value;
            }

            if ($target === 'medical_profile') {
                $medicalData[$key] = $value;
            }
        }

        $identity = $session->identity;

        if (! empty($personalData)) {
            $identity->personalProfile()->updateOrCreate([], $personalData);
        }

        if (! empty($medicalData)) {
            $identity->medicalProfile()->updateOrCreate([], $medicalData);
        }
    }

    private function castValue(string $key, QuestionType $type, mixed $value): mixed
    {
        if ($key === 'birth_date') {
            return $this->normalizeBirthDate($value);
        }

        if ($key === 'last_screening_date') {
            return $this->normalizeLastScreeningDate($value);
        }

        if ($key === 'menopause_status') {
            if (! is_string($value)) {
                return 'unknown';
            }
            $lower = strtolower(trim($value));
            // Questionnaire uses 'post-' / 'post+' — ENUM only allows 'post'.
            if (str_starts_with($lower, 'post')) {
                return 'post';
            }
            return in_array($lower, ['pre', 'post', 'unknown'], true) ? $lower : 'unknown';
        }

        if ($key === 'education_level') {
            $map = [
                'aucun' => 'none',
                'primaire' => 'primary',
                'secondaire' => 'secondary',
                'superieur' => 'university',
                'superieur' => 'university',
                'autodidacte' => 'university',
                'none' => 'none',
                'primary' => 'primary',
                'secondary' => 'secondary',
                'university' => 'university',
            ];

            if (! is_string($value)) {
                return null;
            }

            $lower = strtolower(trim($value));

            return $map[$lower] ?? null;
        }

        if ($type === QuestionType::Boolean) {
            if (is_string($value)) {
                $lower = strtolower(trim($value));
                if (in_array($lower, ['oui', 'yes', 'true', '1'], true)) {
                    return true;
                }
                if (in_array($lower, ['non', 'no', 'false', '0'], true)) {
                    return false;
                }
            }

            return (bool) $value;
        }

        return $value;
    }

    private function normalizeBirthDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || (is_string($value) && preg_match('/^\d{4}$/', trim($value)))) {
            return sprintf('%04d-01-01', (int) $value);
        }

        if (is_string($value)) {
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    private function normalizeLastScreeningDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) && array_key_exists('value', $value)) {
            $value = $value['value'];
        }

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $stringValue = is_int($value) ? (string) $value : trim($value);
        if ($stringValue === '') {
            return null;
        }

        // Questionnaire uses relative buckets here, not literal dates.
        if (in_array($stringValue, ['never', 'over_3_years', 'over_5_year', 'less_than_1_year'], true)) {
            return null;
        }

        if (preg_match('/^\d{4}$/', $stringValue) === 1) {
            return sprintf('%04d-01-01', (int) $stringValue);
        }

        try {
            return Carbon::parse($stringValue)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
