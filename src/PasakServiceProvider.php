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
use Inertia\Inertia;
use Internal\Pasak\Console\Commands\PruneSecurityLogs;
use Internal\Pasak\Console\Commands\PurgeInjectedData;
use Internal\Pasak\Console\Commands\SecurityBaselineCommand;
use Internal\Pasak\Console\Commands\SecurityInstallCommand;
use Internal\Pasak\Console\Commands\SecurityScanAccessLogs;
use Internal\Pasak\Console\Commands\SecurityUpgradeCommand;
use Internal\Pasak\Console\Commands\UnblockIpAddress;
use Internal\Pasak\Http\Middleware\BlockIpAddress;
use Internal\Pasak\Http\Middleware\DetectSecurityThreats;
use Internal\Pasak\Http\Middleware\EnsureSecurityAdmin;
use Internal\Pasak\Http\Middleware\TrackUserActivity;
use Internal\Pasak\Listeners\LogFailedLoginAttempt;
use Internal\Pasak\Listeners\RecordUserLogin;
use Internal\Pasak\Listeners\ResetLoginAttempts;
use Internal\Pasak\Services\AccessLogScannerService;
use Internal\Pasak\Services\LoginThrottleService;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Services\ServerSecurityService;
use Internal\Pasak\Services\UserLoginService;
use Internal\Pasak\Services\VersionCheckService;
use Livewire\Livewire;

class PasakServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pasak.php', 'pasak');

        // Backward compatibility: old installs publish config/security.php
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

        $this->app->singleton(LoginThrottleService::class, function ($app) {
            return new LoginThrottleService(
                $app->make(PasakService::class),
            );
        });

        $this->app->singleton(UserLoginService::class, function ($app) {
            return new UserLoginService;
        });

        $this->app->singleton(ServerSecurityService::class, function ($app) {
            return new ServerSecurityService(
                $app->make(PasakService::class),
                $app->make(LoginThrottleService::class),
            );
        });

        $this->app->singleton(AccessLogScannerService::class, function ($app) {
            return new AccessLogScannerService(
                $app->make(PasakService::class),
            );
        });

        $this->app->singleton(VersionCheckService::class, function () {
            return new VersionCheckService;
        });

        $this->app->alias(PasakService::class, 'pasak');
        $this->app->alias(PasakService::class, 'security.monitor');
        $this->app->alias(VersionCheckService::class, 'pasak.version');
        $this->app->alias(VersionCheckService::class, 'security.version');
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
        $this->publishes(
            [
                __DIR__.'/../config/pasak.php' => config_path(
                    'security.php',
                ),
            ],
            'security-config',
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
        $this->publishes(
            [
                __DIR__.'/../database/migrations' => database_path(
                    'migrations',
                ),
            ],
            'security-migrations',
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
        $this->publishes(
            [
                __DIR__.'/../stubs/nginx.conf.stub' => base_path(
                    'nginx.conf',
                ),
            ],
            'security-nginx',
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
        $this->publishes(
            [
                __DIR__.'/../stubs/apache2.conf.stub' => base_path(
                    'apache2.conf',
                ),
            ],
            'security-apache',
        );

        // Apache .htaccess Hardened Configuration
        $this->publishes(
            [
                __DIR__.'/../stubs/htaccess.stub' => public_path('.htaccess'),
            ],
            'pasak-htaccess',
        );
        $this->publishes(
            [
                __DIR__.'/../stubs/htaccess.stub' => public_path('.htaccess'),
            ],
            'security-htaccess',
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
        $this->publishes(
            [
                __DIR__.'/../stubs/blocked.blade.php' => resource_path(
                    'views/errors/blocked.blade.php',
                ),
            ],
            'security-views',
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
        $this->publishes(
            [
                __DIR__.'/../config/pasak.php' => config_path(
                    'security.php',
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
            'security-all',
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
        $this->publishes($pasakRouteStubs, 'security-routes');

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
                __DIR__.'/../stubs/routes/pasak.php.stub' => base_path(
                    'routes/pasak.php',
                ),
            ],
            'security-routes-web',
        );

        $this->publishes(
            [
                __DIR__.'/../stubs/routes/pasak-api.php.stub' => base_path(
                    'routes/pasak-api.php',
                ),
            ],
            'pasak-routes-api',
        );
        $this->publishes(
            [
                __DIR__.'/../stubs/routes/pasak-api.php.stub' => base_path(
                    'routes/pasak-api.php',
                ),
            ],
            'security-routes-api',
        );

        // Livewire Starter Kit Monitoring Dashboard (views + configuration).
        //
        // Publishes the Blade/single-file Livewire pages that make up the
        // monitoring dashboard, tailored for the official Laravel Livewire
        // Starter Kit (Flux UI), together with the package configuration.
        $dashboardAssets = [
            __DIR__.'/../stubs/livewire/pages/pasak' => resource_path(
                'views/pages/pasak',
            ),
            __DIR__.'/../config/pasak.php' => config_path('pasak.php'),
        ];

        $this->publishes($dashboardAssets, 'starterkit-livewire');
        $this->publishes($dashboardAssets, 'starterkit-blade');
        $this->publishes($dashboardAssets, 'pasak-dashboard');
        $this->publishes($dashboardAssets, 'pasak-dashboard-blade');

        // Backwards/typo-compatible alias so that both spellings work.
        $this->publishes($dashboardAssets, 'staterkit-livewire');
        $this->publishes($dashboardAssets, 'staterkit-blade');

        // Monitoring views only (no configuration overwrite).
        $this->publishes(
            [
                __DIR__.'/../stubs/livewire/pages/pasak' => resource_path(
                    'views/pages/pasak',
                ),
            ],
            'pasak-dashboard-views',
        );
        $this->publishes(
            [
                __DIR__.'/../stubs/livewire/pages/pasak' => resource_path(
                    'views/pages/pasak',
                ),
            ],
            'pasak-dashboard-blade-views',
        );

        // React Starter Kit Monitoring Dashboard (views + configuration).
        //
        // Publishes the Inertia + React pages and shared components that make
        // up the monitoring dashboard, tailored for the official Laravel React
        // Starter Kit (Inertia + shadcn/ui), together with the package config.
        $reactDashboardAssets = [
            __DIR__.'/../stubs/react/pages/pasak' => resource_path(
                'js/pages/pasak',
            ),
            __DIR__.'/../stubs/react/components/pasak' => resource_path(
                'js/components/pasak',
            ),
            __DIR__.'/../config/pasak.php' => config_path('pasak.php'),
        ];

        $this->publishes($reactDashboardAssets, 'starterkit-react');
        $this->publishes($reactDashboardAssets, 'starterkit-tsx');
        $this->publishes($reactDashboardAssets, 'pasak-dashboard-react');
        $this->publishes($reactDashboardAssets, 'pasak-dashboard-tsx');

        // Backwards/typo-compatible alias so that both spellings work.
        $this->publishes($reactDashboardAssets, 'staterkit-react');
        $this->publishes($reactDashboardAssets, 'staterkit-tsx');

        // React monitoring views only (no configuration overwrite).
        $this->publishes(
            [
                __DIR__.'/../stubs/react/pages/pasak' => resource_path(
                    'js/pages/pasak',
                ),
                __DIR__.
                '/../stubs/react/components/pasak' => resource_path(
                    'js/components/pasak',
                ),
            ],
            'pasak-dashboard-react-views',
        );
        $this->publishes(
            [
                __DIR__.'/../stubs/react/pages/pasak' => resource_path(
                    'js/pages/pasak',
                ),
                __DIR__.
                '/../stubs/react/components/pasak' => resource_path(
                    'js/components/pasak',
                ),
            ],
            'pasak-dashboard-tsx-views',
        );

        // Combined publication of both Blade and TSX dashboard assets.
        $bothDashboardAssets = array_merge($dashboardAssets, $reactDashboardAssets);
        $this->publishes($bothDashboardAssets, 'starterkit-all');
        $this->publishes($bothDashboardAssets, 'pasak-dashboard-all');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            SecurityInstallCommand::class,
            SecurityUpgradeCommand::class,
            PruneSecurityLogs::class,
            UnblockIpAddress::class,
            SecurityScanAccessLogs::class,
            SecurityBaselineCommand::class,
            PurgeInjectedData::class,
        ]);
    }

    protected function registerListeners(): void
    {
        Event::listen(Failed::class, LogFailedLoginAttempt::class);
        Event::listen(Login::class, ResetLoginAttempts::class);
        Event::listen(Login::class, RecordUserLogin::class);
    }

    protected function registerRoutes(): void
    {
        if (! config('pasak.routes.enabled', true)) {
            return;
        }

        // Jika rute sudah didaftarkan (misalnya oleh routes/api.php di host), hindari duplikasi
        if (Route::has('security.logs.index')) {
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
     * Register the optional Starter Kit monitoring dashboard.
     *
     * The dashboard is only wired up when it has been explicitly enabled via
     * "security.dashboard.enabled" and the matching frontend package is present
     * in the host application. Its views are published separately using:
     *
     *     php artisan vendor:publish --tag=starterkit-livewire
     *     php artisan vendor:publish --tag=starterkit-react
     */
    protected function registerDashboardRoutes(): void
    {
        if (! config('pasak.dashboard.enabled', false)) {
            return;
        }

        // Jika rute dashboard sudah didaftarkan (misalnya oleh routes/web.php di host), hindari duplikasi
        if (Route::has('security.dashboard.overview')) {
            return;
        }

        // Jika berkas rute web kustom host ada, prioritaskan pemuatannya
        $hostDashboardFile = base_path('routes/pasak.php');
        if (file_exists($hostDashboardFile)) {
            $this->loadRoutesFrom($hostDashboardFile);

            return;
        }

        $driver = (string) config('pasak.dashboard.driver', 'livewire');

        if (in_array(strtolower($driver), ['livewire', 'blade', 'all', 'both'], true)) {
            $this->registerLivewireDashboardRoutes();
        }

        if (in_array(strtolower($driver), ['react', 'tsx', 'all', 'both'], true)) {
            $this->registerReactDashboardRoutes();
        }
    }

    /**
     * Register the Livewire (Flux UI) dashboard routes.
     */
    protected function registerLivewireDashboardRoutes(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/pasak-dashboard.php');
    }

    /**
     * Register the Inertia + React dashboard routes.
     */
    protected function registerReactDashboardRoutes(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        $this->loadRoutesFrom(
            __DIR__.'/../routes/pasak-dashboard-react.php',
        );
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
                            ServerSecurityService::HEARTBEAT_KEY,
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

        // Backward compatibility: old installs use manage-security-monitor.
        if (! Gate::has('manage-security-monitor')) {
            Gate::define('manage-security-monitor', function ($user) {
                return Gate::forUser($user)->allows('manage-pasak');
            });
        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('pasak.admin', EnsureSecurityAdmin::class);
        $router->aliasMiddleware('security.admin', EnsureSecurityAdmin::class);
        $router->aliasMiddleware('pasak.block', BlockIpAddress::class);
        $router->aliasMiddleware('security.block', BlockIpAddress::class);
        $router->aliasMiddleware(
            'pasak.detect',
            DetectSecurityThreats::class,
        );
        $router->aliasMiddleware(
            'security.detect',
            DetectSecurityThreats::class,
        );
        $router->aliasMiddleware('pasak.activity', TrackUserActivity::class);
        $router->aliasMiddleware('security.activity', TrackUserActivity::class);

        if (config('pasak.auto_register_middleware', false)) {
            $this->app->booted(function (): void {
                $kernel = $this->app->make(KernelContract::class);

                if ($kernel instanceof HttpKernel) {
                    $kernel->pushMiddleware(BlockIpAddress::class);
                    $kernel->pushMiddleware(DetectSecurityThreats::class);
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
                $versionChecker = $this->app->make(VersionCheckService::class);
                $versionChecker->notifyIfUpdateAvailable($event->output);
            } catch (\Throwable) {
                // Abaikan kesalahan agar tidak pernah mengganggu server dev
            }
        });
    }
}
