<?php

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Internal\Pasak\Http\Controllers\Dashboard\OverviewController;
use Internal\Pasak\Services\PasakService;

test('dashboard overview renders plain blade views when driver is blade', function () {
    config()->set('pasak.dashboard.driver', 'blade');

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
            ->and($html)->toContain('<!DOCTYPE html>');
    } finally {
        File::deleteDirectory($target);
    }
});

test('dashboard overview falls back to blade views when inertia is unavailable', function () {
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

        // inertia/inertia-laravel tidak terpasang di testbench: harus fallback ke Blade.
        expect(class_exists(Inertia\Inertia::class))->toBeFalse()
            ->and($response)->toBeInstanceOf(View::class);
    } finally {
        File::deleteDirectory($target);
    }
});
