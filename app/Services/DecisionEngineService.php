<?php

namespace App\Services;

use Carbon\Carbon;

class DecisionEngineService
{
    public function evaluateMammo($account, $age): array
    {
        $last = $account->medicalProfile?->last_screening_date;
        $status = 'not_applicable';
        $message = null;
        $next = null;

        if ($age === null) return compact('status', 'message', 'next');

        if ($age < 35) {
            $status = 'not_applicable';
        } elseif ($age >= 35 && $age < 50) {
            $interval = 24;
        } elseif ($age >= 50 && $age <= 69) {
            $interval = 24;
        } elseif ($age > 69 && $age <= 75) {
            $interval = 36;
        }

        if (isset($interval)) {
            if (!$last) {
                $status = 'never';
                $message = "Aucune mammographie enregistrée. Il est recommandé d'en faire tous les $interval mois.";
            } else {
                $next = Carbon::parse($last)->addMonths($interval);
                if (now()->greaterThanOrEqualTo($next)) {
                    $status = 'due';
                    $message = "Votre mammographie est en retard depuis le " . $next->format('d/m/Y');
                } else {
                    $status = 'ok';
                    $message = "Prochaine mammographie prévue le " . $next->format('d/m/Y');
                }
            }
        }

        return compact('status', 'message', 'next');
    }

    public function evaluatePalpation($account): array
    {
        $last = $account->medicalProfile?->last_auto_palpation_date;
        $menopause = $account->medicalProfile?->menopause_status;
        $status = 'never';
        $next = null;
        $message = null;

        if (!$last) {
            $message = "Vous n'avez pas encore effectué d'auto-palpation.";
        } else {
            $next = Carbon::parse($last)->addMonth();
            if (now()->greaterThanOrEqualTo($next)) {
                $status = 'due';
                $message = "Il est temps de refaire votre auto-examen.";
            } else {
                $status = 'ok';
                $message = "Votre dernier auto-examen est récent.";
            }
        }

        return compact('status', 'message', 'next');
    }

    public function evaluateCol($account, $age): array
    {
        $last = $account->medicalProfile?->last_cervical_screening_date;
        $status = 'not_applicable';
        $message = null;
        $next = null;

        if ($age === null) return compact('status', 'message', 'next');

        if ($age < 22) {
            $status = 'not_applicable';
        } elseif ($age >= 22 && $age < 50) {
            $interval = 36;
        } elseif ($age >= 50) {
            $interval = 60;
        }

        if (isset($interval)) {
            if (!$last) {
                $status = 'never';
                $message = "Aucun frottis enregistré. Il est recommandé d'en faire tous les " . ($interval / 12) . " ans.";
            } else {
                $next = Carbon::parse($last)->addMonths($interval);
                if (now()->greaterThanOrEqualTo($next)) {
                    $status = 'due';
                    $message = "Un nouveau frottis est recommandé depuis le " . $next->format('d/m/Y');
                } else {
                    $status = 'ok';
                    $message = "Dernier frottis encore valable.";
                }
            }
        }

        return compact('status', 'message', 'next');
    }
}
