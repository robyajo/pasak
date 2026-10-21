<?php

namespace Internal\Pasak;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Internal\Pasak\Console\Commands\PasakBaselineCommand;
use Internal\Pasak\Console\Commands\PasakInstallCommand;
use Internal\Pasak\Console\Commands\PasakPruneSecurityLogsCommand;
use Internal\Pasak\Console\Commands\PasakPurgeInjectedDataCommand;
use Internal\Pasak\Console\Commands\PasakScanAccessLogsCommand;
use Internal\Pasak\Console\Commands\PasakUnblockIpCommand;
use Internal\Pasak\Console\Commands\PasakUninstallCommand;
use Internal\Pasak\Console\Commands\PasakUpgradeCommand;
use Internal\Pasak\Http\Middleware\PasakBlockIpAddress;
use Internal\Pasak\Http\Middleware\PasakDetectSecurityThreats;
use Internal\Pasak\Http\Middleware\PasakEnsureSecurityAdmin;
use Internal\Pasak\Http\Middleware\PasakInjectAnnouncementBanner;
use Internal\Pasak\Http\Middleware\PasakTrackUserActivity;
use Internal\Pasak\Listeners\PasakLogFailedLoginAttempt;
use Internal\Pasak\Listeners\PasakRecordUserLogin;
use Internal\Pasak\Listeners\PasakResetLoginAttempts;
use Internal\Pasak\Services\AccessLogScannerService;
use Internal\Pasak\Services\LoginThrottleService;
use Internal\Pasak\Services\PasakAccessLogScannerService;
use Internal\Pasak\Services\PasakLoginThrottleService;
use Internal\Pasak\Services\PasakServerSecurityService;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Services\PasakUserLoginService;
use Internal\Pasak\Services\PasakVersionCheckService;
use Internal\Pasak\Services\ServerSecurityService;
use Internal\Pasak\Services\UserLoginService;
use Internal\Pasak\Services\VersionCheckService;

class PasakServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pasak.php', 'pasak');

        // Backward compatibility: old installs publish config/pasak.php
        // with SECURITY_* env vars. Merge it as fallback for missing keys.
        $legacyConfig = config('security');
        if (is_array($legacyConfig)) {
            foreach ($legacyConfig as $key => $value) {
                if (! config()->has('pasak.'.$key)) {
                    config()->set('pasak.'.$key, $value);
                }
            }
            // Keep the legacy key in sync so old config('security.*') calls keep working.
            config()->set('security', array_replace($legacyConfig, config('pasak')));
        } else {
            config()->set('security', config('pasak'));
        }

        $this->app->singleton(PasakService::class, function ($app) {
            return new PasakService;
        });

        $this->app->singleton(PasakLoginThrottleService::class, function ($app) {
            return new PasakLoginThrottleService(
                $app->make(PasakService::class),
            );
        });
        $this->app->alias(PasakLoginThrottleService::class, LoginThrottleService::class);

        $this->app->singleton(PasakUserLoginService::class, function ($app) {
            return new PasakUserLoginService;
        });
        $this->app->alias(PasakUserLoginService::class, UserLoginService::class);

        $this->app->singleton(PasakServerSecurityService::class, function ($app) {
            return new PasakServerSecurityService(
                $app->make(PasakService::class),
                $app->make(PasakLoginThrottleService::class),
            );
        });
        $this->app->alias(PasakServerSecurityService::class, ServerSecurityService::class);

        $this->app->singleton(PasakAccessLogScannerService::class, function ($app) {
            return new PasakAccessLogScannerService(
                $app->make(PasakService::class),
            );
        });
        $this->app->alias(PasakAccessLogScannerService::class, AccessLogScannerService::class);

        $this->app->singleton(PasakVersionCheckService::class, function () {
            return new PasakVersionCheckService;
        });
        $this->app->alias(PasakVersionCheckService::class, VersionCheckService::class);

        $this->app->alias(PasakService::class, 'pasak');
        $this->app->alias(PasakVersionCheckService::class, 'pasak.version');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerListeners();
        $this->registerRoutes();
        $this->registerDashboardRoutes();
        $this->registerSchedule();
        $this->registerGate();
        $this->registerMiddleware();
        $this->registerVersionCheck();
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Config
        $this->publishes(
            [
                __DIR__.'/../config/pasak.php' => config_path(
                    'pasak.php',
                ),
            ],
            'pasak-config',
        );

        // Migrations
        $this->publishes(
            [
                __DIR__.'/../database/migrations' => database_path(
                    'migrations',
                ),
            ],
            'pasak-migrations',
        );

        // Nginx Hardened Configuration
        $this->publishes(
            [
                __DIR__.'/../stubs/nginx.conf.stub' => base_path(
                    'nginx.conf',
                ),
            ],
            'pasak-nginx',
        );

        // Apache 2 VirtualHost Hardened Configuration
        $this->publishes(
            [
                __DIR__.'/../stubs/apache2.conf.stub' => base_path(
                    'apache2.conf',
                ),
            ],
            'pasak-apache',
        );

        // Apache .htaccess Hardened Configuration
        $this->publishes(
            [
                __DIR__.'/../stubs/htaccess.stub' => public_path('.htaccess'),
            ],
            'pasak-htaccess',
        );

        // Default 403 "blocked" error page
        $this->publishes(
            [
                __DIR__.'/../stubs/blocked.blade.php' => resource_path(
                    'views/errors/blocked.blade.php',
                ),
            ],
            'pasak-views',
        );

        // Publish All Assets (Config, Migrations, Nginx, Apache2, Htaccess, Blocked View)
        $this->publishes(
            [
                __DIR__.'/../config/pasak.php' => config_path(
                    'pasak.php',
                ),
                __DIR__.'/../database/migrations' => database_path(
                    'migrations',
                ),
                __DIR__.'/../stubs/nginx.conf.stub' => base_path(
                    'nginx.conf',
                ),
                __DIR__.'/../stubs/apache2.conf.stub' => base_path(
                    'apache2.conf',
                ),
                __DIR__.'/../stubs/htaccess.stub' => public_path('.htaccess'),
                __DIR__.'/../stubs/blocked.blade.php' => resource_path(
                    'views/errors/blocked.blade.php',
                ),
            ],
            'pasak-all',
        );

        // Pasak Route Stubs (routes/pasak.php & routes/pasak-api.php)
        $pasakRouteStubs = [
            __DIR__.'/../stubs/routes/pasak.php.stub' => base_path(
                'routes/pasak.php',
            ),
            __DIR__.'/../stubs/routes/pasak-api.php.stub' => base_path(
                'routes/pasak-api.php',
            ),
        ];
        $this->publishes($pasakRouteStubs, 'pasak-routes');

        $this->publishes(
            [
                __DIR__.'/../stubs/routes/pasak.php.stub' => base_path(
                    'routes/pasak.php',
                ),
            ],
            'pasak-routes-web',
        );

        $this->publishes(
            [
                __DIR__.'/../stubs/routes/pasak-api.php.stub' => base_path(
                    'routes/pasak-api.php',
                ),
            ],
            'pasak-routes-api',
        );

        // Dashboard monitoring Blade murni (views + konfigurasi).
        // Satu-satunya dashboard yang didukung: Blade murni tanpa
        // Livewire/Inertia/Vue — 100% Pure PHP (standar Spatie).
        $bladeViews = [
            __DIR__.'/../stubs/blade/pages/pasak' => resource_path(
                'views/pages/pasak',
            ),
        ];
        $dashboardAssets = array_merge(
            $bladeViews,
            [__DIR__.'/../config/pasak.php' => config_path('pasak.php')],
        );

        $this->publishes($dashboardAssets, 'pasak-dashboard');
        $this->publishes($dashboardAssets, 'plain-views');
        $this->publishes($bladeViews, 'pasak-dashboard-views');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            PasakInstallCommand::class,
            PasakUninstallCommand::class,
            PasakUpgradeCommand::class,
            PasakPruneSecurityLogsCommand::class,
            PasakUnblockIpCommand::class,
            PasakScanAccessLogsCommand::class,
            PasakBaselineCommand::class,
            PasakPurgeInjectedDataCommand::class,
        ]);
    }

    protected function registerListeners(): void
    {
        Event::listen(Failed::class, PasakLogFailedLoginAttempt::class);
        Event::listen(Login::class, PasakResetLoginAttempts::class);
        Event::listen(Login::class, PasakRecordUserLogin::class);
    }

    protected function registerRoutes(): void
    {
        if (! config('pasak.routes.enabled', true)) {
            return;
        }

        // Jika rute sudah didaftarkan (misalnya oleh routes/api.php di host), hindari duplikasi
        if (Route::has('pasak.logs.index')) {
            return;
        }

        // Jika berkas rute kustom host ada, prioritaskan pemuatannya
        $hostRouteFile = base_path('routes/pasak-api.php');
        if (file_exists($hostRouteFile)) {
            $this->loadRoutesFrom($hostRouteFile);

            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/pasak.php');
    }

    /**
     * Register the Blade monitoring dashboard (the only dashboard driver).
     *
     * The dashboard is only wired up when it has been explicitly enabled via
     * "pasak.dashboard.enabled". Its views are published separately using:
     *
     *     php artisan pasak:install --with-dashboard
     */
    protected function registerDashboardRoutes(): void
    {
        if (! config('pasak.dashboard.enabled', false)) {
            return;
        }

        // Jika rute dashboard sudah didaftarkan (misalnya oleh routes/web.php di host), hindari duplikasi
        if (Route::has('pasak.dashboard.overview')) {
            return;
        }

        // Jika berkas rute web kustom host ada, prioritaskan pemuatannya
        $hostDashboardFile = base_path('routes/pasak.php');
        if (file_exists($hostDashboardFile)) {
            $this->loadRoutesFrom($hostDashboardFile);

            return;
        }

        $this->registerBladeDashboardRoutes();
    }

    /**
     * Register the plain-Blade dashboard routes (no Livewire/Inertia needed).
     */
    protected function registerBladeDashboardRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/pasak-dashboard-blade.php');
    }

    protected function registerSchedule(): void
    {
        if (! config('pasak.schedule.enabled', true)) {
            return;
        }

        $this->app->booted(function (): void {
            if (! $this->app->runningInConsole()) {
                return;
            }

            try {
                $schedule = $this->app->make(Schedule::class);

                // Daily log prune
                $pruneAt = config('pasak.schedule.prune_at', '02:30');
                $schedule->command('pasak:prune-logs')->dailyAt($pruneAt);

                // Scheduler Heartbeat
                $schedule
                    ->call(function (): void {
                        Cache::put(
                            PasakServerSecurityService::HEARTBEAT_KEY,
                            now()->toIso8601String(),
                            now()->addHour(),
                        );
                    })
                    ->everyMinute()
                    ->name('pasak-heartbeat');

                // Access log auto-scan
                if (config('pasak.access_log.auto_scan_enabled', false)) {
                    $hours = max(
                        1,
                        (int) config(
                            'pasak.access_log.auto_scan_since_hours',
                            24,
                        ),
                    );
                    $schedule
                        ->command(
                            sprintf(
                                'pasak:scan-logs --import --min-level=high --since="%d hours ago"',
                                $hours,
                            ),
                        )
                        ->dailyAt('03:00')
                        ->name('pasak-scan-access-logs');
                }
            } catch (\Throwable) {
                // Ignore schedule registration errors if Schedule is not bound
            }
        });
    }

    protected function registerGate(): void
    {
        if (! Gate::has('manage-pasak')) {
            Gate::define('manage-pasak', function ($user) {
                if (method_exists($user, 'isAdmin')) {
                    return (bool) $user->isAdmin();
                }

                if (
                    in_array($user->role ?? null, ['admin', 'superadmin'], true)
                ) {
                    return true;
                }

                if (isset($user->is_admin)) {
                    return (bool) $user->is_admin;
                }

                if (isset($user->role)) {
                    return in_array($user->role, ['admin', 'superadmin'], true);
                }

                return ! (bool) config('pasak.dashboard.admin_only', false);
            });
        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('pasak.admin', PasakEnsureSecurityAdmin::class);
        $router->aliasMiddleware('pasak.block', PasakBlockIpAddress::class);
        $router->aliasMiddleware(
            'pasak.detect',
            PasakDetectSecurityThreats::class,
        );
        $router->aliasMiddleware('pasak.activity', PasakTrackUserActivity::class);
        $router->aliasMiddleware('pasak.announce', PasakInjectAnnouncementBanner::class);

        if (config('pasak.auto_register_middleware', false)) {
            $this->app->booted(function (): void {
                $kernel = $this->app->make(KernelContract::class);

                if ($kernel instanceof HttpKernel) {
                    $kernel->pushMiddleware(PasakBlockIpAddress::class);
                    $kernel->pushMiddleware(PasakDetectSecurityThreats::class);
                    $kernel->pushMiddleware(PasakInjectAnnouncementBanner::class);
                }
            });
        }
    }

    /**
     * Daftarkan pendengar event command terminal untuk memeriksa versi terbaru secara non-blocking
     * saat menjalankan "php artisan serve" atau "composer run dev" (artisan dev).
     */
    protected function registerVersionCheck(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        if (! (bool) config('pasak.version_check.enabled', true)) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! in_array($event->command, ['serve', 'dev'], true)) {
                return;
            }

            try {
                $versionChecker = $this->app->make(PasakVersionCheckService::class);
                $versionChecker->notifyIfUpdateAvailable($event->output);
            } catch (\Throwable) {
                // Abaikan kesalahan agar tidak pernah mengganggu server dev
            }
        });
    }
}
