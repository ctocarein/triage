<?php

namespace App\Http\Controllers\Evaluation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Evaluation\ThematicBloc;

class ThematicBlocController extends Controller
{
   /**
     * Liste des blocs actifs avec leur groupe associé (pour le frontend).
     */
    public function index()
    {
        $blocs = ThematicBloc::with('questionGroup')->activeOrdered()->get();

        return response()->json([
            'data' => $blocs
        ]);
    }
}
