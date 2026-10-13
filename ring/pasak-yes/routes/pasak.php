<?php

use Illuminate\Support\Facades\Route;
use Internal\Pasak\Http\Controllers\Dashboard\BlockedIpController;
use Internal\Pasak\Http\Controllers\Dashboard\LogController;
use Internal\Pasak\Http\Controllers\Dashboard\OverviewController;
use Internal\Pasak\Http\Controllers\Dashboard\ServerController;
use Internal\Pasak\Http\Controllers\Dashboard\SessionController;
use Internal\Pasak\Http\Controllers\Dashboard\SettingController;
use Internal\Pasak\Http\Controllers\Dashboard\TicketController;

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
        Route::get('/', OverviewController::class)->name('overview');
        Route::get('logs', [LogController::class, 'index'])->name('logs');
        Route::get('logs/{id}', [LogController::class, 'show'])->whereNumber('id')->name('logs.show');
        Route::delete('logs', [LogController::class, 'clear'])->name('logs.clear');
        Route::delete('logs/{id}', [LogController::class, 'destroy'])->whereNumber('id')->name('logs.destroy');
        Route::get('blocked-ips', [BlockedIpController::class, 'index'])->name('blocked-ips');
        Route::get('blocked-ips/{id}', [BlockedIpController::class, 'show'])->whereNumber('id')->name('blocked-ips.show');
        Route::post('blocked-ips', [BlockedIpController::class, 'store'])->name('blocked-ips.store');
        Route::patch('blocked-ips/{id}/toggle', [BlockedIpController::class, 'toggle'])->whereNumber('id')->name('blocked-ips.toggle');
        Route::delete('blocked-ips/{id}', [BlockedIpController::class, 'destroy'])->whereNumber('id')->name('blocked-ips.destroy');
        Route::get('server', [ServerController::class, 'index'])->name('server');
        Route::post('server/baseline', [ServerController::class, 'createBaseline'])->name('server.baseline');
        Route::delete('server/baseline', [ServerController::class, 'deleteBaseline'])->name('server.baseline.destroy');
        Route::delete('server/suspicious-files', [ServerController::class, 'deleteSuspiciousFile'])->name('server.suspicious-files.destroy');
        Route::delete('lockouts/{id}', [ServerController::class, 'releaseLockout'])->whereNumber('id')->name('lockouts.destroy');
        Route::get('sessions', [SessionController::class, 'index'])->name('sessions');
        Route::delete('user-sessions/{id}', [SessionController::class, 'destroyLogin'])->whereNumber('id')->name('user-sessions.destroy');
        Route::delete('sessions/{sessionId}', [SessionController::class, 'destroySession'])->name('sessions.destroy');
        Route::delete('trusted-ips/{id}', [SessionController::class, 'destroyTrusted'])->whereNumber('id')->name('trusted-ips.destroy');
        Route::post('trusted-ips/my-ip', [SessionController::class, 'storeMyIp'])->name('trusted-ips.store-my-ip');
        Route::get('tickets', [TicketController::class, 'index'])->name('tickets');
        Route::get('tickets/{id}', [TicketController::class, 'show'])->whereNumber('id')->name('tickets.show');
        Route::post('tickets/{id}/respond', [TicketController::class, 'respond'])->whereNumber('id')->name('tickets.respond');
        Route::delete('tickets/{id}', [TicketController::class, 'destroy'])->whereNumber('id')->name('tickets.destroy');
        Route::get('settings', [SettingController::class, 'index'])->name('settings');
        Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
    });
