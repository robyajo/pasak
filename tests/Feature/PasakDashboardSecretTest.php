<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Internal\Pasak\Support\PasakDashboardSecret;

function pasakTemporaryEnvFile(): string
{
    $dir = sys_get_temp_dir().'/pasak-secret-'.uniqid('', true);

    File::ensureDirectoryExists($dir);

    return $dir.'/.env';
}

test('pasak:secret terdaftar sebagai perintah artisan', function () {
    expect(array_keys(Artisan::all()))->toContain('pasak:secret');
});

test('pasak:secret membuat rahasia saat baris masih kosong', function () {
    $path = pasakTemporaryEnvFile();

    try {
        File::put($path, "APP_NAME=Demo\nPASAK_DASHBOARD_SECRET=\n");

        $this->artisan('pasak:secret', ['--path' => $path])
            ->assertSuccessful();

        $secret = PasakDashboardSecret::readFromEnvFile($path);

        expect($secret)
            ->toBeString()
            ->and(strlen((string) $secret))
            ->toBe(PasakDashboardSecret::LENGTH);
    } finally {
        File::delete($path);
    }
});

test('pasak:secret tidak menimpa rahasia yang sudah ada kecuali --force', function () {
    $path = pasakTemporaryEnvFile();

    try {
        File::put($path, "PASAK_DASHBOARD_SECRET=existing-secret-value\n");

        $this->artisan('pasak:secret', ['--path' => $path])
            ->assertSuccessful();

        expect(PasakDashboardSecret::readFromEnvFile($path))
            ->toBe('existing-secret-value');

        $this->artisan('pasak:secret', [
            '--path' => $path,
            '--force' => true,
        ])->assertSuccessful();

        $regenerated = PasakDashboardSecret::readFromEnvFile($path);

        expect($regenerated)
            ->not->toBe('existing-secret-value')
            ->and(strlen((string) $regenerated))
            ->toBe(PasakDashboardSecret::LENGTH);
    } finally {
        File::delete($path);
    }
});

test('pasak:install membuat secret hanya pada .env', function () {
    $envPath = base_path('.env');
    $examplePath = base_path('.env.example');
    $originalEnv = File::exists($envPath) ? File::get($envPath) : null;
    $originalExample = File::exists($examplePath)
        ? File::get($examplePath)
        : null;

    try {
        File::put($envPath, "APP_NAME=Demo\n");
        File::put($examplePath, "APP_NAME=Demo\nPASAK_DASHBOARD_SECRET=legacy-leaked-value\n");

        $this->artisan('pasak:install', [
            '--force' => true,
            '--without-nginx' => true,
            '--without-htaccess' => true,
            '--without-views' => true,
            '--without-user-trait' => true,
            '--without-middleware' => true,
            '--without-routes' => true,
            '--without-api' => true,
            '--without-web' => true,
        ])->assertSuccessful();

        $secret = PasakDashboardSecret::readFromEnvFile($envPath);

        expect($secret)
            ->toBeString()
            ->and(strlen((string) $secret))
            ->toBe(PasakDashboardSecret::LENGTH)
            ->and(File::get($examplePath))
            ->toContain('PASAK_DASHBOARD_SECRET=')
            ->not->toContain('legacy-leaked-value')
            ->and(PasakDashboardSecret::readFromEnvFile($examplePath))
            ->toBeNull();
    } finally {
        if ($originalEnv !== null) {
            File::put($envPath, $originalEnv);
        }

        if ($originalExample !== null) {
            File::put($examplePath, $originalExample);
        }
    }
});

test('rahasia contoh tidak ikut ditulis ke .env.example', function () {
    $dir = dirname(pasakTemporaryEnvFile());

    try {
        $env = $dir.'/.env';
        $example = $dir.'/.env.example';

        File::put($env, "PASAK_DASHBOARD_SECRET=\n");
        File::put($example, "PASAK_DASHBOARD_SECRET=\n");

        PasakDashboardSecret::ensureInEnvFile($env);
        PasakDashboardSecret::ensureInEnvFile($example, false, false);

        expect(PasakDashboardSecret::readFromEnvFile($env))->toBeString()
            ->and(PasakDashboardSecret::readFromEnvFile($example))->toBeNull();
    } finally {
        File::deleteDirectory($dir);
    }
});
