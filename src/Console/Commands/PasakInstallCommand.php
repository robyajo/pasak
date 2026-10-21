<?php

namespace Internal\Pasak\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PasakInstallCommand extends Command
{
    protected string $wizardLang = 'id';

    protected const DOCS_BASE = 'https://security-dev.pekanbaru.go.id';

    protected function t(string $id, string $en): string
    {
        return $this->wizardLang === 'en' ? $en : $id;
    }

    protected function docs(string $path = ''): string
    {
        return rtrim(self::DOCS_BASE, '/').($path !== '' ? '/'.ltrim($path, '/') : '');
    }

    protected $signature = 'pasak:install
                            {--force : Timpa berkas konfigurasi, migrasi, nginx, htaccess, dan halaman blokir yang sudah ada}
                            {--lang= : Bahasa wizard instalasi (id|en)}
                            {--api-only : Instalasi API-only (inti + keamanan, tanpa halaman/dashboard)}
                            {--without-nginx : Jangan publikasikan berkas nginx.conf}
                            {--without-apache : Jangan publikasikan berkas apache2.conf}
                            {--without-htaccess : Jangan perbarui berkas public/.htaccess}
                            {--without-views : Jangan publikasikan halaman blokir errors/blocked.blade.php}
                            {--without-env : Jangan tambahkan variabel konfigurasi ke berkas .env}
                            {--without-user-trait : Jangan tambahkan trait HasPasakSecurityRelations ke model User}
                            {--without-middleware : Jangan daftarkan middleware WAF ke bootstrap/app.php atau Kernel.php}
                            {--without-routes : Jangan publikasikan berkas routes/pasak.php dan routes/pasak-api.php}
                            {--without-api : Jangan pasang dukungan API (tanpa rute API)}
                            {--without-web : Jangan pasang dashboard web (tanpa halaman/dashboard)}
                            {--with-dashboard : Publikasikan dashboard monitoring Blade murni (semua aplikasi Laravel)}
                            {--with-plain : Alias --with-dashboard (Blade murni)}
                            {--with-blade : Alias --with-dashboard (Blade murni)}
                            {--with-htaccess : Paksa perbarui berkas public/.htaccess dengan aturan hardening}';

    protected $description = 'Instalasi dan publikasi aset Pasak (konfigurasi, migrasi, Nginx, Apache .htaccess, halaman blokir, dashboard Blade, trait User, middleware WAF, rute web & API kustom, dan .env)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $withoutNginx = (bool) $this->option('without-nginx');
        $withoutApache = (bool) $this->option('without-apache');
        $withoutHtaccess = (bool) $this->option('without-htaccess');
        $withoutViews = (bool) $this->option('without-views');
        $withoutEnv = (bool) $this->option('without-env');
        $withoutUserTrait = (bool) $this->option('without-user-trait');
        $withoutMiddleware = (bool) $this->option('without-middleware');
        $withoutRoutes = (bool) $this->option('without-routes');
        $withoutApi = (bool) $this->option('without-api');
        $withoutWeb = (bool) $this->option('without-web');
        $withDashboard = (bool) ($this->option('with-dashboard') || $this->option('with-plain') || $this->option('with-blade'));
        $apiOnly = (bool) $this->option('api-only');

        $interactive = ! app()->runningUnitTests() && $this->input->isInteractive();

        // 0. Bahasa wizard instalasi (default: Indonesia).
        $langOption = strtolower((string) ($this->option('lang') ?? ''));
        if (in_array($langOption, ['id', 'en'], true)) {
            $this->wizardLang = $langOption;
        } elseif ($interactive && ! $this->option('no-interaction')) {
            $langChoice = $this->choice(
                'Pilih bahasa instalasi / Choose installation language',
                ['id' => 'Indonesia (default)', 'en' => 'English'],
                'id'
            );
            $this->wizardLang = $langChoice === 'en' ? 'en' : 'id';
        }

        $this->info($this->t('Memulai instalasi Pasak (Bulwark)...', 'Starting Pasak (Bulwark) installation...'));
        $this->newLine();

        // 1. Pilihan cakupan: API dan/atau Web (bawaan: keduanya dipasang).
        //    --api-only adalah jalan pintas untuk "API ya, Web tidak".
        if ($apiOnly) {
            $withoutWeb = true;
        }

        $apiChoiceGiven = $apiOnly || $withoutApi || $withoutRoutes;
        $webChoiceGiven = $withDashboard || $withoutWeb || $withoutRoutes || $apiOnly;

        $withApi = ! $withoutApi && ! $withoutRoutes;
        if (! $apiChoiceGiven && $interactive) {
            $withApi = $this->confirm(
                $this->t(
                    'Pasang dukungan API? (headless REST API di /api/pasak/*)',
                    'Install API support? (headless REST API at /api/pasak/*)'
                ),
                true
            );
        }

        if (! $withApi) {
            $withoutApi = true;
        }

        // 2. Dashboard web Blade murni (bawaan: ya).
        if (! $webChoiceGiven && $interactive) {
            $withDashboard = $this->confirm(
                $this->t(
                    'Pasang dashboard web Blade murni? (prefix /pasak, wajib login)',
                    'Install the plain Blade web dashboard? (/pasak prefix, login required)'
                ),
                true
            );
        }

        if ($withoutWeb) {
            $withDashboard = false;
        }

        if ($apiOnly && $withApi && ! $withDashboard) {
            $this->comment($this->t(
                'Mode API-only: halaman dashboard dilewati, yang dipasang hanya inti + keamanan.',
                'API-only mode: dashboard pages skipped, only core + security will be installed.'
            ));
        }

        $dashboardChoice = $withDashboard ? 'plain' : 'none';

        // 3. Cek kelengkapan lingkungan sebelum memasang apa pun.
        $this->runReadinessCheck();

        $migrated = false;

        // 1. Publish Config
        $this->comment('Mempublikasikan berkas konfigurasi...');
        $this->call('vendor:publish', [
            '--tag' => 'pasak-config',
            '--force' => $force,
        ]);

        // 2. Publish Migrations
        $this->comment('Mempublikasikan berkas migrasi database...');
        $this->call('vendor:publish', [
            '--tag' => 'pasak-migrations',
            '--force' => $force,
        ]);

        // 3. Publish Nginx Configuration
        if (! $withoutNginx) {
            $this->comment(
                'Mempublikasikan konfigurasi server Nginx (nginx.conf)...',
            );
            $this->call('vendor:publish', [
                '--tag' => 'pasak-nginx',
                '--force' => $force,
            ]);
        }

        // 3b. Publish Apache2 VirtualHost Configuration
        if (! $withoutApache) {
            $this->comment(
                'Mempublikasikan konfigurasi server Apache2 (apache2.conf)...',
            );
            $this->call('vendor:publish', [
                '--tag' => 'pasak-apache',
                '--force' => $force,
            ]);
        }

        // 4. Publish / Hardening Apache .htaccess (tidak pernah mengedit milik user)
        if (! $withoutHtaccess) {
            $this->comment(
                'Menyiapkan aturan hardening Apache (public/.htaccess)...',
            );
            $this->applyHtaccessHardening($force || (bool) $this->option('with-htaccess'));
        }

        // 5. Publish Default Blocked (403) Page
        if (! $withoutViews) {
            $this->comment(
                'Mempublikasikan halaman blokir default (resources/views/errors/blocked.blade.php)...',
            );
            $this->call('vendor:publish', [
                '--tag' => 'pasak-views',
                '--force' => $force,
            ]);
        }

        // 6. Sinkronkan direktori dashboard: hapus sisa stack lain bila ada.
        $this->syncDashboardChoice($withDashboard);

        // 6b. Publish dashboard Blade murni (tanpa Livewire/Inertia).
        if ($withDashboard) {
            $this->comment(
                'Mempublikasikan dashboard Blade murni (resources/views/pages/pasak)...',
            );
            $this->call('vendor:publish', [
                '--tag' => 'plain-views',
                '--force' => $force,
            ]);
        }

        // 8. Auto-inject trait HasPasakSecurityRelations ke model User
        if (! $withoutUserTrait) {
            $this->comment(
                'Menyematkan trait HasPasakSecurityRelations ke model User...',
            );
            $this->injectUserTrait();
        }

        // 9. Auto-register WAF middlewares ke bootstrap/app.php atau app/Http/Kernel.php
        if (! $withoutMiddleware) {
            $this->comment(
                'Mendaftarkan middleware proteksi WAF ke aplikasi host...',
            );
            $this->registerMiddlewareInApp();
        }

        // 10. Ensure API support & publish customizable route files (routes/pasak.php & routes/pasak-api.php)
        $wantWebRoutes = ! $withoutRoutes && $dashboardChoice !== 'none';
        $wantApiRoutes = ! $withoutRoutes && ! $withoutApi;

        if ($wantWebRoutes || $wantApiRoutes) {
            $this->comment(
                $wantWebRoutes && $wantApiRoutes
                    ? 'Menyiapkan rute mandiri (routes/pasak.php & routes/pasak-api.php)...'
                    : ($wantWebRoutes
                        ? 'Menyiapkan rute mandiri web (routes/pasak.php)...'
                        : 'Menyiapkan rute mandiri API (routes/pasak-api.php)...')
            );
            $this->publishSecurityRoutes($force, ! $wantApiRoutes, ! $wantWebRoutes, $dashboardChoice);
        }

        // 11. Append / Update Environment Variables with rich comments to .env & .env.example
        if (! $withoutEnv) {
            $this->comment(
                'Menyematkan variabel konfigurasi dan panduan ke berkas .env...',
            );
            $this->appendEnvironmentVariables($withDashboard);
        }

        $this->newLine();
        $this->info($this->t('Instalasi aset berhasil diselesaikan!', 'Asset installation completed!'));
        $this->newLine();

        // 4. Tawarkan menjalankan migrasi database sekarang.
        if ($interactive && ! $migrated) {
            $migrated = $this->confirm(
                $this->t(
                    'Jalankan migrasi database sekarang? (php artisan migrate)',
                    'Run database migrations now? (php artisan migrate)'
                ),
                true
            );

            if ($migrated) {
                $this->call('migrate', ['--force' => true]);
            }
        }

        $dashboardPrefix = '/'.ltrim((string) config('pasak.dashboard.prefix', 'pasak'), '/');
        $dashboardBase = rtrim((string) config('app.url', 'http://localhost:8000'), '/').$dashboardPrefix;
        $dashboardUrls = array_map(
            fn (string $page): string => $page === '' ? $dashboardBase : $dashboardBase.'/'.$page,
            ['', 'threat-map', 'logs', 'blocked-ips', 'server', 'sessions', 'tickets', 'settings']
        );
        $doc = fn (string $path): string => '📖 '.$this->docs($path);
        $steps = [];
        $steps[] = $migrated
            ? [$this->t('Migrasi database: sudah dijalankan otomatis.', 'Database migrations: already executed automatically.'), $doc('getting-started/installation')]
            : [$this->t('Jalankan migrasi database:', 'Run database migrations:'), 'php artisan migrate', $doc('getting-started/installation')];
        if (! $withoutUserTrait) {
            $steps[] = [$this->t('Model User: Trait HasPasakSecurityRelations berhasil disematkan secara otomatis.', 'User model: HasPasakSecurityRelations trait injected automatically.'), $doc('getting-started/installation')];
        } else {
            $steps[] = [
                $this->t('Tambahkan trait HasPasakSecurityRelations ke model User:', 'Add the HasPasakSecurityRelations trait to your User model:'),
                'use Internal\Pasak\Concerns\HasPasakSecurityRelations;',
                $doc('getting-started/installation'),
            ];
        }
        if (! $withoutMiddleware) {
            $steps[] = [$this->t('Middleware WAF: PasakBlockIpAddress & PasakDetectSecurityThreats berhasil didaftarkan otomatis.', 'WAF middleware: PasakBlockIpAddress & PasakDetectSecurityThreats registered automatically.'), $doc('core-architecture/threat-detection-engine')];
        } else {
            $steps[] = [
                $this->t('Daftarkan middleware proteksi WAF di bootstrap/app.php (Laravel 11+) atau app/Http/Kernel.php (Laravel 10):', 'Register the WAF protection middleware in bootstrap/app.php (Laravel 11+) or app/Http/Kernel.php (Laravel 10):'),
                '\Internal\Pasak\Http\Middleware\PasakBlockIpAddress::class',
                '\Internal\Pasak\Http\Middleware\PasakDetectSecurityThreats::class',
                $doc('getting-started/installation'),
            ];
        }
        if ($wantWebRoutes || $wantApiRoutes) {
            $steps[] = [($wantWebRoutes && $wantApiRoutes)
                ? $this->t('Rute kustom: berkas routes/pasak.php (web) dan routes/pasak-api.php (API) siap dikustomisasi.', 'Custom routes: routes/pasak.php (web) and routes/pasak-api.php (API) are ready to customize.')
                : ($wantWebRoutes
                    ? $this->t('Rute kustom web: berkas routes/pasak.php siap dikustomisasi.', 'Custom web routes: routes/pasak.php is ready to customize.')
                    : $this->t('Rute kustom API: berkas routes/pasak-api.php siap dikustomisasi.', 'Custom API routes: routes/pasak-api.php is ready to customize.')),
                $doc('api-reference/api-overview')];
        }
        $steps[] = [
            $this->t('Sesuaikan variabel PASAK_* di berkas .env, minimal:', 'Adjust the PASAK_* variables in your .env file, at minimum:'),
            'PASAK_IP_WHITELIST=127.0.0.1,::1,<ip-kantor-anda>   # agar IP internal tidak pernah diblokir',
            'PASAK_SUPPORT_EMAIL=security@domain-anda.test          # tampil di halaman blokir 403',
            $doc('getting-started/configuration'),
        ];
        $steps[] = [
            $this->t('Aktifkan scheduler Laravel agar pembersihan log & heartbeat berjalan (sekali saja):', 'Enable the Laravel scheduler so log pruning & heartbeat run (one time):'),
            '* * * * * cd /path-ke-proyek && php artisan schedule:run >> /dev/null 2>&1',
            $doc('cli-automation/scheduled-tasks'),
        ];
        $steps[] = [
            $this->t('Buat baseline integritas berkas (deteksi backdoor):', 'Create the file integrity baseline (backdoor detection):'),
            'php artisan pasak:baseline --create',
            $doc('security-modules/server-security-and-webshells'),
        ];
        $steps[] = [
            $this->t('Uji cepat (dari IP yang TIDAK di-whitelist — localhost bawaan dikecualikan via PASAK_IP_WHITELIST):', 'Quick test (from a NON-whitelisted IP — localhost is excluded by default via PASAK_IP_WHITELIST):'),
            'curl -I https://domain-anda.test/                                  # 200',
            "curl -I 'https://domain-anda.test/?q=%7B%7B7*7%7D%7D'               # 403",
            $doc('core-architecture/threat-detection-engine'),
        ];
        if (! $withoutNginx) {
            $steps[] = [$this->t('Web server Nginx: periksa dan sesuaikan nginx.conf di root proyek, lalu reload nginx.', 'Nginx web server: review and adjust nginx.conf at the project root, then reload nginx.'), $doc('webserver-hardening/nginx-hardened-waf')];
        }
        if (! $withoutApache) {
            $steps[] = [$this->t('Web server Apache2: periksa dan sesuaikan apache2.conf di root proyek, lalu reload apache2.', 'Apache2 web server: review and adjust apache2.conf at the project root, then reload apache2.'), $doc('webserver-hardening/apache-htaccess-hardening')];
        }
        if (! $withoutHtaccess) {
            $steps[] = File::exists(public_path('.htaccess.pasak'))
                ? [$this->t('Apache / cPanel: .htaccess milik Anda TIDAK diubah. Aturan hardening siap di public/.htaccess.pasak — tinjau lalu aktifkan manual (`--with-htaccess` untuk aktivasi otomatis).', 'Apache / cPanel: your .htaccess was NOT modified. Hardening rules are ready in public/.htaccess.pasak — review then activate manually (`--with-htaccess` for automatic activation).'), $doc('webserver-hardening/apache-htaccess-hardening')]
                : [$this->t('Apache / cPanel: public/.htaccess telah diperkuat terhadap upload webshell, double extension, dotfile, dan file backup.', 'Apache / cPanel: public/.htaccess has been hardened against webshell uploads, double extensions, dotfiles, and backup files.'), $doc('webserver-hardening/apache-htaccess-hardening')];
        }
        if (! $withoutViews) {
            $steps[] = [$this->t('Halaman blokir: sesuaikan resources/views/errors/blocked.blade.php dengan branding aplikasi Anda.', 'Blocked page: customize resources/views/errors/blocked.blade.php with your app branding.'), $doc('integration-guides/frontend-blade-livewire')];
        }
        if ($withDashboard) {
            $steps[] = array_merge(
                [$this->t("Dashboard Blade murni: siap diakses pada prefix {$dashboardPrefix} (wajib login, tanpa Livewire/Inertia).", "Plain Blade dashboard: ready at the {$dashboardPrefix} prefix (login required, no Livewire/Inertia needed).")],
                $dashboardUrls,
                [$doc('integration-guides/frontend-blade-livewire')]
            );
        } elseif ($withApi) {
            $steps[] = [$this->t('Dashboard monitoring (opsional): sediakan kapan saja dengan php artisan pasak:install --with-dashboard.', 'Monitoring dashboard (optional): provide anytime with php artisan pasak:install --with-dashboard.'), $doc('integration-guides/frontend-blade-livewire')];
        } else {
            $steps[] = [$this->t('Mode inti saja: kelola via perintah Artisan (pasak:unblock-ip, pasak:scan-logs, ...). Tambahkan API/web kapan saja dengan php artisan pasak:install --force.', 'Core-only mode: manage via Artisan commands (pasak:unblock-ip, pasak:scan-logs, ...). Add API/web anytime with php artisan pasak:install --force.'), $doc('api-reference/api-overview')];
        }
        if (config('pasak.access_log.auto_scan_enabled', false) === false) {
            $steps[] = [
                $this->t('Opsional: pindai access log web server untuk serangan yang tak sampai ke PHP:', 'Optional: scan the web server access log for attacks that never reach PHP:'),
                'php artisan pasak:scan-logs --since="24 hours ago"',
                $doc('security-modules/access-log-streaming'),
            ];
        }

        $this->line(
            $this->t('<fg=cyan>Langkah selanjutnya agar tidak pusing — ikuti berurutan:</>', '<fg=cyan>Next steps so nothing gets confusing — follow in order:</>'),
        );
        foreach ($steps as $index => $lines) {
            $number = $index + 1;
            $this->line("  {$number}. {$lines[0]}");
            foreach (array_slice($lines, 1) as $detail) {
                $this->line("     <fg=yellow>{$detail}</>");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Cek kelengkapan lingkungan (langkah 3): PHP, Laravel, database, .env.
     * Hanya memberi peringatan — tidak pernah menghentikan instalasi.
     */
    protected function runReadinessCheck(): void
    {
        $this->comment($this->t(
            'Memeriksa kelengkapan lingkungan...',
            'Checking environment readiness...'
        ));

        $checks = [];

        $checks[] = [version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP '.PHP_VERSION.' (>= 8.2)'];
        $checks[] = [class_exists(Application::class), 'Framework Laravel ('.Application::VERSION.')'];

        try {
            DB::connection()->getPdo();
            $checks[] = [true, $this->t('Koneksi database', 'Database connection')];
        } catch (\Throwable) {
            $checks[] = [false, $this->t('Koneksi database (periksa DB_* di .env)', 'Database connection (check DB_* in .env)')];
        }

        $checks[] = [File::exists(base_path('.env')), $this->t('Berkas .env', '.env file')];
        $checks[] = [is_writable(base_path('composer.json')), $this->t('composer.json dapat ditulis', 'composer.json is writable')];

        foreach ($checks as [$ok, $label]) {
            $this->line($ok ? "  ✓ {$label}" : "  ✗ {$label}");
        }

        $this->line('  📖 '.$this->docs('getting-started/installation'));
        $this->newLine();
    }

    /**
     * Hapus sisa direktori dashboard React bila ada (migrasi dari versi lama),
     * sehingga instalasi selalu Blade murni.
     */
    protected function syncDashboardChoice(bool $withDashboard): void
    {
        foreach (['js/pages/pasak', 'js/components/pasak'] as $relative) {
            $path = resource_path($relative);
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
                $this->line("  ✓ Direktori resources/{$relative} (React, warisan) dibersihkan — dashboard kini Blade murni.");
            }
        }

        if (! $withDashboard) {
            $path = resource_path('views/pages/pasak');
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
                $this->line('  ✓ Direktori resources/views/pages/pasak dibersihkan (tanpa dashboard).');
            }
        }
    }

    /**
     * Terapkan aturan hardening pada public/.htaccess.
     */
    protected function applyHtaccessHardening(bool $force): void
    {
        $stubPath = __DIR__.'/../../../stubs/htaccess.stub';
        if (! File::exists($stubPath)) {
            $stubPath = dirname(__DIR__, 2).'/stubs/htaccess.stub';
        }

        if (! File::exists($stubPath)) {
            $this->warn(
                'Berkas stub htaccess tidak ditemukan pada path: '.$stubPath,
            );

            return;
        }

        $stubContent = File::get($stubPath);
        $publicDir = public_path();
        if (! File::isDirectory($publicDir)) {
            File::makeDirectory($publicDir, 0755, true, true);
        }

        $htaccessPath = public_path('.htaccess');
        $pasakHtaccessPath = public_path('.htaccess.pasak');

        if (! File::exists($htaccessPath)) {
            // Belum ada .htaccess: aman membuat baru dari stub lengkap.
            File::put($htaccessPath, $stubContent);
            $this->info(
                '  ✓ Berkas public/.htaccess berhasil dibuat dengan aturan hardening lengkap.',
            );

            return;
        }

        if ($force) {
            // Eksplisit (--force / --with-htaccess): cadangkan lalu aktifkan.
            $backupPath = public_path('.htaccess.backup-'.date('Ymd_His'));
            File::copy($htaccessPath, $backupPath);
            $this->line("  ℹ Cadangan dibuat di: <fg=gray>{$backupPath}</>");
            File::put($htaccessPath, $stubContent);
            $this->info(
                '  ✓ Berkas public/.htaccess berhasil diperbarui dengan aturan hardening lengkap.',
            );

            return;
        }

        // Bawaan: JANGAN sentuh .htaccess milik user. Tulis berkas BARU
        // berdampingan agar dapat ditinjau sebelum diaktifkan manual.
        File::put($pasakHtaccessPath, $stubContent);
        $this->info(
            '  ✓ Berkas public/.htaccess milik Anda TIDAK diubah.',
        );
        $this->line(
            '  ℹ Aturan hardening lengkap ditulis ke berkas BARU: <fg=yellow>public/.htaccess.pasak</>',
        );
        $this->line(
            '  ℹ Untuk mengaktifkan: tinjau isinya, cadangkan .htaccess lama, lalu `mv public/.htaccess.pasak public/.htaccess`.',
        );
    }

    /**
     * Sematkan variabel konfigurasi lingkungan dan penjelasannya ke berkas .env & .env.example.
     */
    protected function appendEnvironmentVariables(bool $withDashboard = false): void
    {
        $stubPath = __DIR__.'/../../../stubs/env.stub';
        if (! File::exists($stubPath)) {
            $stubPath = dirname(__DIR__, 2).'/stubs/env.stub';
        }

        if (! File::exists($stubPath)) {
            return;
        }

        $stubContent = File::get($stubPath);

        if ($withDashboard) {
            $stubContent = str_replace(
                'PASAK_DASHBOARD_ENABLED=false',
                'PASAK_DASHBOARD_ENABLED=true',
                $stubContent
            );
        }

        $stubContent =
            '

'.
            trim($stubContent).
            '
';
        $envTargets = ['.env', '.env.example'];

        foreach ($envTargets as $envFile) {
            $targetPath = base_path($envFile);
            if (! File::exists($targetPath)) {
                continue;
            }

            $currentContent = File::get($targetPath);
            if (str_contains($currentContent, 'PASAK_MONITOR_ENABLED')) {
                if ($withDashboard) {
                    $updated = preg_replace(
                        '/PASAK_DASHBOARD_ENABLED=(false|0)/',
                        'PASAK_DASHBOARD_ENABLED=true',
                        $currentContent
                    );
                    $updated = preg_replace(
                        '/PASAK_DASHBOARD_DRIVER=[^\r\n]+/',
                        'PASAK_DASHBOARD_DRIVER=blade',
                        (string) $updated
                    );
                    if ($updated !== null && $updated !== $currentContent) {
                        File::put($targetPath, $updated);
                        $this->info("  ✓ Berkas {$envFile} diperbarui: PASAK_DASHBOARD_ENABLED=true (blade).");
                    }
                }
                $this->line(
                    "  ✓ Berkas {$envFile} sudah memiliki variabel konfigurasi keamanan.",
                );

                continue;
            }

            File::append($targetPath, $stubContent);
            $this->info(
                "  ✓ Variabel konfigurasi keamanan berhasil ditambahkan ke berkas {$envFile}.",
            );
        }
    }

    /**
     * Sematkan trait HasPasakSecurityRelations ke model User secara otomatis.
     */
    protected function injectUserTrait(): void
    {
        $possiblePaths = [
            app_path('Models/User.php'),
            app_path('User.php'),
        ];

        $userModelClass = config('pasak.user_model');
        if (is_string($userModelClass) && class_exists($userModelClass)) {
            try {
                $reflector = new \ReflectionClass($userModelClass);
                $filename = $reflector->getFileName();
                if ($filename && File::exists($filename)) {
                    array_unshift($possiblePaths, $filename);
                }
            } catch (\Throwable) {
                // Abaikan jika refleksi gagal
            }
        }

        $userPath = null;
        foreach ($possiblePaths as $path) {
            if ($path && File::exists($path)) {
                $userPath = $path;
                break;
            }
        }

        if (! $userPath) {
            $this->line('  ℹ Model User tidak ditemukan di lokasi standar (app/Models/User.php). Lewati injeksi trait.');

            return;
        }

        $content = File::get($userPath);

        if (str_contains($content, 'HasPasakSecurityRelations') || str_contains($content, 'HasSecurityRelations')) {
            $this->line('  ✓ Model User sudah memiliki trait HasPasakSecurityRelations.');

            return;
        }

        // 1. Tambahkan import `use Internal\Pasak\Concerns\HasPasakSecurityRelations;`
        $importStatement = "use Internal\\Pasak\\Concerns\\HasPasakSecurityRelations;\n";
        if (! str_contains($content, 'use Internal\\Pasak\\Concerns\\HasPasakSecurityRelations;')) {
            $lastUsePos = strrpos($content, "\nuse ");
            if ($lastUsePos !== false) {
                $endOfLine = strpos($content, "\n", $lastUsePos + 1);
                if ($endOfLine !== false) {
                    $content = substr_replace($content, $importStatement, $endOfLine + 1, 0);
                }
            } elseif (preg_match('/namespace\s+[^;]+;\s*/', $content, $nsMatches, PREG_OFFSET_CAPTURE)) {
                $nsEnd = $nsMatches[0][1] + strlen($nsMatches[0][0]);
                $content = substr_replace($content, "\n".$importStatement, $nsEnd, 0);
            }
        }

        // 2. Tambahkan trait di dalam deklarasi kelas User
        $classPos = strpos($content, 'class User');
        if ($classPos !== false) {
            $classOpenBrace = strpos($content, '{', $classPos);
            if ($classOpenBrace !== false) {
                $afterBrace = substr($content, $classOpenBrace + 1);
                // Cocokkan `use Trait...;` yang berada di baris tersendiri (bukan @use pada PHPDoc)
                if (preg_match('/(?m)^\s*use\s+([^;]+);/', $afterBrace, $useMatches, PREG_OFFSET_CAPTURE)) {
                    $matchOffset = $classOpenBrace + 1 + $useMatches[1][1];
                    $existingTraits = $useMatches[1][0];
                    $replacement = 'HasPasakSecurityRelations, '.trim($existingTraits);
                    $content = substr_replace($content, $replacement, $matchOffset, strlen($existingTraits));
                } else {
                    $content = substr_replace($content, "\n    use HasPasakSecurityRelations;", $classOpenBrace + 1, 0);
                }
            }
        }

        File::put($userPath, $this->normalizeHostPhpFile((string) $content));
        $this->info("  ✓ Trait HasPasakSecurityRelations berhasil disematkan ke model User ({$userPath}).");
    }

    /**
     * Normalisasi berkas PHP host: maksimal satu baris kosong antar blok
     * dan tepat satu newline di EOF (ramah Pint).
     */
    protected function normalizeHostPhpFile(string $content): string
    {
        $content = (string) preg_replace("/\n{3,}/", "\n\n", $content);

        return rtrim($content)."\n";
    }

    /**
     * Daftarkan middleware PasakBlockIpAddress dan PasakDetectSecurityThreats ke aplikasi host secara otomatis.
     */
    protected function registerMiddlewareInApp(): void
    {
        $bootstrapAppPath = base_path('bootstrap/app.php');
        $kernelPath = app_path('Http/Kernel.php');

        // Laravel 11 & 12 (bootstrap/app.php)
        if (File::exists($bootstrapAppPath)) {
            $content = File::get($bootstrapAppPath);

            if (
                (str_contains($content, 'BlockIpAddress') || str_contains($content, 'PasakBlockIpAddress')) &&
                (str_contains($content, 'DetectSecurityThreats') || str_contains($content, 'PasakDetectSecurityThreats'))
            ) {
                $this->line('  ✓ Middleware WAF sudah terdaftar di bootstrap/app.php.');

                return;
            }

            // Pola: ->withMiddleware(function (Middleware $middleware) ... {
            if (preg_match('/->withMiddleware\s*\(\s*function\s*\(\s*(?:Middleware\s+)?\$([a-zA-Z0-9_]+)\s*\)(?:\s*:\s*void)?\s*\{/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $varName = $matches[1][0];
                $snippet = "        \${$varName}->append([\n"
                    ."            \\Internal\\Pasak\\Http\\Middleware\\PasakBlockIpAddress::class,\n"
                    ."            \\Internal\\Pasak\\Http\\Middleware\\PasakDetectSecurityThreats::class,\n"
                    ."            \\Internal\\Pasak\\Http\\Middleware\\PasakInjectAnnouncementBanner::class,\n"
                    ."        ]);\n";

                $matchEnd = $matches[0][1] + strlen($matches[0][0]);

                $remaining = substr($content, $matchEnd);
                if (preg_match('/^\s*\/\/\s*\n/', $remaining, $commentMatch)) {
                    $content = substr_replace($content, "\n".$snippet, $matchEnd, strlen($commentMatch[0]));
                } else {
                    $content = substr_replace($content, "\n".$snippet, $matchEnd, 0);
                }

                File::put($bootstrapAppPath, $this->normalizeHostPhpFile($content));
                $this->info('  ✓ Middleware WAF berhasil didaftarkan di bootstrap/app.php.');

                return;
            }
        }

        // Laravel 10 (app/Http/Kernel.php)
        if (File::exists($kernelPath)) {
            $content = File::get($kernelPath);

            if (
                (str_contains($content, 'BlockIpAddress') || str_contains($content, 'PasakBlockIpAddress')) &&
                (str_contains($content, 'DetectSecurityThreats') || str_contains($content, 'PasakDetectSecurityThreats'))
            ) {
                $this->line('  ✓ Middleware WAF sudah terdaftar di app/Http/Kernel.php.');

                return;
            }

            $middlewareSnippet = "        \\Internal\\Pasak\\Http\\Middleware\\PasakBlockIpAddress::class,\n"
                ."        \\Internal\\Pasak\\Http\\Middleware\\PasakDetectSecurityThreats::class,\n"
                ."        \\Internal\\Pasak\\Http\\Middleware\\PasakInjectAnnouncementBanner::class,\n";

            if (preg_match('/protected\s+\$middleware\s*=\s*\[/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1] + strlen($matches[0][0]);
                $content = substr_replace($content, "\n".$middlewareSnippet, $pos, 0);
                File::put($kernelPath, $this->normalizeHostPhpFile($content));
                $this->info('  ✓ Middleware WAF berhasil didaftarkan di app/Http/Kernel.php.');

                return;
            }
        }

        $this->line('  ℹ Berkas bootstrap/app.php atau Kernel.php tidak dapat diperbarui secara otomatis. Silakan daftarkan middleware secara manual.');
    }

    /**
     * Pastikan rute API tersedia di host application (menjalankan install:api jika belum ada).
     */
    protected function ensureApiRoutesInstalled(): void
    {
        $apiRoutePath = base_path('routes/api.php');

        if (File::exists($apiRoutePath)) {
            $this->line('  ✓ Berkas routes/api.php sudah tersedia.');

            return;
        }

        // Di Laravel 11/12/13, rute API dipasang melalui perintah artisan install:api
        if ($this->getApplication()?->has('install:api')) {
            $this->comment('  Menyiapkan rute API bawaan Laravel (php artisan install:api)...');
            try {
                $this->call('install:api', [
                    '--no-interaction' => true,
                    '--without-migration-prompt' => true,
                ]);
                $this->info('  ✓ Dukungan rute API berhasil disiapkan via install:api.');
            } catch (\Throwable $e) {
                $this->warn('  ⚠ Gagal menjalankan install:api: '.$e->getMessage());
            }
        }

        clearstatcache(true, $apiRoutePath);

        // Jika berkas routes/api.php masih belum ada, buat secara mandiri
        if (! file_exists($apiRoutePath)) {
            $routesDir = base_path('routes');
            if (! File::isDirectory($routesDir)) {
                File::makeDirectory($routesDir, 0755, true, true);
            }

            File::put($apiRoutePath, <<<'PHP'
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

PHP
            );
            $this->info('  ✓ Berkas routes/api.php berhasil dibuat.');
        }

        // Pastikan pada Laravel 11/12/13 bootstrap/app.php memiliki konfigurasi api: __DIR__.'/../routes/api.php'
        $bootstrapAppPath = base_path('bootstrap/app.php');
        if (File::exists($bootstrapAppPath)) {
            $bootstrapContent = File::get($bootstrapAppPath);
            if (! str_contains($bootstrapContent, 'routes/api.php') && str_contains($bootstrapContent, '->withRouting(')) {
                if (preg_match('/web:\s*__DIR__\s*\.\s*\'\/[^\']+\'([^\n]*\n)/', $bootstrapContent, $m, PREG_OFFSET_CAPTURE)) {
                    $insertPos = $m[0][1] + strlen($m[0][0]);
                    $bootstrapContent = substr_replace(
                        $bootstrapContent,
                        "        api: __DIR__.'/../routes/api.php',\n",
                        $insertPos,
                        0
                    );
                    File::put($bootstrapAppPath, $this->normalizeHostPhpFile($bootstrapContent));
                    $this->info('  ✓ Konfigurasi rute API didaftarkan ke bootstrap/app.php.');
                }
            }
        }
    }

    /**
     * Publikasikan berkas rute pasak.php dan pasak-api.php ke direktori routes/ host application.
     *
     * Berkas routes/pasak.php selalu varian Blade murni.
     */
    protected function publishSecurityRoutes(bool $force, bool $withoutApi = false, bool $skipWeb = false, string $dashboardChoice = 'none'): void
    {
        if (! $withoutApi) {
            $this->ensureApiRoutesInstalled();
        }

        $routesDir = base_path('routes');
        if (! File::isDirectory($routesDir)) {
            File::makeDirectory($routesDir, 0755, true, true);
        }

        // 1. Publikasikan routes/pasak-api.php (dilewati bila dukungan API tidak dipasang).
        $apiStubPath = __DIR__.'/../../../stubs/routes/pasak-api.php.stub';
        if (! File::exists($apiStubPath)) {
            $apiStubPath = dirname(__DIR__, 2).'/stubs/routes/pasak-api.php.stub';
        }
        $targetApiPath = base_path('routes/pasak-api.php');

        if (! $withoutApi && File::exists($apiStubPath)) {
            if (! File::exists($targetApiPath) || $force) {
                File::copy($apiStubPath, $targetApiPath);
                $this->info('  ✓ Berkas routes/pasak-api.php berhasil dibuat.');
            } else {
                $this->line('  ✓ Berkas routes/pasak-api.php sudah ada.');
            }
        }

        // 2. Publikasikan routes/pasak.php (dilewati bila dashboard web tidak dipasang).
        if (! $skipWeb && $dashboardChoice !== 'none') {
            $webStubPath = __DIR__.'/../../../stubs/routes/pasak-blade.php.stub';
            if (! File::exists($webStubPath)) {
                $webStubPath = dirname(__DIR__, 2).'/stubs/routes/pasak-blade.php.stub';
            }
            if (! File::exists($webStubPath)) {
                $webStubPath = __DIR__.'/../../../stubs/routes/pasak.php.stub';
            }
            if (! File::exists($webStubPath)) {
                $webStubPath = dirname(__DIR__, 2).'/stubs/routes/pasak.php.stub';
            }
            $targetWebPath = base_path('routes/pasak.php');

            if (File::exists($webStubPath)) {
                if (! File::exists($targetWebPath) || $force) {
                    File::copy($webStubPath, $targetWebPath);
                    $this->info('  ✓ Berkas routes/pasak.php berhasil dibuat (Blade murni).');
                } else {
                    $this->line('  ✓ Berkas routes/pasak.php sudah ada.');
                }
            }

            // 3. Sertakan routes/pasak.php di routes/web.php jika belum ada
            $webRouteFile = base_path('routes/web.php');
            if (File::exists($webRouteFile)) {
                $webContent = File::get($webRouteFile);
                if (! str_contains($webContent, 'pasak.php')) {
                    File::append($webRouteFile, "\nrequire __DIR__.'/pasak.php';\n");
                    $this->info('  ✓ Berkas routes/web.php menyertakan routes/pasak.php.');
                }
            }
        }

        // 4. Sertakan routes/pasak-api.php di routes/api.php jika belum ada
        if (! $withoutApi) {
            $apiRouteFile = base_path('routes/api.php');
            if (File::exists($apiRouteFile)) {
                $apiContent = File::get($apiRouteFile);
                if (! str_contains($apiContent, 'pasak-api.php')) {
                    File::append($apiRouteFile, "\nrequire __DIR__.'/pasak-api.php';\n");
                    $this->info('  ✓ Berkas routes/api.php menyertakan routes/pasak-api.php.');
                }
            }
        }
    }
}
