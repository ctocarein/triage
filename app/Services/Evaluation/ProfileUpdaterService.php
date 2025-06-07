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
            $value = $answer->value;

            if ($target === 'personal_profile') {
                $personalData[$key] = $value;
            }

            if ($target === 'medical_profile') {
                $medicalData[$key] = $value;
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

}
