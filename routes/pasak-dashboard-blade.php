<?php

use Illuminate\Support\Facades\Route;
use Internal\Pasak\Http\Controllers\Dashboard\PasakAllowedIpController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakBlockedIpController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakDashboardAliasController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakDashboardEntryController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakLogController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakOverviewController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakServerController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakSessionController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakSettingController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakThreatMapController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakTicketController;
use Internal\Pasak\Http\Middleware\PasakRotatingDashboard;

/*
|--------------------------------------------------------------------------
| Pasak Plain-Blade Dashboard Routes
|--------------------------------------------------------------------------
|
| Rute dashboard Blade murni (resources/views/pages/pasak) untuk aplikasi
| Laravel tanpa Livewire/Inertia. Seluruh halaman dilindungi oleh
| middleware "web" + "auth" (wajib login).
|
| Mode URL acak sekali pakai (PASAK_DASHBOARD_ROTATING=true):
| pintu stabil "/pasak" 302 ke "/pasak-{token}/" milik sesi pengguna.
| Token diputar setiap akses HTML sehingga reload selalu berubah,
| mis. /pasak-95856857476jdhjs5/logs lalu /pasak-634664873647jbdjshf/logs.
|
*/

$prefix = trim((string) config('pasak.dashboard.prefix', 'pasak'), '/');
if ($prefix === '') {
    $prefix = 'pasak';
}
$middleware = array_filter(
    (array) config('pasak.dashboard.middleware', ['web', 'auth']),
);
$adminMiddleware = array_filter(
    (array) config('pasak.dashboard.admin_middleware', []),
);
$rotating = (bool) config('pasak.dashboard.rotating', false);

$registerPasakDashboard = function () {
    Route::get('/', PasakOverviewController::class)->name('overview');
    Route::get('threat-map', [PasakThreatMapController::class, 'index'])->name(
        'threat-map',
    );
    Route::get('threat-map/feed', [
        PasakThreatMapController::class,
        'feed',
    ])->name('threat-map.feed');
    Route::get('logs', [PasakLogController::class, 'index'])->name('logs');
    Route::get('logs/{id}', [PasakLogController::class, 'show'])
        ->whereNumber('id')
        ->name('logs.show');
    Route::delete('logs', [PasakLogController::class, 'clear'])->name(
        'logs.clear',
    );
    Route::delete('logs/{id}', [PasakLogController::class, 'destroy'])
        ->whereNumber('id')
        ->name('logs.destroy');
    Route::get('blocked-ips', [PasakBlockedIpController::class, 'index'])->name(
        'blocked-ips',
    );
    Route::get('blocked-ips/{id}', [PasakBlockedIpController::class, 'show'])
        ->whereNumber('id')
        ->name('blocked-ips.show');
    Route::post('blocked-ips', [
        PasakBlockedIpController::class,
        'store',
    ])->name('blocked-ips.store');
    Route::patch('blocked-ips/{id}/toggle', [
        PasakBlockedIpController::class,
        'toggle',
    ])
        ->whereNumber('id')
        ->name('blocked-ips.toggle');
    Route::delete('blocked-ips/{id}', [
        PasakBlockedIpController::class,
        'destroy',
    ])
        ->whereNumber('id')
        ->name('blocked-ips.destroy');
    Route::get('allowed-ips', [PasakAllowedIpController::class, 'index'])->name(
        'allowed-ips',
    );
    Route::post('allowed-ips', [
        PasakAllowedIpController::class,
        'store',
    ])->name('allowed-ips.store');
    Route::patch('allowed-ips/{id}/toggle', [
        PasakAllowedIpController::class,
        'toggle',
    ])
        ->whereNumber('id')
        ->name('allowed-ips.toggle');
    Route::delete('allowed-ips/{id}', [
        PasakAllowedIpController::class,
        'destroy',
    ])
        ->whereNumber('id')
        ->name('allowed-ips.destroy');
    Route::get('server', [PasakServerController::class, 'index'])->name(
        'server',
    );
    Route::post('server/baseline', [
        PasakServerController::class,
        'createBaseline',
    ])->name('server.baseline');
    Route::delete('server/baseline', [
        PasakServerController::class,
        'deleteBaseline',
    ])->name('server.baseline.destroy');
    Route::delete('server/suspicious-files', [
        PasakServerController::class,
        'deleteSuspiciousFile',
    ])->name('server.suspicious-files.destroy');
    Route::delete('lockouts/{id}', [
        PasakServerController::class,
        'releaseLockout',
    ])
        ->whereNumber('id')
        ->name('lockouts.destroy');
    Route::get('sessions', [PasakSessionController::class, 'index'])->name(
        'sessions',
    );
    Route::delete('user-sessions/{id}', [
        PasakSessionController::class,
        'destroyLogin',
    ])
        ->whereNumber('id')
        ->name('user-sessions.destroy');
    Route::delete('sessions/{sessionId}', [
        PasakSessionController::class,
        'destroySession',
    ])->name('sessions.destroy');
    Route::delete('trusted-ips/{id}', [
        PasakSessionController::class,
        'destroyTrusted',
    ])
        ->whereNumber('id')
        ->name('trusted-ips.destroy');
    Route::post('trusted-ips/my-ip', [
        PasakSessionController::class,
        'storeMyIp',
    ])->name('trusted-ips.store-my-ip');
    Route::get('tickets', [PasakTicketController::class, 'index'])->name(
        'tickets',
    );
    Route::get('tickets/{id}', [PasakTicketController::class, 'show'])
        ->whereNumber('id')
        ->name('tickets.show');
    Route::post('tickets/{id}/respond', [
        PasakTicketController::class,
        'respond',
    ])
        ->whereNumber('id')
        ->name('tickets.respond');
    Route::delete('tickets/{id}', [PasakTicketController::class, 'destroy'])
        ->whereNumber('id')
        ->name('tickets.destroy');
    Route::get('settings', [PasakSettingController::class, 'index'])->name(
        'settings',
    );
    Route::post('settings', [PasakSettingController::class, 'update'])->name(
        'settings.update',
    );
};

if ($rotating) {
    // Pintu stabil untuk bookmark: /pasak → 302 → /pasak-{token}/
    Route::middleware(array_merge($middleware, $adminMiddleware))
        ->get($prefix, PasakDashboardEntryController::class)
        ->name('pasak.dashboard.entry');

    Route::middleware(
        array_merge($middleware, $adminMiddleware, [
            PasakRotatingDashboard::class,
        ]),
    )
        ->prefix('{pasakSlug}')
        ->where([
            'pasakSlug' => preg_quote($prefix, '/').'-[A-Za-z0-9]{12,32}',
        ])
        ->name('pasak.dashboard.')
        ->group($registerPasakDashboard);

    // Alias stabil: /pasak/logs → 302 → /pasak-{token}/logs. Menjaga bookmark,
    // link layout host, dan route() di luar dashboard tetap hidup.
    Route::middleware(array_merge($middleware, $adminMiddleware))
        ->prefix($prefix)
        ->group(function () {
            Route::get('{any}', PasakDashboardAliasController::class)->where(
                'any',
                '.*',
            );
        });
} else {
    Route::middleware(array_merge($middleware, $adminMiddleware))
        ->prefix($prefix)
        ->name('pasak.dashboard.')
        ->group($registerPasakDashboard);
}
