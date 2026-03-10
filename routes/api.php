<?php

use App\Domains\Engagement\Presentation\Http\Controllers\CampaignController;
use App\Domains\Engagement\Presentation\Http\Controllers\QrCodeController;
use App\Domains\Organization\Presentation\Http\Controllers\OrganizationController;
use App\Domains\Ambassador\Presentation\Http\Controllers\ReferralRedirectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('/organizations/default', [OrganizationController::class, 'default']);
Route::get('/organizations/public', [OrganizationController::class, 'publicList']);
Route::get('/r/{code}', [ReferralRedirectController::class, 'handle']);
Route::get('/referrals/expired', function () {
    return response()->file(public_path('referrals/expired.html'), ['Content-Type' => 'text/html'], 410);
});
Route::get('/referrals/used', function () {
    return response()->file(public_path('referrals/used.html'), ['Content-Type' => 'text/html']);
});

Route::options('/{any}', function () {
    return response()->noContent();
})->where('any', '.*');

Route::middleware('auth:sanctum')->group(function () {
    require base_path('app/Domains/Ambassador/Presentation/Http/Routes/ambassador.php');
    require base_path('app/Domains/Engagement/Presentation/Http/Routes/engagement.routes.php');
    require base_path('app/Domains/Organization/Routes/api.php');
});

require __DIR__.'/patient/api.php';
require base_path('app/Domains/Evaluation/Presentation/Http/Routes/admin.routes.php');
