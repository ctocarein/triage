<?php

namespace App\Domains\Evaluation\Controllers\Patient;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Domains\Evaluation\Models\ThematicBloc;

// Blocs internes au parcours — non affichés comme suggestions
const SYSTEM_BLOC_KEYS = [
    'consent_declined',
    'greeting',
    'emergency_screen',
    'dispatch_intention',
    'patient_context',
    'patient_demographics',
];

class ThematicBlocController extends Controller
{
    public function index()
    {
        $thematics = ThematicBloc::where('is_active', true)
            ->whereNotIn('key', SYSTEM_BLOC_KEYS)
            ->whereNotIn('intention', ['crisis', 'triage'])
            ->orderByDesc('priority')
            ->orderBy('order')
            ->get(['key', 'label', 'description', 'icon', 'color', 'intention', 'priority'])
            ->map(fn($bloc) => [
                'key'         => $bloc->key,
                'title'       => $bloc->label,
                'description' => $bloc->description,
                'icon'        => $bloc->icon,
                'color'       => $bloc->color,
                'intention'   => $bloc->intention?->value,
                'priority'    => $bloc->priority,
            ]);

        return response()->json([
            'data' => $thematics
        ]);
    }
}



