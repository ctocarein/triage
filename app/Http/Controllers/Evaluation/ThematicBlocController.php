<?php

namespace App\Http\Controllers\Evaluation;

use App\Models\ThematicBloc;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

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
