<?php

use App\Http\Controllers\Api\KeamananController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Custom API Keamanan Host Application (JSON)
Route::prefix('keamanan')->name('api.keamanan.')->group(function () {
    // 1. Overview
    Route::get('/', [KeamananController::class, 'index'])->name('index');

    // 2. Logs
    Route::get('logs', [KeamananController::class, 'logs'])->name('logs');
    Route::get('logs/{id}', [KeamananController::class, 'showLog'])->name('logs.show');
    Route::delete('logs', [KeamananController::class, 'clearLogs'])->name('logs.clear');
    Route::delete('logs/{id}', [KeamananController::class, 'destroyLog'])->name('logs.destroy');

    // 3. Blocked IPs
    Route::get('blocked-ips', [KeamananController::class, 'blockedIps'])->name('blocked-ips');
    Route::get('check/{ip}', [KeamananController::class, 'checkIp'])->name('check');
    Route::post('block', [KeamananController::class, 'blockIp'])->name('block');
    Route::post('unblock/{ip}', [KeamananController::class, 'unblockIp'])->name('unblock');
    Route::patch('blocked-ips/{id}/toggle', [KeamananController::class, 'toggleBlockedIp'])->name('blocked-ips.toggle');
    Route::delete('blocked-ips/{id}', [KeamananController::class, 'destroyBlockedIp'])->name('blocked-ips.destroy');

    // 4. Allowed IPs (Whitelist)
    Route::get('allowed-ips', [KeamananController::class, 'allowedIps'])->name('allowed-ips');
    Route::post('allowed-ips', [KeamananController::class, 'storeAllowedIp'])->name('allowed-ips.store');
    Route::patch('allowed-ips/{id}/toggle', [KeamananController::class, 'toggleAllowedIp'])->name('allowed-ips.toggle');
    Route::delete('allowed-ips/{id}', [KeamananController::class, 'destroyAllowedIp'])->name('allowed-ips.destroy');

    // 5. Server Security
    Route::get('server', [KeamananController::class, 'serverReport'])->name('server.report');
    Route::post('server/baseline', [KeamananController::class, 'createBaseline'])->name('server.baseline');
    Route::delete('server/baseline', [KeamananController::class, 'deleteBaseline'])->name('server.baseline.destroy');
    Route::delete('server/suspicious-files', [KeamananController::class, 'deleteSuspiciousFile'])->name('server.suspicious-files.destroy');
    Route::delete('lockouts/{id}', [KeamananController::class, 'releaseLockout'])->name('lockouts.destroy');

    // 6. User Sessions & Trusted IPs
    Route::get('sessions', [KeamananController::class, 'sessions'])->name('sessions');
    Route::delete('sessions/{id}', [KeamananController::class, 'terminateSession'])->name('sessions.terminate');
    Route::post('trusted-ips', [KeamananController::class, 'storeTrustedIp'])->name('trusted-ips.store');
    Route::post('trusted-ips/my-ip', [KeamananController::class, 'storeTrustedIp'])->name('trusted-ips.my-ip');
    Route::delete('trusted-ips/{id}', [KeamananController::class, 'destroyTrustedIp'])->name('trusted-ips.destroy');

    // 7. Tickets
    Route::get('tickets', [KeamananController::class, 'tickets'])->name('tickets');
    Route::get('tickets/{id}', [KeamananController::class, 'showTicket'])->name('tickets.show');
    Route::post('tickets/{id}/respond', [KeamananController::class, 'respondTicket'])->name('tickets.respond');
    Route::delete('tickets/{id}', [KeamananController::class, 'destroyTicket'])->name('tickets.destroy');

    // 8. Settings
    Route::get('settings', [KeamananController::class, 'settings'])->name('settings');
    Route::match(['post', 'put'], 'settings', [KeamananController::class, 'updateSettings'])->name('settings.update');
});

require __DIR__.'/pasak-api.php';
