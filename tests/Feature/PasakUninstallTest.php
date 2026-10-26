<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

$uninstallDir = '';

beforeEach(function () use (&$uninstallDir) {
    $uninstallDir = sys_get_temp_dir().'/pasak_uninstall_test_'.uniqid();
    foreach (
        [
            'routes',
            'bootstrap',
            'app/Models',
            'config',
            'public',
            'resources/views/errors',
            'database/migrations',
        ] as $dir
    ) {
        File::makeDirectory($uninstallDir.'/'.$dir, 0755, true, true);
    }

    // Arahkan seluruh helper path Laravel ke direktori tiruan.
    $this->app->setBasePath($uninstallDir);

    // Simulasi aplikasi host yang sudah terinstall Pasak.
    File::put(
        $uninstallDir.'/routes/pasak.php',
        "<?php // pasak web routes\n",
    );
    File::put(
        $uninstallDir.'/routes/pasak-api.php',
        "<?php // pasak api routes\n",
    );
    File::put(
        $uninstallDir.'/routes/web.php',
        "<?php\n\nrequire __DIR__.'/pasak.php';\n",
    );
    File::put(
        $uninstallDir.'/routes/api.php',
        "<?php\n\nrequire __DIR__.'/pasak-api.php';\n",
    );
    File::put(
        $uninstallDir.'/bootstrap/app.php',
        <<<'PHP'
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
        ,
    );
    File::put(
        $uninstallDir.'/app/Models/User.php',
        <<<'PHP'
        <?php

        namespace App\Models;

        use Illuminate\Foundation\Auth\User as Authenticatable;
        use Internal\Pasak\Concerns\HasSecurityRelations;

        class User extends Authenticatable
        {
            use HasSecurityRelations;
        }
        PHP
        ,
    );
    File::put(
        $uninstallDir.'/.env',
        "APP_NAME=Demo\n\n# Pasak\nPASAK_MONITOR_ENABLED=true\nPASAK_SUPPORT_EMAIL=a@b.test\n",
    );
    File::put(
        $uninstallDir.'/.env.example',
        "APP_NAME=Demo\n\n# Pasak\nPASAK_MONITOR_ENABLED=true\n",
    );
    File::put($uninstallDir.'/config/pasak.php', "<?php return [];\n");
    File::put($uninstallDir.'/public/.htaccess', 'Original htaccess');
    File::put(
        $uninstallDir.'/public/.htaccess.backup-20260101_000000',
        'Original htaccess',
    );
    File::put(
        $uninstallDir.'/resources/views/errors/blocked.blade.php',
        'blocked',
    );
    File::makeDirectory(
        $uninstallDir.'/resources/views/pages/pasak',
        0755,
        true,
        true,
    );
    File::makeDirectory(
        $uninstallDir.'/.agents/skills/pasak',
        0755,
        true,
        true,
    );
    File::put(
        $uninstallDir.'/.agents/skills/pasak/SKILL.md',
        'skill content',
    );
    File::put(
        $uninstallDir.'/AGENTS.md',
        "# Host Guidelines\n\n<!-- pasak-guidelines:start -->\nPasak Guidelines\n<!-- pasak-guidelines:end -->\n",
    );
});

afterEach(function () use (&$uninstallDir) {
    if (! empty($uninstallDir) && File::isDirectory($uninstallDir)) {
        File::deleteDirectory($uninstallDir);
    }
});

test(
    'pasak uninstall removes all traces without touching host files',
    function () use (&$uninstallDir) {
        foreach (
            [
                'pasak_blocked_ips',
                'pasak_logs',
                'pasak_login_attempts',
                'pasak_ip_unblock_requests',
                'pasak_user_logins',
                'pasak_trusted_ips',
                'pasak_allowed_ips',
                'pasak_settings',
            ] as $table
        ) {
            expect(Schema::hasTable($table))->toBeTrue();
        }

        $this->artisan('pasak:uninstall', [
            '--force' => true,
        ])->assertSuccessful();

        foreach (
            [
                'pasak_blocked_ips',
                'pasak_logs',
                'pasak_login_attempts',
                'pasak_ip_unblock_requests',
                'pasak_user_logins',
                'pasak_trusted_ips',
                'pasak_allowed_ips',
                'pasak_settings',
            ] as $table
        ) {
            expect(Schema::hasTable($table))->toBeFalse();
        }

        expect(File::exists($uninstallDir.'/routes/pasak.php'))
            ->toBeFalse()
            ->and(File::exists($uninstallDir.'/routes/pasak-api.php'))
            ->toBeFalse()
            ->and(File::get($uninstallDir.'/routes/web.php'))
            ->not->toContain('pasak.php')
            ->and(File::get($uninstallDir.'/routes/api.php'))
            ->not->toContain('pasak-api.php')
            ->and(File::get($uninstallDir.'/routes/web.php'))
            ->toContain('<?php')
            ->and(File::get($uninstallDir.'/bootstrap/app.php'))
            ->not->toContain('BlockIpAddress')
            ->and(File::get($uninstallDir.'/bootstrap/app.php'))
            ->toContain('withMiddleware')
            ->and(File::get($uninstallDir.'/bootstrap/app.php'))
            ->not->toContain('->append')
            ->and(File::get($uninstallDir.'/app/Models/User.php'))
            ->not->toContain('HasSecurityRelations')
            ->and(File::get($uninstallDir.'/app/Models/User.php'))
            ->toContain('class User')
            ->and(File::get($uninstallDir.'/.env'))
            ->not->toContain('PASAK_')
            ->and(File::get($uninstallDir.'/.env'))
            ->toContain('APP_NAME=Demo')
            ->and(File::exists($uninstallDir.'/config/pasak.php'))
            ->toBeFalse()
            ->and(File::get($uninstallDir.'/public/.htaccess'))
            ->toBe('Original htaccess')
            ->and(
                File::exists(
                    $uninstallDir.'/resources/views/errors/blocked.blade.php',
                ),
            )
            ->toBeFalse()
            ->and(File::isDirectory($uninstallDir.'/resources/views/errors'))
            ->toBeFalse()
            ->and(
                File::isDirectory(
                    $uninstallDir.'/resources/views/pages/pasak',
                ),
            )
            ->toBeFalse()
            ->and(File::isDirectory($uninstallDir.'/resources/views/pages'))
            ->toBeFalse()
            ->and(File::exists($uninstallDir.'/.agents/skills/pasak/SKILL.md'))
            ->toBeFalse()
            ->and(File::isDirectory($uninstallDir.'/.agents/skills/pasak'))
            ->toBeFalse()
            ->and(File::get($uninstallDir.'/AGENTS.md'))
            ->toBe("# Host Guidelines\n")
            ->and(File::get($uninstallDir.'/AGENTS.md'))
            ->not->toContain('pasak-guidelines');
    },
);

test(
    'pasak uninstall removes middleware registered via use import and short class names',
    function () use (&$uninstallDir) {
        File::put(
            $uninstallDir.'/bootstrap/app.php',
            <<<'PHP'
            <?php

            use Illuminate\Foundation\Application;
            use Illuminate\Foundation\Configuration\Exceptions;
            use Illuminate\Foundation\Configuration\Middleware;
            use Illuminate\Http\Request;
            use Internal\Pasak\Http\Middleware\PasakBlockIpAddress;
            use Internal\Pasak\Http\Middleware\PasakDetectSecurityThreats;
            use Internal\Pasak\Http\Middleware\PasakInjectAnnouncementBanner;

            return Application::configure(basePath: dirname(__DIR__))
                ->withRouting(
                    web: __DIR__.'/../routes/web.php',
                    commands: __DIR__.'/../routes/console.php',
                    health: '/up',
                )
                ->withMiddleware(function (Middleware $middleware): void {
                    $middleware->append([
                        PasakBlockIpAddress::class,
                        PasakDetectSecurityThreats::class,
                        PasakInjectAnnouncementBanner::class,
                    ]);

                    $middleware->trustProxies(at: ['127.0.0.1', '::1']);
                })
                ->withExceptions(function (Exceptions $exceptions): void {
                    $exceptions->shouldRenderJsonWhen(
                        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
                    );
                })->create();
            PHP
            ,
        );

        $this->artisan('pasak:uninstall', [
            '--force' => true,
        ])->assertSuccessful();

        $content = File::get($uninstallDir.'/bootstrap/app.php');

        expect($content)
            ->not->toContain('Pasak')
            ->and($content)
            ->toContain('withMiddleware')
            ->and($content)
            ->toContain('trustProxies')
            ->and($content)
            ->toContain('withExceptions')
            ->and($content)
            ->not->toContain('->append');

        exec(
            'php -l '.
                escapeshellarg($uninstallDir.'/bootstrap/app.php').
                ' 2>&1',
            $output,
            $code,
        );
        expect($code)->toBe(0);
    },
);

test(
    'pasak uninstall keeps host middleware whose name merely contains a pasak suffix',
    function () use (&$uninstallDir) {
        File::put(
            $uninstallDir.'/bootstrap/app.php',
            <<<'PHP'
            <?php

            return Application::configure(basePath: dirname(__DIR__))
                ->withMiddleware(function (Middleware $middleware): void {
                    $middleware->append([
                        SomeBlockIpAddress::class,
                        DetectSecurityThreatsV2::class,
                    ]);
                })
                ->create();
            PHP
            ,
        );

        $this->artisan('pasak:uninstall', [
            '--force' => true,
        ])->assertSuccessful();

        $content = File::get($uninstallDir.'/bootstrap/app.php');

        expect($content)
            ->toContain('SomeBlockIpAddress')
            ->and($content)
            ->toContain('DetectSecurityThreatsV2')
            ->and($content)
            ->toContain('->append');
    },
);

test(
    'pasak uninstall leaves valid bootstrap app when host has trust proxies plus pasak append',
    function () use (&$uninstallDir) {
        File::put(
            $uninstallDir.'/bootstrap/app.php',
            <<<'PHP'
            <?php

            use Illuminate\Foundation\Application;
            use Illuminate\Foundation\Configuration\Middleware;

            return Application::configure(basePath: dirname(__DIR__))
                ->withMiddleware(function (Middleware $middleware): void {
                    $middleware->trustProxies(at: ['127.0.0.1', '::1']);
                    $middleware->append([
                        \Internal\Pasak\Http\Middleware\BlockIpAddress::class,
                        \Internal\Pasak\Http\Middleware\DetectSecurityThreats::class,
                        \Internal\Pasak\Http\Middleware\InjectAnnouncementBanner::class,
                    ]);
                })
                ->create();
            PHP
            ,
        );

        $this->artisan('pasak:uninstall', [
            '--force' => true,
        ])->assertSuccessful();

        $content = File::get($uninstallDir.'/bootstrap/app.php');

        expect($content)
            ->not->toContain('Pasak')
            ->and($content)
            ->toContain('trustProxies');

        // Tidak boleh ada statement variabel gantung ("$middleware" tanpa method call).
        foreach (explode("\n", $content) as $line) {
            expect(trim($line))
                ->not->toBe('$middleware')
                ->and(trim($line))
                ->not->toBe('$middleware;');
        }

        exec(
            'php -l '.
                escapeshellarg($uninstallDir.'/bootstrap/app.php').
                ' 2>&1',
            $output,
            $code,
        );
        expect($code)->toBe(0);
    },
);

test(
    'pasak uninstall removes stub env comments and pasak backup files',
    function () use (&$uninstallDir) {
        $stub = file_get_contents(dirname(__DIR__, 2).'/stubs/env.stub');
        File::put($uninstallDir.'/.env', "APP_NAME=Demo\n\n".$stub."\n");
        File::put(
            $uninstallDir.'/routes/pasak.php.backup-20260101_000000',
            'backup',
        );
        File::put(
            $uninstallDir.'/public/.htaccess.backup-20260101_000000',
            'Original htaccess',
        );
        File::makeDirectory(
            $uninstallDir.
                '/resources/views/pages/pasak.backup-20260101_000000',
            0755,
            true,
            true,
        );

        $this->artisan('pasak:uninstall', [
            '--force' => true,
        ])->assertSuccessful();

        expect(File::get($uninstallDir.'/.env'))
            ->not->toContain('PASAK_')
            ->and(File::get($uninstallDir.'/.env'))
            ->not->toContain('# Pasak')
            ->and(File::get($uninstallDir.'/.env'))
            ->toContain('APP_NAME=Demo')
            ->and(
                File::exists(
                    $uninstallDir.'/routes/pasak.php.backup-20260101_000000',
                ),
            )
            ->toBeFalse()
            ->and(
                File::exists(
                    $uninstallDir.'/public/.htaccess.backup-20260101_000000',
                ),
            )
            ->toBeFalse()
            ->and(
                File::isDirectory(
                    $uninstallDir.
                        '/resources/views/pages/pasak.backup-20260101_000000',
                ),
            )
            ->toBeFalse();
    },
);

test('pasak uninstall respects keep options', function () {
    $this->artisan('pasak:uninstall', [
        '--force' => true,
        '--keep-tables' => true,
    ])->assertSuccessful();

    expect(Schema::hasTable('pasak_blocked_ips'))->toBeTrue();
});

test(
    'pasak uninstall removes its migration records so reinstall recreates tables',
    function () {
        expect(
            DB::table('migrations')
                ->where('migration', '2026_01_01_000001_create_pasak_tables')
                ->exists(),
        )->toBeTrue();

        $this->artisan('pasak:uninstall', [
            '--force' => true,
        ])->assertSuccessful();

        expect(
            DB::table('migrations')
                ->where('migration', 'like', '%pasak%')
                ->exists(),
        )
            ->toBeFalse()
            ->and(Schema::hasTable('pasak_blocked_ips'))
            ->toBeFalse();
    },
);

test(
    'pasak uninstall cleans pint-formatted route require with spaces and double quotes',
    function () use (&$uninstallDir) {
        File::put(
            $uninstallDir.'/routes/web.php',
            "<?php\n\nrequire __DIR__ . '/pasak.php';\n",
        );
        File::put(
            $uninstallDir.'/routes/api.php',
            "<?php\n\nrequire_once __DIR__ . \"/pasak-api.php\";\n",
        );
        File::put($uninstallDir.'/routes/pasak.php', "<?php\n");
        File::put($uninstallDir.'/routes/pasak-api.php', "<?php\n");

        $this->artisan('pasak:uninstall', [
            '--force' => true,
        ])->assertSuccessful();

        expect(File::get($uninstallDir.'/routes/web.php'))
            ->not->toContain('pasak.php')
            ->and(File::get($uninstallDir.'/routes/api.php'))
            ->not->toContain('pasak-api.php');
    },
);
