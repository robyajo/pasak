<?php

use Illuminate\Support\Facades\File;
use Internal\Pasak\Console\Commands\SecurityInstallCommand;

test('install command registers wizard options', function () {
    $definition = $this->app->make(SecurityInstallCommand::class)->getDefinition();

    expect($definition->hasOption('lang'))->toBeTrue()
        ->and($definition->hasOption('api-only'))->toBeTrue()
        ->and($definition->hasOption('without-api'))->toBeTrue()
        ->and($definition->hasOption('without-web'))->toBeTrue()
        ->and($definition->hasOption('with-dashboard'))->toBeTrue()
        ->and($definition->hasOption('with-blade'))->toBeTrue()
        ->and($definition->hasOption('with-plain'))->toBeTrue()
        ->and($definition->hasOption('with-livewire'))->toBeFalse()
        ->and($definition->hasOption('with-tsx'))->toBeFalse()
        ->and($definition->hasOption('with-both'))->toBeFalse()
        ->and($definition->hasOption('stack'))->toBeFalse();
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

test('install with without-web publishes api routes but no web pages', function () {
    $webRouteFile = base_path('routes/pasak.php');
    $apiRouteFile = base_path('routes/pasak-api.php');
    @unlink($webRouteFile);
    @unlink($apiRouteFile);

    try {
        $this->artisan('pasak:install', [
            '--without-web' => true,
            '--force' => true,
        ])->assertSuccessful();

        expect(File::exists($apiRouteFile))->toBeTrue()
            ->and(File::exists($webRouteFile))->toBeFalse();
    } finally {
        @unlink($webRouteFile);
        @unlink($apiRouteFile);
    }
});

test('install with without-api publishes web pages but no api routes', function () {
    $webRouteFile = base_path('routes/pasak.php');
    $apiRouteFile = base_path('routes/pasak-api.php');
    @unlink($webRouteFile);
    @unlink($apiRouteFile);

    try {
        $this->artisan('pasak:install', [
            '--without-api' => true,
            '--with-dashboard' => true,
            '--force' => true,
        ])->assertSuccessful();

        expect(File::exists($webRouteFile))->toBeTrue()
            ->and(File::exists($apiRouteFile))->toBeFalse();
    } finally {
        @unlink($webRouteFile);
        @unlink($apiRouteFile);
    }
});
test('install prints dashboard access urls after install', function () {
    $webRouteFile = base_path('routes/pasak.php');
    $apiRouteFile = base_path('routes/pasak-api.php');
    @unlink($webRouteFile);
    @unlink($apiRouteFile);

    try {
        $command = $this->artisan('pasak:install', [
            '--force' => true,
            '--with-plain' => true,
            '--without-nginx' => true,
            '--without-htaccess' => true,
            '--without-env' => true,
        ]);

        $command->expectsOutputToContain('/pasak/logs');

        foreach (['/pasak/blocked-ips', '/pasak/server', '/pasak/sessions', '/pasak/tickets', '/pasak/settings'] as $path) {
            $command->expectsOutputToContain($path);
        }

        $command->assertSuccessful();
    } finally {
        @unlink($webRouteFile);
        @unlink($apiRouteFile);
    }
});

test('install does not duplicate route require lines on re-run', function () {
    $apiRoutesFile = base_path('routes/api.php');
    $hadApiRoutes = File::exists($apiRoutesFile);
    $original = $hadApiRoutes ? File::get($apiRoutesFile) : null;

    // Normalisasi: buang sisa require dari test lain agar mandiri.
    if ($hadApiRoutes) {
        File::put($apiRoutesFile, str_replace("\nrequire __DIR__.'/pasak-api.php';", '', $original));
    }

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
