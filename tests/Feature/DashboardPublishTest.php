<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

afterEach(function () {
    $directories = [
        resource_path('views/pages/pasak'),
        resource_path('js/pages/pasak'),
        resource_path('js/components/pasak'),
    ];

    foreach ($directories as $directory) {
        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }
    }
});

test(
    'dashboard is disabled by default so the package stays headless',
    function () {
        expect(config('pasak.dashboard.enabled'))
            ->toBeFalse()
            ->and(config('pasak.dashboard.driver'))
            ->toBe('blade')
            ->and(config('pasak.dashboard.prefix'))
            ->toBe('pasak')
            ->and(config('pasak.dashboard.middleware'))
            ->toContain('auth');
    },
);

test(
    'plain views tag publishes the blade monitoring views',
    function () {
        $directory = resource_path('views/pages/pasak');

        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }

        $this->artisan('vendor:publish', [
            '--tag' => 'plain-views',
            '--force' => true,
        ])->assertSuccessful();

        expect(File::exists($directory.'/overview.blade.php'))
            ->toBeTrue()
            ->and(File::exists($directory.'/logs.blade.php'))
            ->toBeTrue()
            ->and(File::exists($directory.'/blocked-ips.blade.php'))
            ->toBeTrue()
            ->and(File::exists($directory.'/server.blade.php'))
            ->toBeTrue()
            ->and(File::exists($directory.'/sessions.blade.php'))
            ->toBeTrue()
            ->and(File::exists($directory.'/tickets.blade.php'))
            ->toBeTrue()
            ->and(File::exists($directory.'/layout.blade.php'))
            ->toBeTrue();

        expect(File::get($directory.'/overview.blade.php'))->toContain(
            'Security Overview',
        );
    },
);

test(
    'pasak dashboard tag publishes the blade views and configuration',
    function () {
        $directory = resource_path('views/pages/pasak');

        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }

        $this->artisan('vendor:publish', [
            '--tag' => 'pasak-dashboard',
            '--force' => true,
        ])->assertSuccessful();

        expect(File::exists($directory.'/overview.blade.php'))->toBeTrue();
    },
);

test(
    'blade dashboard routes are gated behind authentication',
    function () {
        $routes = File::get(__DIR__.'/../../routes/pasak-dashboard-blade.php');

        expect($routes)
            ->toContain("config('pasak.dashboard.middleware', ['web', 'auth'])")
            ->and($routes)
            ->toContain('OverviewController::class')
            ->and($routes)
            ->toContain("->name('pasak.dashboard.')");

        expect(Route::has('pasak.dashboard.overview'))->toBeFalse();
    },
);

test(
    'security install command can publish the dashboard with the with-dashboard flag',
    function () {
        $directory = resource_path('views/pages/pasak');

        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }

        $this->artisan('pasak:install', [
            '--force' => true,
            '--with-dashboard' => true,
            '--without-nginx' => true,
            '--without-htaccess' => true,
            '--without-env' => true,
        ])->assertSuccessful();

        expect(File::exists($directory.'/overview.blade.php'))->toBeTrue();
    },
);

test(
    'install with dashboard removes react leftovers so only blade exists',
    function () {
        $bladeDir = resource_path('views/pages/pasak');
        $tsxDir = resource_path('js/pages/pasak');
        $componentsDir = resource_path('js/components/pasak');

        File::makeDirectory($tsxDir, 0755, true, true);
        File::makeDirectory($componentsDir, 0755, true, true);
        File::put($tsxDir.'/stale.tsx', 'stale');
        File::put($componentsDir.'/stale.tsx', 'stale');

        $this->artisan('pasak:install', [
            '--force' => true,
            '--with-dashboard' => true,
            '--without-nginx' => true,
            '--without-htaccess' => true,
            '--without-env' => true,
        ])->assertSuccessful();

        expect(File::exists($bladeDir.'/overview.blade.php'))->toBeTrue()
            ->and(File::isDirectory($tsxDir))->toBeFalse()
            ->and(File::isDirectory($componentsDir))->toBeFalse();
    },
);

test(
    'install with api-only removes the dashboard views',
    function () {
        $bladeDir = resource_path('views/pages/pasak');
        $tsxDir = resource_path('js/pages/pasak');

        File::makeDirectory($bladeDir, 0755, true, true);
        File::makeDirectory($tsxDir, 0755, true, true);
        File::put($bladeDir.'/stale.blade.php', 'stale');
        File::put($tsxDir.'/stale.tsx', 'stale');

        $this->artisan('pasak:install', [
            '--force' => true,
            '--api-only' => true,
            '--without-nginx' => true,
            '--without-htaccess' => true,
            '--without-env' => true,
        ])->assertSuccessful();

        expect(File::isDirectory($bladeDir))->toBeFalse()
            ->and(File::isDirectory($tsxDir))->toBeFalse();
    },
);

test(
    'install with dashboard publishes blade-only web routes without inertia or livewire',
    function () {
        $webRouteFile = base_path('routes/pasak.php');
        @unlink($webRouteFile);

        try {
            $this->artisan('pasak:install', [
                '--force' => true,
                '--with-dashboard' => true,
                '--without-nginx' => true,
                '--without-htaccess' => true,
                '--without-env' => true,
            ])->assertSuccessful();

            $routes = File::get($webRouteFile);

            expect($routes)->toContain('OverviewController::class')
                ->and($routes)->toContain('resources/views/pages/pasak')
                ->and($routes)->not->toContain('Inertia\Inertia::class')
                ->and($routes)->not->toContain('Livewire\Livewire::class')
                ->and($routes)->not->toContain('Route::livewire');
        } finally {
            @unlink($webRouteFile);
        }
    },
);
