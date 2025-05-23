<?php

namespace App\Http\Controllers\Capsule;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Capsule;


class CapsuleController extends Controller
{
    /**
     * Liste des capsules visibles pour l'utilisatrice connectée.
     */
    public function index(Request $request)
    {
        $account = $request->user();
        $age = $account?->personalProfile?->age();
        $profile = $account?->medicalProfile?->menopause_status ? 'menopause' : 'cycle_regular';

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
            ->take(10)
            ->get();

        return response()->json($capsules);
    }

    /**
     * Affiche une capsule spécifique (détail + items).
     */
    
    public function show($id)
    {
        $capsule = Capsule::with('items')->findOrFail($id);
        return response()->json($capsule);
    }
}
