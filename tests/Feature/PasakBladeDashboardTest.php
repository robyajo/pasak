<?php

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Internal\Pasak\Http\Controllers\Dashboard\AllowedIpController;
use Internal\Pasak\Http\Controllers\Dashboard\OverviewController;
use Internal\Pasak\Http\Controllers\Dashboard\SettingController;
use Internal\Pasak\Models\AllowedIp;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Services\PasakVersionCheckService;

test('dashboard overview renders plain blade views when driver is blade', function () {
    config()->set('pasak.dashboard.driver', 'blade');
    app()->detectEnvironment(fn () => 'local');
    config()->set('app.debug', true);

    // Terbitkan stub Blade murni ke view path bawaan testbench.
    $target = resource_path('views/pages/pasak');
    if (! File::isDirectory($target)) {
        File::copyDirectory(
            dirname(__DIR__, 2).'/stubs/blade/pages/pasak',
            $target
        );
    }

    Route::get('/__pasak_test_overview', fn () => 'ok')->name('pasak.dashboard.overview');

    // Layout navigasi membutuhkan seluruh nama rute dashboard.
    foreach (['logs', 'blocked-ips', 'server', 'sessions', 'tickets', 'settings'] as $page) {
        Route::get('/__pasak_test_'.$page, fn () => 'ok')->name('pasak.dashboard.'.$page);
    }

    try {
        $response = app(OverviewController::class)(
            Request::create('/__pasak_test_overview?range=week'),
            app(PasakService::class)
        );

        expect($response)->toBeInstanceOf(View::class)
            ->and($response->getName())->toBe('pages.pasak.overview');

        $html = $response->render();

        expect($html)->toContain('Security Overview')
            ->and($html)->toContain('sec-stat-card')
            ->and($html)->toContain('<!DOCTYPE html>')
            ->and($html)->toContain('pasakAnnounce');
    } finally {
        File::deleteDirectory($target);
    }
});

test('dashboard allowed-ips page renders with entries', function () {
    config()->set('pasak.dashboard.driver', 'blade');

    $target = resource_path('views/pages/pasak');
    if (! File::isDirectory($target)) {
        File::copyDirectory(
            dirname(__DIR__, 2).'/stubs/blade/pages/pasak',
            $target
        );
    }

    foreach (['overview', 'logs', 'blocked-ips', 'server', 'sessions', 'tickets', 'settings'] as $page) {
        Route::get('/__pasak_test_'.$page, fn () => 'ok')->name('pasak.dashboard.'.$page);
    }
    Route::get('/__pasak_test_allowed', fn () => 'ok')->name('pasak.dashboard.allowed-ips');
    Route::post('/__pasak_test_allowed', fn () => 'ok')->name('pasak.dashboard.allowed-ips.store');
    Route::patch('/__pasak_test_allowed/{id}', fn () => 'ok')->name('pasak.dashboard.allowed-ips.toggle');
    Route::delete('/__pasak_test_allowed/{id}', fn () => 'ok')->name('pasak.dashboard.allowed-ips.destroy');

    try {
        AllowedIp::create([
            'ip_address' => '10.9.0.0/16',
            'label' => 'Test range',
            'is_active' => true,
        ]);

        // Render langsung tanpa middleware web: sediakan $errors manual.
        $this->app['view']->share('errors', new ViewErrorBag);

        $response = app(AllowedIpController::class)->index(
            Request::create('/__pasak_test_allowed')
        );

        expect($response)->toBeInstanceOf(View::class);

        $html = $response->render();

        expect($html)->toContain('Allowed IPs')
            ->and($html)->toContain('10.9.0.0/16')
            ->and($html)->toContain('Test range');
    } finally {
        File::deleteDirectory($target);
    }
});

test('dashboard overview always renders blade views regardless of legacy driver value', function () {
    config()->set('pasak.dashboard.driver', 'react');

    $target = resource_path('views/pages/pasak');
    if (! File::isDirectory($target)) {
        File::copyDirectory(
            dirname(__DIR__, 2).'/stubs/blade/pages/pasak',
            $target
        );
    }

    Route::get('/__pasak_test_overview', fn () => 'ok')->name('pasak.dashboard.overview');

    // Layout navigasi membutuhkan seluruh nama rute dashboard.
    foreach (['logs', 'blocked-ips', 'server', 'sessions', 'tickets', 'settings'] as $page) {
        Route::get('/__pasak_test_'.$page, fn () => 'ok')->name('pasak.dashboard.'.$page);
    }

    try {
        $response = app(OverviewController::class)(
            Request::create('/__pasak_test_overview?range=week'),
            app(PasakService::class)
        );

        // Dashboard kini Blade murni: nilai driver warisan diabaikan.
        expect($response)->toBeInstanceOf(View::class);
    } finally {
        File::deleteDirectory($target);
    }
});

test('dashboard settings page renders per-level thresholds and simulation summary', function () {
    config()->set('pasak.dashboard.driver', 'blade');

    $target = resource_path('views/pages/pasak');
    if (! File::isDirectory($target)) {
        File::copyDirectory(
            dirname(__DIR__, 2).'/stubs/blade/pages/pasak',
            $target
        );
    }

    foreach (['overview', 'logs', 'blocked-ips', 'server', 'sessions', 'tickets', 'settings'] as $page) {
        Route::get('/__pasak_test_'.$page, fn () => 'ok')->name('pasak.dashboard.'.$page);
    }
    Route::post('/__pasak_test_settings', fn () => 'ok')->name('pasak.dashboard.settings.update');

    try {
        app(PasakService::class)->updateSettings([
            'auto_block_scope' => 'auto',
            'auto_block_level_thresholds' => ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 5],
            'auto_block_combined_threshold' => 3,
            'auto_block_enabled' => true,
        ]);

        $response = app(SettingController::class)->index(
            app(PasakVersionCheckService::class)
        );

        $html = $response->render();

        expect($html)->toContain('auto_block_level_thresholds')
            ->and($html)->toContain('value="auto"')
            ->and($html)->toContain('pasak-sim-chips')
            ->and($html)->toContain('data-sec-sim-level="critical"')
            ->and($html)->toContain('auto_block_duration_select')
            ->and($html)->toContain('login_lockout_enabled');
    } finally {
        File::deleteDirectory($target);
    }
});
