<?php

use App\Domains\Engagement\Controllers\CampaignController;
use App\Domains\Engagement\Controllers\QrCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:PlatformAdmin,OrgManager')->group(function () {
    Route::get('/qr-codes', [QrCodeController::class, 'index']);
    Route::post('/qr-codes', [QrCodeController::class, 'store']);
    Route::get('/qr-codes/{qrCode}', [QrCodeController::class, 'show']);
    Route::put('/qr-codes/{qrCode}', [QrCodeController::class, 'update']);
    Route::delete('/qr-codes/{qrCode}', [QrCodeController::class, 'destroy']);
    Route::post('/qr-codes/{qrCode}/validate', [QrCodeController::class, 'validateQr']);
    Route::post('/qr-codes/{qrCode}/revoke', [QrCodeController::class, 'revoke']);
    Route::get('/qr-codes/{qrCode}/stats', [QrCodeController::class, 'stats']);
    Route::get('/qr-codes/{qrCode}/download', [QrCodeController::class, 'download']);

    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update']);
    Route::post('/campaigns/{campaign}/archive', [CampaignController::class, 'archive']);
    Route::get('/campaigns/{campaign}/report', [CampaignController::class, 'report']);
});
