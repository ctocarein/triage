<?php

use App\Domains\Engagement\Controllers\CampaignController;
use App\Domains\Engagement\Controllers\QrCodeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    require base_path('app/Domains/Engagement/Routes/api.php');
    require base_path('app/Domains/Organization/Routes/api.php');
});

require __DIR__.'/patient/api.php';
