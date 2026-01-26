<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Models\EvaluationSession;

class BlocRulesService
{
    /**
     * Détermine si un bloc doit être sauté selon le profil du patient.
     */
    public function shouldSkip(string $blocKey, EvaluationSession $session): bool
    {
        $birthDate = $session->identity->personalProfile?->birth_date;
        $age = $birthDate ? $this->calculateAge($birthDate) : null;

        return match ($blocKey) {
            'symptom_breast' => $age !== null && $age < 25,
            'symptom_cervix' => $age !== null && $age < 22,
            // ➕ Ajouter d’autres règles spécifiques ici
            default => false,
        };
    }

    /**
     * Calcule l’âge à partir d’une date de naissance au format `d-m-Y`.
     */
    protected function calculateAge(string $birthDate): ?int
    {
        try {
            $dob = \DateTime::createFromFormat('d-m-Y', $birthDate);
            return $dob ? (new \DateTime())->diff($dob)->y : null;
        } catch (\Exception) {
            return null;
        }
    }
}
