<?php

use Illuminate\Support\Facades\File;
use Internal\Pasak\Console\Commands\SecurityInstallCommand;

test('install command registers wizard options', function () {
    $definition = $this->app->make(SecurityInstallCommand::class)->getDefinition();

    expect($definition->hasOption('lang'))->toBeTrue()
        ->and($definition->hasOption('api-only'))->toBeTrue()
        ->and($definition->hasOption('with-blade'))->toBeTrue()
        ->and($definition->hasOption('with-tsx'))->toBeTrue()
        ->and($definition->hasOption('with-both'))->toBeTrue()
        ->and($definition->hasOption('stack'))->toBeTrue();
});

test('install with api-only publishes api routes but no web pages', function () {
    $webRouteFile = base_path('routes/pasak.php');
    $apiRouteFile = base_path('routes/pasak-api.php');
    @unlink($webRouteFile);
    @unlink($apiRouteFile);

    try {
        $this->artisan('pasak:install', [
            '--api-only' => true,
            '--force' => true,
        ])->assertSuccessful();

        expect(File::exists($apiRouteFile))->toBeTrue()
            ->and(File::exists($webRouteFile))->toBeFalse();
    } finally {
        @unlink($webRouteFile);
        @unlink($apiRouteFile);
    }
});

test('install does not duplicate route require lines on re-run', function () {
    $apiRoutesFile = base_path('routes/api.php');
    $hadApiRoutes = File::exists($apiRoutesFile);
    $original = $hadApiRoutes ? File::get($apiRoutesFile) : null;

    try {
        $this->artisan('pasak:install', ['--force' => true])->assertSuccessful();
        $this->artisan('pasak:install', ['--force' => true])->assertSuccessful();

        if (File::exists($apiRoutesFile)) {
            expect(substr_count(File::get($apiRoutesFile), "require __DIR__.'/pasak-api.php';"))->toBe(1);
        }

        $webRoutesFile = base_path('routes/web.php');
        if (File::exists($webRoutesFile)) {
            expect(substr_count(File::get($webRoutesFile), "require __DIR__.'/pasak.php';"))->toBe(1);
        }
    } finally {
        if ($hadApiRoutes) {
            File::put($apiRoutesFile, $original);
        } else {
            @unlink($apiRoutesFile);
        }
        @unlink(base_path('routes/pasak.php'));
        @unlink(base_path('routes/pasak-api.php'));
    }
});
