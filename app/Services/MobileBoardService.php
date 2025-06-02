<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Capsule\Capsule;
use App\Models\Report;
use App\Services\DecisionEngineService;
use App\Models\Evaluation\EvaluationSession;


class MobileBoardService
{
    public function buildDashboard($account): array
    {
        // ✅ Ne pas re-demander $request->user()
        if (!$account) {
            throw new \Exception('Account not provided');
        }
        // ✅ Vérifier si l'utilisateur a un profil personnel

        if ($account->personalProfile?->getAgeAttribute() == null && !$account->personalProfile?->menopause_status) {
    
            return ['message' => "Veuillez compléter votre profil personnel"];
        }
            $age = $account->personalProfile?->getAgeAttribute();
            $profile = $account->medicalProfile?->menopause_status ? 'menopause' : 'cycle_regular';
            
             $capsules = Capsule::with('items')
            ->where('is_active', true)
            ->where('published_at', '<=', now())
            ->when($age, fn($q) =>
                $q->where(function ($q) use ($age) {
                    $q->whereNull('target_age_min')->orWhere('target_age_min', '<=', $age);
                })->where(function ($q) use ($age) {
                    $q->whereNull('target_age_max')->orWhere('target_age_max', '>=', $age);
                })
            )
            ->where(function ($q) use ($profile) {
                $q->where('audience_profile', 'all')->orWhere('audience_profile', $profile);
            })
            ->orderByDesc('sponsor_priority')
            ->latest('published_at')
            ->take(6)
            ->get();
 

        // ✅ Profils
        $profileData = $account->personalProfile;
        $medical = $account->medicalProfile;
        $age = $profileData?->birth_date ? Carbon::parse($profileData->birth_date)->age : null;

        // ✅ Évaluation intelligente
        $mammo = app(DecisionEngineService::class)->evaluateMammo($account, $age);
        $palpation = app(DecisionEngineService::class)->evaluatePalpation($account);
        $col = app(DecisionEngineService::class)->evaluateCol($account, $age);

        // ✅ Recommandations
        $reports = Report::whereHas('evaluationSession', function($query) use ($account) {
            $query->where('account_id', $account->id);
        })
        ->latest()
        ->take(5)
        ->get();

        return [
            'mammo' => $mammo,
            'palpation' => $palpation,
            'col' => $col,
            'reports' => $reports,
            'capsules' => $capsules,
        ];
    }
}
