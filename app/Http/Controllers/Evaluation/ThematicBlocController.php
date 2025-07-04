<?php

namespace App\Http\Controllers\Evaluation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Decisions\DecisionPath;


class ThematicBlocController extends Controller
{
   public function index()
    {
        $thematics = DecisionPath::where('is_active', true)
            ->orderBy('order')
            ->get([
                'id',
                'key',
                'title',
                'description',
                'color',
                'icon',
                'image_url',
                'order',
                'version',
            ]);

        return response()->json([
            'data' => $thematics
        ]);
    }
}
