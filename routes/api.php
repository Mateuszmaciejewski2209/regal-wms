<?php

use App\Http\Controllers\Api\ScannerController;
use App\Http\Middleware\AuthenticateScannerDevice;
use Illuminate\Support\Facades\Route;

/*
| API mobilnego skanera. Telefon wysyła każdy skan jako żądanie HTTP (webhook),
| a serwer rozgłasza wynik do panelu przez WebSocket (Laravel Reverb).
*/

Route::prefix('scanner')->name('api.scanner.')->group(function () {
    Route::post('pair', [ScannerController::class, 'pair'])
        ->middleware('throttle:scanner-pair')
        ->name('pair');

    Route::middleware([AuthenticateScannerDevice::class, 'throttle:scanner'])->group(function () {
        Route::get('me', [ScannerController::class, 'me'])->name('me');
        Route::get('documents', [ScannerController::class, 'documents'])->name('documents');
        Route::post('target', [ScannerController::class, 'target'])->name('target');
        Route::post('scans', [ScannerController::class, 'scan'])->name('scans');
        Route::delete('session', [ScannerController::class, 'unpair'])->name('unpair');
    });
});
