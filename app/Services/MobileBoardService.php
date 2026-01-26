<?php

namespace App\Services;

use Carbon\Carbon;
use App\Domains\Report\Models\Report;
use App\Domains\Recommendation\Models\Capsule;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Services\Decisions\DecisionEngineService;

class MobileBoardService
{
    public function buildDashboard($identity): array
    {
        if (!$identity) {
            throw new \Exception('Identity not provided');
        }

        // Vérifier si l'utilisateur a un profil personnel
        if ($identity->personalProfile?->getAgeAttribute() == null && !$identity->personalProfile?->menopause_status) {
            return ['message' => "Veuillez compléter votre profil personnel"];
        }

        $age = $identity->personalProfile?->getAgeAttribute();
        $profile = $identity->medicalProfile?->menopause_status ? 'menopause' : 'cycle_regular';

        $capsules = Capsule::with('items')
            ->where('is_active', true)
            ->orderByDesc('sponsor_priority')
            ->latest('published_at')
            ->take(6)
            ->get();

        // Evaluation Status
        $hasCompletedFull = $identity->evaluationSessions()
            ->where('type', 'complete')
            ->where('status', 'completed')
            ->exists();

        $profileData = $identity->personalProfile;
        $medical = $identity->medicalProfile;
        $age = $profileData?->birth_date ? Carbon::parse($profileData->birth_date)->age : null;

        $mammo = app(DecisionEngineService::class)->evaluateMammo($identity, $age);
        $palpation = app(DecisionEngineService::class)->evaluatePalpation($identity);
        $col = app(DecisionEngineService::class)->evaluateCol($identity, $age);

        $reports = Report::whereHas('evaluationSession', function($query) use ($identity) {
            $query->where('identity_id', $identity->id);
        })
        ->latest()
        ->take(5)
        ->get();

        // ✅ Liste des recentes évaluations
        $groupLabels = QuestionGroup::pluck('label', 'key');

        $sessions = $identity->evaluationSessions()
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
