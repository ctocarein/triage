<?php

namespace App\Services\Decisions;

use Carbon\Carbon;

class DecisionEngineService
{
    protected array $mammoRules = [
        ['min_age' => 35, 'max_age' => 49, 'interval_months' => 24],
        ['min_age' => 50, 'max_age' => 69, 'interval_months' => 24],
        ['min_age' => 70, 'max_age' => 75, 'interval_months' => 36],
    ];

    protected array $colRules = [
        ['min_age' => 22, 'max_age' => 49, 'interval_months' => 36],
        ['min_age' => 50, 'max_age' => 120, 'interval_months' => 60],
    ];

    public function evaluateScreening($account, $age, $last, array $rules, $label = 'examen')
    {
        $status = 'not_applicable';
        $message = null;
        $next = null;
        $interval = null;

        if ($age === null) return compact('status', 'message', 'next');

        foreach ($rules as $rule) {
            if ($age >= $rule['min_age'] && $age <= $rule['max_age']) {
                $interval = $rule['interval_months'];
                break;
            }
        }

        if ($interval !== null) {
            if (!$last) {
                $status = 'never';
                $message = "Aucun $label enregistré. Il est recommandé d'en faire tous les $interval mois.";
            } else {
                $next = Carbon::parse($last)->addMonths($interval);
                if (now()->greaterThanOrEqualTo($next)) {
                    $status = 'due';
                    $message = "Votre $label est en retard depuis le " . $next->format('d/m/Y');
                } else {
                    $status = 'ok';
                    $message = "Prochain $label prévu le " . $next->format('d/m/Y');
                }
            }
        }

        return compact('status', 'message', 'next');
    }

    public function evaluateMammo($account, $age): array
    {
        $last = $account->medicalProfile?->last_screening_date;
        return $this->evaluateScreening($account, $age, $last, $this->mammoRules, 'mammographie');
    }

    public function evaluateCol($account, $age): array
    {
        $last = $account->medicalProfile?->last_cervical_screening_date;
        return $this->evaluateScreening($account, $age, $last, $this->colRules, 'frottis');
    }
}

