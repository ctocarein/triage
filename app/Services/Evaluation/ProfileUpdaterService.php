<?php

namespace App\Services\Evaluation;

use App\Models\Evaluation\EvaluationSession;
use App\Models\PersonalProfile;
use App\Models\MedicalProfile;

class ProfileUpdaterService
{
    public function syncFromAnswers(EvaluationSession $session): void
    {
        $answers = $session->answers()->with('question')->get();

        $personalData = [];
        $medicalData = [];

        foreach ($answers as $answer) {
            $key = $answer->question->key;
            $target = $answer->question->answer_target;

            if ($target === 'personal_profile') {
                $personalData[$key] = $this->formatValue($answer->value);
            }

            if ($target === 'medical_profile') {
                $medicalData[$key] = $this->formatValue($answer->value);
            }
        }

        // Synchroniser le profil personnel
        if (!empty($personalData)) {
            $profile = $session->account->personalProfile ?? new PersonalProfile([
                'account_id' => $session->account_id
            ]);
            $profile->fill($personalData)->save();
        }

        // Synchroniser le profil médical
        if (!empty($medicalData)) {
            $profile = $session->account->medicalProfile ?? new MedicalProfile([
                'account_id' => $session->account_id
            ]);
            $profile->fill($medicalData)->save();
        }
    }

    private function formatValue($value)
    {
        // Adaptation simple si le champ est un tableau ou une valeur simple
        if (is_array($value) && count($value) === 1) {
            return $value[0];
        }
        return is_array($value) ? json_encode($value) : $value;
    }
}
