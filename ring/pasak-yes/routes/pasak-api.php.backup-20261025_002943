<?php

use Illuminate\Support\Facades\Route;
use Internal\Pasak\Http\Controllers\Api\PasakAllowedIpApiController;
use Internal\Pasak\Http\Controllers\Api\PasakBlockedIpApiController;
use Internal\Pasak\Http\Controllers\Api\PasakIpUnblockRequestApiController;
use Internal\Pasak\Http\Controllers\Api\PasakSecurityLogApiController;
use Internal\Pasak\Http\Controllers\Api\PasakSecuritySettingApiController;
use Internal\Pasak\Http\Controllers\Api\PasakServerSecurityApiController;
use Internal\Pasak\Http\Controllers\Api\PasakUserSessionApiController;

/*
|--------------------------------------------------------------------------
| Security Monitor Headless REST API Routes
|--------------------------------------------------------------------------
|
| Rute REST API headless untuk Laravel Security Monitor. Anda bebas mengubah
| prefix, middleware, nama rute, atau menambahkan custom endpoint sesuai kebutuhan.
|
*/

// Jika rute ini dimuat dari dalam routes/api.php (grup 'api' sudah aktif),
// sesuaikan prefix agar URL tetap konsisten '/api/security/*'.
$rawPrefix = (string) config('pasak.routes.prefix', 'api/pasak');
$inApiGroup = Route::hasGroupStack() && collect(Route::getGroupStack())->contains(fn ($g) => ($g['prefix'] ?? '') === 'api');
$prefix = $inApiGroup ? (preg_replace('#^/?api/?#', '', $rawPrefix) ?: 'security') : $rawPrefix;
$middleware = (array) config('pasak.routes.middleware', ['api']);
$authMiddleware = (array) config('pasak.routes.auth_middleware', ['auth']);
$adminMiddleware = (array) config('pasak.routes.admin_middleware', [
    'Internal\Pasak\Http\Middleware\PasakEnsureSecurityAdmin',
]);

Route::prefix($prefix)->middleware($middleware)->name('pasak.')->group(function () use ($authMiddleware, $adminMiddleware) {
    // Public Endpoints (Permohonan Banding & Cek Status Tiket)
    Route::post('unblock-tickets/submit', [PasakIpUnblockRequestApiController::class, 'submit'])->name('unblock-tickets.submit');
    Route::get('unblock-tickets/check/{ticketNumber}', [PasakIpUnblockRequestApiController::class, 'checkStatus'])->name('unblock-tickets.check');

    // Authenticated User Endpoints
    Route::middleware($authMiddleware)->group(function () {
        Route::post('trusted-ips/save-my-ip', [PasakUserSessionApiController::class, 'storeMyIp'])->name('trusted-ips.store-my-ip');
    });

    // Admin Protected Endpoints
    Route::middleware(array_merge($authMiddleware, $adminMiddleware))->group(function () {
        // Logs
        Route::get('logs', [PasakSecurityLogApiController::class, 'index'])->name('logs.index');
        Route::delete('logs/clear', [PasakSecurityLogApiController::class, 'clear'])->name('logs.clear');
        Route::delete('logs/{id}', [PasakSecurityLogApiController::class, 'destroy'])->name('logs.destroy');

        // Blocked IPs
        Route::get('blocked-ips', [PasakBlockedIpApiController::class, 'index'])->name('blocked-ips.index');
        Route::post('blocked-ips', [PasakBlockedIpApiController::class, 'store'])->name('blocked-ips.store');
        Route::get('blocked-ips/{id}', [PasakBlockedIpApiController::class, 'show'])->name('blocked-ips.show');
        Route::patch('blocked-ips/{id}/toggle', [PasakBlockedIpApiController::class, 'toggle'])->name('blocked-ips.toggle');
        Route::delete('blocked-ips/{id}', [PasakBlockedIpApiController::class, 'destroy'])->name('blocked-ips.destroy');

        // Allowed IPs (global whitelist)
        Route::get('allowed-ips', [PasakAllowedIpApiController::class, 'index'])->name('allowed-ips.index');
        Route::post('allowed-ips', [PasakAllowedIpApiController::class, 'store'])->name('allowed-ips.store');
        Route::get('allowed-ips/{id}', [PasakAllowedIpApiController::class, 'show'])->name('allowed-ips.show');
        Route::patch('allowed-ips/{id}/toggle', [PasakAllowedIpApiController::class, 'toggle'])->name('allowed-ips.toggle');
        Route::delete('allowed-ips/{id}', [PasakAllowedIpApiController::class, 'destroy'])->name('allowed-ips.destroy');

        // Server Security & Audit
        Route::get('server', [PasakServerSecurityApiController::class, 'index'])->name('server.index');
        Route::post('server/baseline', [PasakServerSecurityApiController::class, 'storeBaseline'])->name('server.baseline');
        Route::delete('server/baseline', [PasakServerSecurityApiController::class, 'destroyBaseline'])->name('server.baseline.destroy');
        Route::delete('server/suspicious-files', [PasakServerSecurityApiController::class, 'destroySuspiciousFile'])->name('server.suspicious-files.destroy');
        Route::delete('lockouts/{id}', [PasakServerSecurityApiController::class, 'releaseLockout'])->name('lockouts.destroy');

        // User Sessions & Trusted IPs
        Route::get('user-sessions', [PasakUserSessionApiController::class, 'index'])->name('user-sessions.index');
        Route::get('user-sessions/realtime', [PasakUserSessionApiController::class, 'realtime'])->name('user-sessions.realtime');
        Route::get('user-sessions/{id}', [PasakUserSessionApiController::class, 'show'])->name('user-sessions.show');
        Route::delete('user-sessions/{id}', [PasakUserSessionApiController::class, 'destroy'])->name('user-sessions.destroy');
        Route::delete('user-sessions/session/{sessionId}', [PasakUserSessionApiController::class, 'destroySession'])->name('user-sessions.destroy-session');
        Route::delete('trusted-ips/{id}', [PasakUserSessionApiController::class, 'destroyTrustedIp'])->name('trusted-ips.destroy');

        // Unblock Tickets Management
        Route::get('unblock-tickets', [PasakIpUnblockRequestApiController::class, 'index'])->name('unblock-tickets.index');
        Route::post('unblock-tickets/{id}/respond', [PasakIpUnblockRequestApiController::class, 'respond'])->name('unblock-tickets.respond');
        Route::delete('unblock-tickets/{id}', [PasakIpUnblockRequestApiController::class, 'destroy'])->name('unblock-tickets.destroy');

        // Security Settings
        Route::get('settings', [PasakSecuritySettingApiController::class, 'index'])->name('settings.index');
        Route::post('settings', [PasakSecuritySettingApiController::class, 'update'])->name('settings.update');
    });
});
