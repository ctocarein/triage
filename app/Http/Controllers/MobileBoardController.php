<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\MobileBoardService;

class MobileBoardController extends Controller
{
    public function __invoke(Request $request, MobileBoardService $service)
    {
        $account = $request->user();
        $data = $service->buildDashboard($account);

        return response()->json($data);
    }
}
