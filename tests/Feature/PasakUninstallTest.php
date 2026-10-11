<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

$uninstallDir = '';

beforeEach(function () use (&$uninstallDir) {
    $uninstallDir = sys_get_temp_dir().'/pasak_uninstall_test_'.uniqid();
    foreach (['routes', 'bootstrap', 'app/Models', 'config', 'public', 'resources/views/errors', 'database/migrations'] as $dir) {
        File::makeDirectory($uninstallDir.'/'.$dir, 0755, true, true);
    }

    // Arahkan seluruh helper path Laravel ke direktori tiruan.
    $this->app->setBasePath($uninstallDir);

    // Simulasi aplikasi host yang sudah terinstall Pasak.
    File::put($uninstallDir.'/routes/pasak.php', "<?php // pasak web routes\n");
    File::put($uninstallDir.'/routes/pasak-api.php', "<?php // pasak api routes\n");
    File::put($uninstallDir.'/routes/web.php', "<?php\n\nrequire __DIR__.'/pasak.php';\n");
    File::put($uninstallDir.'/routes/api.php', "<?php\n\nrequire __DIR__.'/pasak-api.php';\n");
    File::put($uninstallDir.'/bootstrap/app.php', <<<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            \Internal\Pasak\Http\Middleware\BlockIpAddress::class,
            \Internal\Pasak\Http\Middleware\DetectSecurityThreats::class,
        ]);
    })
    ->create();
PHP
    );
    File::put($uninstallDir.'/app/Models/User.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Internal\Pasak\Concerns\HasSecurityRelations;

class User extends Authenticatable
{
    use HasSecurityRelations;
}
PHP
    );
    File::put($uninstallDir.'/.env', "APP_NAME=Demo\n\n# Pasak\nPASAK_MONITOR_ENABLED=true\nPASAK_SUPPORT_EMAIL=a@b.test\n");
    File::put($uninstallDir.'/.env.example', "APP_NAME=Demo\n\n# Pasak\nPASAK_MONITOR_ENABLED=true\n");
    File::put($uninstallDir.'/config/pasak.php', "<?php return [];\n");
    File::put($uninstallDir.'/public/.htaccess', 'Original htaccess');
    File::put($uninstallDir.'/public/.htaccess.backup-20260101_000000', 'Original htaccess');
    File::put($uninstallDir.'/resources/views/errors/blocked.blade.php', 'blocked');
    File::makeDirectory($uninstallDir.'/resources/views/pages/pasak', 0755, true, true);
});

afterEach(function () use (&$uninstallDir) {
    if (! empty($uninstallDir) && File::isDirectory($uninstallDir)) {
        File::deleteDirectory($uninstallDir);
    }
});

test('pasak uninstall removes all traces without touching host files', function () use (&$uninstallDir) {
    foreach (['blocked_ips', 'pasak_logs', 'login_attempts', 'ip_unblock_requests', 'user_logins', 'trusted_ips', 'pasak_settings'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    $this->artisan('pasak:uninstall', ['--force' => true])->assertSuccessful();

    foreach (['blocked_ips', 'pasak_logs', 'login_attempts', 'ip_unblock_requests', 'user_logins', 'trusted_ips', 'pasak_settings'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    expect(File::exists($uninstallDir.'/routes/pasak.php'))->toBeFalse()
        ->and(File::exists($uninstallDir.'/routes/pasak-api.php'))->toBeFalse()
        ->and(File::get($uninstallDir.'/routes/web.php'))->not->toContain('pasak.php')
        ->and(File::get($uninstallDir.'/routes/api.php'))->not->toContain('pasak-api.php')
        ->and(File::get($uninstallDir.'/routes/web.php'))->toContain('<?php')
        ->and(File::get($uninstallDir.'/bootstrap/app.php'))->not->toContain('BlockIpAddress')
        ->and(File::get($uninstallDir.'/bootstrap/app.php'))->toContain('withMiddleware')
        ->and(File::get($uninstallDir.'/app/Models/User.php'))->not->toContain('HasSecurityRelations')
        ->and(File::get($uninstallDir.'/app/Models/User.php'))->toContain('class User')
        ->and(File::get($uninstallDir.'/.env'))->not->toContain('PASAK_')
        ->and(File::get($uninstallDir.'/.env'))->toContain('APP_NAME=Demo')
        ->and(File::exists($uninstallDir.'/config/pasak.php'))->toBeFalse()
        ->and(File::get($uninstallDir.'/public/.htaccess'))->toBe('Original htaccess')
        ->and(File::exists($uninstallDir.'/resources/views/errors/blocked.blade.php'))->toBeFalse()
        ->and(File::isDirectory($uninstallDir.'/resources/views/pages/pasak'))->toBeFalse();
});

test('pasak uninstall respects keep options', function () {
    $this->artisan('pasak:uninstall', ['--force' => true, '--keep-tables' => true])->assertSuccessful();

    expect(Schema::hasTable('blocked_ips'))->toBeTrue();
});
