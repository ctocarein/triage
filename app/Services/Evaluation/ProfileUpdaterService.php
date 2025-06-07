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
            // On ignore les réponses aux questions qui n'ont pas de cible
            $target = $answer->question->answer_target;
            $key = $answer->question->key;
             $value = $this->castValue($answer->question->type, $answer->value);

            if ($target === 'personal_profile') {
                $personalData[$key] = $value;
            }

            if ($target === 'medical_profile') {
                $medicalData[$key] = $value;
            }
            $account = $session->account;

            if (!empty($personalData)) {
                $account->personalProfile()->updateOrCreate([], $personalData);
            }

            if (!empty($medicalData)) {
                $account->medicalProfile()->updateOrCreate([], $medicalData);
            }
        }

            // On récupère les profils liés à l’utilisateur
            $account = $session->account;

            if (!empty($personalData)) {
                $account->personalProfile()->updateOrCreate([], $personalData);
            }

            if (!empty($medicalData)) {
                $account->medicalProfile()->updateOrCreate([], $medicalData);
            }
}

    private function castValue(string $type, $value)
    {
        if ($type === 'boolean') {
            if (is_string($value)) {
                $lower = strtolower($value);
                if (in_array($lower, ['oui', 'yes', 'true', '1'])) {
                    return true;
                }
                if (in_array($lower, ['non', 'no', 'false', '0'])) {
                    return false;
                }
            }

            return (bool) $value;
        }

        return $value;
    }

}
