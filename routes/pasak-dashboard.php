<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Security Monitor Livewire Dashboard Routes
|--------------------------------------------------------------------------
|
| Rute ini hanya dimuat ketika "security.dashboard.enabled" bernilai true dan
| paket Livewire tersedia di aplikasi host. Seluruh halaman dilindungi oleh
| middleware autentikasi ("auth") dan, secara default, juga oleh middleware
| "security.admin" yang memeriksa Gate "manage-security-monitor".
|
*/

$prefix = config('pasak.dashboard.prefix', 'security');
$middleware = array_filter((array) config('pasak.dashboard.middleware', ['web', 'auth']));
$adminMiddleware = array_filter((array) config('pasak.dashboard.admin_middleware', []));

Route::middleware(array_merge($middleware, $adminMiddleware))
    ->prefix($prefix)
    ->name('security.dashboard.')
    ->group(function () {
        Route::livewire('/', 'pages::pasak.overview')->name('overview');
        Route::livewire('logs', 'pages::pasak.logs')->name('logs');
        Route::livewire('blocked-ips', 'pages::pasak.blocked-ips')->name('blocked-ips');
        Route::livewire('server', 'pages::pasak.server')->name('server');
        Route::livewire('sessions', 'pages::pasak.sessions')->name('sessions');
        Route::livewire('tickets', 'pages::pasak.tickets')->name('tickets');
        Route::livewire('settings', 'pages::pasak.settings')->name('settings');
    });
