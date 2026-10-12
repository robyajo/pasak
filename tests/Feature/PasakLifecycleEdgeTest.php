<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

$edgeDir = '';

beforeEach(function () use (&$edgeDir) {
    $edgeDir = sys_get_temp_dir().'/pasak_edge_test_'.uniqid();
    foreach (['routes', 'bootstrap', 'app/Models', 'app/Http', 'config', 'public', 'resources/views/errors', 'resources/views/pages/pasak'] as $dir) {
        File::makeDirectory($edgeDir.'/'.$dir, 0755, true, true);
    }

    Http::fake([
        'https://repo.packagist.org/p2/robyajo/pasak.json' => Http::response([
            'packages' => ['robyajo/pasak' => [['version' => 'v99.9.9']]],
        ], 200),
        'https://api.github.com/repos/robyajo/pasak/tags' => Http::response([], 200),
    ]);
});

afterEach(function () use (&$edgeDir) {
    if (! empty($edgeDir) && File::isDirectory($edgeDir)) {
        File::deleteDirectory($edgeDir);
    }
});

test('uninstall on fresh host without any pasak traces succeeds and touches nothing', function () use (&$edgeDir) {
    $this->app->setBasePath($edgeDir);
    File::put($edgeDir.'/routes/web.php', "<?php\n\n// host routes\n");
    File::put($edgeDir.'/.env', "APP_NAME=Demo\nAPP_KEY=base64:xyz\n");
    File::put($edgeDir.'/public/.htaccess', 'Custom htaccess');

    $this->artisan('pasak:uninstall', ['--force' => true])->assertSuccessful();

    expect(File::get($edgeDir.'/routes/web.php'))->toBe("<?php\n\n// host routes\n")
        ->and(File::get($edgeDir.'/.env'))->toBe("APP_NAME=Demo\nAPP_KEY=base64:xyz\n")
        ->and(File::get($edgeDir.'/public/.htaccess'))->toBe('Custom htaccess');
});

test('uninstall removes middleware from laravel 10 kernel style and keeps host middleware', function () use (&$edgeDir) {
    $this->app->setBasePath($edgeDir);
    File::put($edgeDir.'/app/Http/Kernel.php', <<<'PHP'
<?php

class Kernel
{
    protected $middleware = [
        \App\Http\Middleware\TrustProxies::class,
        \Internal\Pasak\Http\Middleware\BlockIpAddress::class,
        \Internal\Pasak\Http\Middleware\DetectSecurityThreats::class,
    ];
}
PHP
    );

    $this->artisan('pasak:uninstall', ['--force' => true])->assertSuccessful();

    $content = File::get($edgeDir.'/app/Http/Kernel.php');
    expect($content)->not->toContain('BlockIpAddress')
        ->and($content)->not->toContain('DetectSecurityThreats')
        ->and($content)->toContain('TrustProxies');
});

test('uninstall leaves user model without trait untouched', function () use (&$edgeDir) {
    $this->app->setBasePath($edgeDir);
    $original = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
}
PHP;
    File::put($edgeDir.'/app/Models/User.php', $original);

    $this->artisan('pasak:uninstall', ['--force' => true])->assertSuccessful();

    expect(File::get($edgeDir.'/app/Models/User.php'))->toBe($original);
});

test('uninstall removes trait from combined use statement and keeps other traits', function () use (&$edgeDir) {
    $this->app->setBasePath($edgeDir);
    File::put($edgeDir.'/app/Models/User.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Internal\Pasak\Concerns\HasSecurityRelations;

class User extends Authenticatable
{
    use HasFactory, HasSecurityRelations, Notifiable;
}
PHP
    );

    $this->artisan('pasak:uninstall', ['--force' => true])->assertSuccessful();

    $content = File::get($edgeDir.'/app/Models/User.php');
    expect($content)->not->toContain('HasSecurityRelations')
        ->and($content)->toContain('HasFactory')
        ->and($content)->toContain('Notifiable');
});

test('uninstall cleans legacy security traces from previous package versions', function () use (&$edgeDir) {
    $this->app->setBasePath($edgeDir);
    File::put($edgeDir.'/routes/web.php', "<?php\n\nrequire __DIR__.'/security.php';\n");
    File::put($edgeDir.'/bootstrap/app.php', <<<'PHP'
<?php

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            \Internal\SecurityMonitor\Http\Middleware\BlockIpAddress::class,
        ]);
    })
    ->create();
PHP
    );
    Schema::create('security_logs', function ($table) {
        $table->id();
    });
    Schema::create('security_settings', function ($table) {
        $table->id();
    });

    $this->artisan('pasak:uninstall', ['--force' => true])->assertSuccessful();

    expect(File::get($edgeDir.'/routes/web.php'))->not->toContain('security.php')
        ->and(File::get($edgeDir.'/bootstrap/app.php'))->not->toContain('SecurityMonitor')
        ->and(Schema::hasTable('security_logs'))->toBeFalse()
        ->and(Schema::hasTable('security_settings'))->toBeFalse();
});

test('uninstall with missing tables and files does not error', function () use (&$edgeDir) {
    $this->app->setBasePath($edgeDir);
    foreach (['blocked_ips', 'pasak_logs', 'login_attempts', 'ip_unblock_requests', 'user_logins', 'trusted_ips', 'pasak_settings'] as $table) {
        Schema::dropIfExists($table);
    }

    $this->artisan('pasak:uninstall', ['--force' => true])->assertSuccessful();
});

test('upgrade migrates legacy security-named host files to pasak names', function () use (&$edgeDir) {
    $this->app->setBasePath($edgeDir);

    File::put($edgeDir.'/routes/security.php', "<?php // KUSTOM RUTE LAMA\n");
    File::put($edgeDir.'/routes/security-api.php', "<?php // KUSTOM API LAMA\n");
    File::put($edgeDir.'/routes/web.php', "<?php\n\nrequire __DIR__.'/security.php';\n");
    File::put($edgeDir.'/routes/api.php', "<?php\n\nrequire __DIR__.'/security-api.php';\n");
    File::makeDirectory($edgeDir.'/resources/views/pages/security', 0755, true, true);
    File::put($edgeDir.'/resources/views/pages/security/overview.blade.php', 'kustom lama');
    File::makeDirectory($edgeDir.'/config', 0755, true, true);
    File::put($edgeDir.'/config/security.php', "<?php // config lama\n");
    // Simulasi host era lama murni: target baru belum ada.
    File::deleteDirectory($edgeDir.'/resources/views/pages/pasak');

    $this->artisan('pasak:upgrade', [
        '--force' => true,
        '--no-composer' => true,
        '--no-migrate' => true,
    ])->assertSuccessful();

    expect(File::exists($edgeDir.'/routes/security.php'))->toBeFalse()
        ->and(File::get($edgeDir.'/routes/pasak.php'))->toContain('KUSTOM RUTE LAMA')
        ->and(File::exists($edgeDir.'/routes/security-api.php'))->toBeFalse()
        ->and(File::get($edgeDir.'/routes/pasak-api.php'))->toContain('KUSTOM API LAMA')
        ->and(File::get($edgeDir.'/routes/web.php'))->toContain("require __DIR__.'/pasak.php';")
        ->and(File::get($edgeDir.'/routes/api.php'))->toContain("require __DIR__.'/pasak-api.php';")
        ->and(File::isDirectory($edgeDir.'/resources/views/pages/security'))->toBeFalse()
        ->and(File::get($edgeDir.'/resources/views/pages/pasak/overview.blade.php'))->toBe('kustom lama')
        ->and(File::exists($edgeDir.'/config/pasak.php'))->toBeTrue()
        ->and(File::exists($edgeDir.'/config/security.php'))->toBeTrue();
});

test('upgrade preserves customized host routes by default', function () {
    $routeFile = base_path('routes/pasak.php');
    File::put($routeFile, "<?php // KUSTOMISASI HOST JANGAN TIMPA\n");

    try {
        $this->artisan('pasak:upgrade', [
            '--force' => true,
            '--no-composer' => true,
            '--no-migrate' => true,
        ])->assertSuccessful();

        expect(File::get($routeFile))->toContain('KUSTOMISASI HOST JANGAN TIMPA');
    } finally {
        @unlink($routeFile);
    }
});

test('upgrade with sync-routes backs up customized routes before overwriting', function () {
    $routeFile = base_path('routes/pasak.php');
    File::put($routeFile, "<?php // KUSTOMISASI HOST JANGAN TIMPA\n");

    try {
        $this->artisan('pasak:upgrade', [
            '--force' => true,
            '--no-composer' => true,
            '--no-migrate' => true,
            '--sync-routes' => true,
        ])->assertSuccessful();

        $backups = glob($routeFile.'.backup-*') ?: [];
        expect($backups)->not->toBeEmpty()
            ->and(File::get($backups[0]))->toContain('KUSTOMISASI HOST JANGAN TIMPA')
            ->and(File::get($routeFile))->not->toContain('KUSTOMISASI HOST JANGAN TIMPA');
    } finally {
        @unlink($routeFile);
        foreach (glob($routeFile.'.backup-*') ?: [] as $backup) {
            @unlink($backup);
        }
        @unlink(base_path('routes/pasak-api.php'));
    }
});

test('upgrade without local route files succeeds using internal routes', function () {
    @unlink(base_path('routes/pasak.php'));
    @unlink(base_path('routes/pasak-api.php'));

    $this->artisan('pasak:upgrade', [
        '--force' => true,
        '--no-composer' => true,
        '--no-migrate' => true,
    ])->assertSuccessful();

    expect(File::exists(base_path('routes/pasak.php')))->toBeFalse();
});

test('upgrade preserves customized blocked view unless sync-views is given', function () {
    $viewFile = resource_path('views/errors/blocked.blade.php');
    $existed = File::exists($viewFile);
    $original = $existed ? File::get($viewFile) : null;
    File::put($viewFile, 'KUSTOM BRANDING HOST');

    try {
        $this->artisan('pasak:upgrade', [
            '--force' => true,
            '--no-composer' => true,
            '--no-migrate' => true,
        ])->assertSuccessful();

        expect(File::get($viewFile))->toBe('KUSTOM BRANDING HOST');
    } finally {
        if ($existed) {
            File::put($viewFile, $original);
        } else {
            @unlink($viewFile);
        }
    }
});
