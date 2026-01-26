<?php

namespace App\Domains\Evaluation\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\MobileBoardService;

class MobileBoardController extends Controller
{
    public function __invoke(Request $request, MobileBoardService $service)
    {
        $identity = $request->user();
        $data = $service->buildDashboard($identity);

        return response()->json($data);
    }
}



