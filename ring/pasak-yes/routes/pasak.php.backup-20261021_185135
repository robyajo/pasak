<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pasak Web Dashboard Routes (Plain Blade)
|--------------------------------------------------------------------------
|
| Rute dashboard Blade murni — satu-satunya dashboard Pasak (100% Pure PHP).
| Me-render tampilan di resources/views/pages/pasak tanpa dependensi
| frontend sehingga Anda bebas menyesuaikan tampilan, prefix URL,
| middleware, atau menambah halaman sendiri.
|
*/

$prefix = config('pasak.dashboard.prefix', 'pasak');
$middleware = array_filter((array) config('pasak.dashboard.middleware', ['web', 'auth']));
$adminMiddleware = array_filter((array) config('pasak.dashboard.admin_middleware', []));

Route::middleware(array_merge($middleware, $adminMiddleware))
    ->prefix($prefix)
    ->name('pasak.dashboard.')
    ->group(function () {
        // Dashboard Blade murni via controller (resources/views/pages/pasak).
        Route::get('/', \Internal\Pasak\Http\Controllers\Dashboard\PasakOverviewController::class)->name('overview');
        Route::get('threat-map', [\Internal\Pasak\Http\Controllers\Dashboard\PasakThreatMapController::class, 'index'])->name('threat-map');
        Route::get('threat-map/feed', [\Internal\Pasak\Http\Controllers\Dashboard\PasakThreatMapController::class, 'feed'])->name('threat-map.feed');
        Route::get('logs', [\Internal\Pasak\Http\Controllers\Dashboard\PasakLogController::class, 'index'])->name('logs');
        Route::get('logs/{id}', [\Internal\Pasak\Http\Controllers\Dashboard\PasakLogController::class, 'show'])->whereNumber('id')->name('logs.show');
        Route::delete('logs', [\Internal\Pasak\Http\Controllers\Dashboard\PasakLogController::class, 'clear'])->name('logs.clear');
        Route::delete('logs/{id}', [\Internal\Pasak\Http\Controllers\Dashboard\PasakLogController::class, 'destroy'])->whereNumber('id')->name('logs.destroy');
        Route::get('blocked-ips', [\Internal\Pasak\Http\Controllers\Dashboard\PasakBlockedIpController::class, 'index'])->name('blocked-ips');
        Route::get('blocked-ips/{id}', [\Internal\Pasak\Http\Controllers\Dashboard\PasakBlockedIpController::class, 'show'])->whereNumber('id')->name('blocked-ips.show');
        Route::post('blocked-ips', [\Internal\Pasak\Http\Controllers\Dashboard\PasakBlockedIpController::class, 'store'])->name('blocked-ips.store');
        Route::patch('blocked-ips/{id}/toggle', [\Internal\Pasak\Http\Controllers\Dashboard\PasakBlockedIpController::class, 'toggle'])->whereNumber('id')->name('blocked-ips.toggle');
        Route::delete('blocked-ips/{id}', [\Internal\Pasak\Http\Controllers\Dashboard\PasakBlockedIpController::class, 'destroy'])->whereNumber('id')->name('blocked-ips.destroy');
        Route::get('allowed-ips', [\Internal\Pasak\Http\Controllers\Dashboard\PasakAllowedIpController::class, 'index'])->name('allowed-ips');
        Route::post('allowed-ips', [\Internal\Pasak\Http\Controllers\Dashboard\PasakAllowedIpController::class, 'store'])->name('allowed-ips.store');
        Route::patch('allowed-ips/{id}/toggle', [\Internal\Pasak\Http\Controllers\Dashboard\PasakAllowedIpController::class, 'toggle'])->whereNumber('id')->name('allowed-ips.toggle');
        Route::delete('allowed-ips/{id}', [\Internal\Pasak\Http\Controllers\Dashboard\PasakAllowedIpController::class, 'destroy'])->whereNumber('id')->name('allowed-ips.destroy');
        Route::get('server', [\Internal\Pasak\Http\Controllers\Dashboard\PasakServerController::class, 'index'])->name('server');
        Route::post('server/baseline', [\Internal\Pasak\Http\Controllers\Dashboard\PasakServerController::class, 'createBaseline'])->name('server.baseline');
        Route::delete('server/baseline', [\Internal\Pasak\Http\Controllers\Dashboard\PasakServerController::class, 'deleteBaseline'])->name('server.baseline.destroy');
        Route::delete('server/suspicious-files', [\Internal\Pasak\Http\Controllers\Dashboard\PasakServerController::class, 'deleteSuspiciousFile'])->name('server.suspicious-files.destroy');
        Route::delete('lockouts/{id}', [\Internal\Pasak\Http\Controllers\Dashboard\PasakServerController::class, 'releaseLockout'])->whereNumber('id')->name('lockouts.destroy');
        Route::get('sessions', [\Internal\Pasak\Http\Controllers\Dashboard\PasakSessionController::class, 'index'])->name('sessions');
        Route::delete('user-sessions/{id}', [\Internal\Pasak\Http\Controllers\Dashboard\PasakSessionController::class, 'destroyLogin'])->whereNumber('id')->name('user-sessions.destroy');
        Route::delete('sessions/{sessionId}', [\Internal\Pasak\Http\Controllers\Dashboard\PasakSessionController::class, 'destroySession'])->name('sessions.destroy');
        Route::delete('trusted-ips/{id}', [\Internal\Pasak\Http\Controllers\Dashboard\PasakSessionController::class, 'destroyTrusted'])->whereNumber('id')->name('trusted-ips.destroy');
        Route::post('trusted-ips/my-ip', [\Internal\Pasak\Http\Controllers\Dashboard\PasakSessionController::class, 'storeMyIp'])->name('trusted-ips.store-my-ip');
        Route::get('tickets', [\Internal\Pasak\Http\Controllers\Dashboard\PasakTicketController::class, 'index'])->name('tickets');
        Route::get('tickets/{id}', [\Internal\Pasak\Http\Controllers\Dashboard\PasakTicketController::class, 'show'])->whereNumber('id')->name('tickets.show');
        Route::post('tickets/{id}/respond', [\Internal\Pasak\Http\Controllers\Dashboard\PasakTicketController::class, 'respond'])->whereNumber('id')->name('tickets.respond');
        Route::delete('tickets/{id}', [\Internal\Pasak\Http\Controllers\Dashboard\PasakTicketController::class, 'destroy'])->whereNumber('id')->name('tickets.destroy');
        Route::get('settings', [\Internal\Pasak\Http\Controllers\Dashboard\PasakSettingController::class, 'index'])->name('settings');
        Route::post('settings', [\Internal\Pasak\Http\Controllers\Dashboard\PasakSettingController::class, 'update'])->name('settings.update');
    });
