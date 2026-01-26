<?php

namespace App\Domains\Recommendation\Controllers\Patient;

use Illuminate\Http\Request;
use App\Domains\Recommendation\Models\Capsule;
use App\Http\Controllers\Controller;


class CapsuleController extends Controller
{
    /**
     * Liste des capsules visibles pour l'utilisatrice connectÃ©e.
     */
    public function index(Request $request)
    {
        $account = $request->user();
        $age = $account?->personalProfile?->age();
        $profile = $account?->medicalProfile?->menopause_status ? 'menopause' : 'cycle_regular';

        $capsules = Capsule::with('items')
            ->where('is_active', true)
            ->where('published_at', '<=', now())
            // ->when($age, fn($q) =>
            //     $q->where(function ($q) use ($age) {
            //         $q->whereNull('target_age_min')->orWhere('target_age_min', '<=', $age);
            //     })->where(function ($q) use ($age) {
            //         $q->whereNull('target_age_max')->orWhere('target_age_max', '>=', $age);
            //     })
            // )
            // ->where(function ($q) use ($profile) {
            //     $q->where('audience_profile', 'all')->orWhere('audience_profile', $profile);
            // })
            ->orderByDesc('sponsor_priority')
            ->latest('published_at')
            ->take(10)
            ->get();

        return response()->json($capsules);
    }

    /**
     * Affiche une capsule spÃ©cifique (dÃ©tail + items).
     */
    
    public function show($id)
    {
        $capsule = Capsule::with('items')->findOrFail($id);
        return response()->json($capsule);
    }
}



