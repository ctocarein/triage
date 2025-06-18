<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Capsule\Capsule;
use App\Models\Report;
use App\Models\Evaluation\QuestionGroup;
use App\Services\DecisionEngineService;
use App\Models\Evaluation\EvaluationSession;

class MobileBoardService
{
    public function buildDashboard($account): array
    {
        if (!$account) {
            throw new \Exception('Account not provided');
        }

        // Vérifier si l'utilisateur a un profil personnel
        if ($account->personalProfile?->getAgeAttribute() == null && !$account->personalProfile?->menopause_status) {
            return ['message' => "Veuillez compléter votre profil personnel"];
        }

        $age = $account->personalProfile?->getAgeAttribute();
        $profile = $account->medicalProfile?->menopause_status ? 'menopause' : 'cycle_regular';

        $capsules = Capsule::with('items')
            ->where('is_active', true)
            ->orderByDesc('sponsor_priority')
            ->latest('published_at')
            ->take(6)
            ->get();

        // Evaluation Status
        $hasCompletedFull = $account->evaluationSessions()
            ->where('type', 'complete')
            ->where('status', 'completed')
            ->exists();

        $profileData = $account->personalProfile;
        $medical = $account->medicalProfile;
        $age = $profileData?->birth_date ? Carbon::parse($profileData->birth_date)->age : null;

        $mammo = app(DecisionEngineService::class)->evaluateMammo($account, $age);
        $palpation = app(DecisionEngineService::class)->evaluatePalpation($account);
        $col = app(DecisionEngineService::class)->evaluateCol($account, $age);

        $reports = Report::whereHas('evaluationSession', function($query) use ($account) {
            $query->where('account_id', $account->id);
        })
        ->latest()
        ->take(5)
        ->get();

        // ✅ Liste des recentes évaluations
        $groupLabels = QuestionGroup::pluck('label', 'key');

        $sessions = $account->evaluationSessions()
            ->withCount('answers')
            ->with('report')
            ->latest()
            ->take(3)
            ->get()
            ->map(function ($session) use ($groupLabels) {
                $thematics = $session->target_blocs
                    ? collect($session->target_blocs)->map(fn($k) => $groupLabels[$k] ?? $k)->toArray()
                    : null;
                return [
                    'id' => $session->id,
                    'type' => $session->type,
                    'status' => $session->status,
                    'started_at' => $session->started_at,
                    'completed_at' => $session->completed_at,
                    'target_blocs' => $session->target_blocs,
                    'thematics' => $thematics,
                    'title' => $session->type === 'complete'
                        ? "Évaluation complète"
                        : "Éval. thématique : " . ($thematics ? implode(', ', $thematics) : ''),
                    'answers_count' => $session->answers_count ?? $session->answers()->count(),
                    'report_id' => $session->report->id ?? null,
                ];
            });

        return [
            'mammo' => $mammo,
            'palpation' => $palpation,
            'col' => $col,
            'reports' => $reports,
            'capsules' => $capsules,
            'profile' => $profileData ? [
                'id' => $profileData->id,
                'first_name' => $profileData->first_name,
                'age' => $age,
                'menopause_status' => $medical?->menopause_status,
            ] : null,
            'has_completed_full' => $hasCompletedFull,
            'recent_sessions' => $sessions,
        ];
    }
}
