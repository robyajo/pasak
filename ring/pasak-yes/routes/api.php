<?php

use App\Http\Controllers\Api\KeamananController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Custom API Keamanan Host Application (JSON)
Route::prefix('keamanan')->name('api.keamanan.')->group(function () {
    Route::get('/', [KeamananController::class, 'index'])->name('index');
    Route::get('/logs', [KeamananController::class, 'logs'])->name('logs');
    Route::get('/blocked-ips', [KeamananController::class, 'blockedIps'])->name('blocked-ips');
    Route::get('/check/{ip}', [KeamananController::class, 'checkIp'])->name('check');
    Route::post('/block', [KeamananController::class, 'blockIp'])->name('block');
    Route::post('/unblock/{ip}', [KeamananController::class, 'unblockIp'])->name('unblock');
    Route::delete('/logs', [KeamananController::class, 'clearLogs'])->name('logs.clear');
});

require __DIR__.'/pasak-api.php';
