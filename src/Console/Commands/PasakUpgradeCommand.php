<?php

namespace Internal\Pasak\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Internal\Pasak\Database\Seeders\PasakDefaultSettingsSeeder;
use Internal\Pasak\Services\PasakVersionCheckService;
use Internal\Pasak\Support\PasakDashboardSecret;
use Symfony\Component\Process\Process;
use Throwable;

class PasakUpgradeCommand extends Command
{
    protected $signature = 'pasak:upgrade
                            {--check : Hanya periksa apakah ada versi baru tanpa menjalankan pembaruan}
                            {--force : Paksa jalankan pembaruan dan sinkronisasi meskipun sudah di versi terbaru}
                            {--no-composer : Lewati pembaruan package via Composer (hanya sinkronkan aset lokal)}
                            {--no-migrate : Lewati eksekusi migrasi database}
                            {--sync-routes : Paksa sinkronkan ulang berkas routes/pasak.php dan routes/pasak-api.php}
                            {--sync-views : Paksa sinkronkan ulang berkas tampilan dashboard Blade}';

    protected $description = 'Periksa dan perbarui package Pasak (Bulwark) ke versi terbaru serta sinkronkan migrasi, rute, dan tampilan dashboard';

    public function handle(PasakVersionCheckService $versionChecker): int
    {
        $this->newLine();
        $this->line(
            '  <bg=blue;fg=white;options=bold> BULWARK SECURITY UPGRADE </>',
        );
        $this->newLine();

        $this->comment('Memeriksa ketersediaan versi terbaru...');

        $current = $versionChecker->getCurrentVersion();
        $latest = $versionChecker->getLatestVersion(true); // segarkan dari sumber rilis

        $this->line("  Versi Terpasang : <options=bold;fg=cyan>v{$current}</>");
        $this->line(
            '  Versi Terbaru   : '.
                ($latest
                    ? "<options=bold;fg=green>v{$latest}</>"
                    : '<comment>Tidak dapat menjangkau repositori rilis (offline/timeout)</comment>'),
        );
        $this->newLine();

        $hasUpdate = $latest && version_compare($latest, $current, '>');

        if ($this->option('check')) {
            if ($hasUpdate) {
                $this->warn("  Tersedia pembaruan: v{$current} → v{$latest}");
                $this->line(
                    '  Jalankan <info>php artisan pasak:upgrade</info> untuk memperbarui.',
                );
            } else {
                $this->info(
                    "  ✓ Package sudah berada pada versi terbaru (v{$current}).",
                );
            }

            return 0;
        }

        if (! $hasUpdate && ! $this->option('force')) {
            $this->info(
                "  ✓ Package sudah berada pada versi terbaru (v{$current}).",
            );

            if ($this->input->isInteractive()) {
                if (
                    ! $this->confirm(
                        'Apakah Anda ingin tetap menyinkronkan aset (migrasi database, rute, dan tampilan)?',
                        false,
                    )
                ) {
                    return 0;
                }
            } else {
                return 0;
            }
        }

        $this->info('Memulai proses pembaruan dan sinkronisasi...');
        $this->newLine();

        // 1. Composer Update
        if (! $this->option('no-composer')) {
            $this->comment('1/5. Memperbarui package via Composer...');
            $composer = $this->findComposer();
            $cmd = array_merge($composer, [
                'update',
                'robyajo/pasak',
                '--with-all-dependencies',
            ]);

            if (function_exists('proc_open')) {
                try {
                    $process = new Process($cmd, base_path());
                    $process->setTimeout(300);
                    $process->run(function ($type, $buffer) {
                        $this->output->write($buffer);
                    });

                    if (! $process->isSuccessful()) {
                        $this->warn(
                            '  Peringatan: Composer update selesai dengan kode non-nol. Melanjutkan sinkronisasi aset lokal...',
                        );
                    } else {
                        $this->info(
                            '  ✓ Composer package berhasil diperbarui.',
                        );
                    }
                } catch (Throwable $e) {
                    $this->warn(
                        '  Gagal menjalankan Composer otomatis: '.
                            $e->getMessage(),
                    );
                    $this->line(
                        '  Silakan jalankan <info>composer update robyajo/pasak</info> secara manual jika diperlukan.',
                    );
                }
            } else {
                $this->warn(
                    '  Fungsi proc_open dinonaktifkan di server. Silakan jalankan composer update secara manual.',
                );
            }
            $this->newLine();
        } else {
            $this->line(
                '  (1/5. Pembaruan Composer dilewati via --no-composer).',
            );
            $this->newLine();
        }

        $this->syncComposerConstraint(\Internal\Pasak\Services\PasakService::VERSION);

        // 2. Publish & Run Migrations
        $this->comment('2/5. Menyinkronkan dan menerapkan migrasi database...');
        $this->call('vendor:publish', [
            '--tag' => 'pasak-migrations',
            '--force' => true,
        ]);
        if (! $this->option('no-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }
        $this->info('  ✓ Berkas migrasi telah disinkronkan dan diterapkan.');
        $this->newLine();

        // 2b. Tanam nilai bawaan pengaturan yang belum ada (idempotent — yang sudah diset user tidak disentuh).
        try {
            $seededKeys = app(PasakDefaultSettingsSeeder::class)->run();

            if ($seededKeys === []) {
                $this->line(
                    '  ✓ Pengaturan keamanan bawaan sudah lengkap di database.',
                );
            } else {
                $this->info(
                    '  ✓ '.
                        count($seededKeys).
                        ' pengaturan keamanan baru ditanam ke database (atur dari dashboard /pasak/settings).',
                );
            }
        } catch (Throwable $e) {
            $this->comment(
                '  ℹ Nilai bawaan pengaturan belum ditanam. Jalankan: php artisan db:seed --class=Internal\\Pasak\\Database\\Seeders\\PasakDefaultSettingsSeeder --force',
            );
        }
        $this->newLine();

        // 3. Synchronize routes (tanpa menimpa kustomisasi host kecuali diminta).
        $this->comment('3/5. Menyinkronkan berkas rute kustom...');
        $this->migrateLegacyHostFiles();
        $this->migrateLegacyEnvAndConfig();
        $hasWebRoutes = File::exists(base_path('routes/pasak.php'));
        $hasApiRoutes = File::exists(base_path('routes/pasak-api.php'));

        if ($hasWebRoutes || $hasApiRoutes || $this->option('sync-routes')) {
            if ($this->option('sync-routes')) {
                $this->backupHostFiles([
                    base_path('routes/pasak.php'),
                    base_path('routes/pasak-api.php'),
                ]);
            }
            $this->call('vendor:publish', [
                '--tag' => 'pasak-routes',
                '--force' => (bool) $this->option('sync-routes'),
            ]);
            $this->info(
                '  ✓ Berkas routes/pasak.php dan routes/pasak-api.php telah disinkronkan.',
            );
        } else {
            $this->line(
                '  (Rute internal paket aktif secara otomatis, berkas rute lokal tidak ditemukan).',
            );
        }
        $this->newLine();

        // 4. Synchronize dashboard views (tanpa menimpa kustomisasi host kecuali diminta).
        $this->comment('4/5. Menyinkronkan tampilan dashboard monitoring...');
        $hasBladeViews = File::isDirectory(resource_path('views/pages/pasak'));

        if ($hasBladeViews || $this->option('sync-views')) {
            if ($this->option('sync-views')) {
                $this->backupHostFiles([resource_path('views/pages/pasak')]);
            }
            $this->call('vendor:publish', [
                '--tag' => 'plain-views',
                '--force' => (bool) $this->option('sync-views'),
            ]);
            $this->info(
                '  ✓ Tampilan dashboard Blade murni telah disinkronkan.',
            );
            $this->warnIfViewsOutdated(
                resource_path('views/pages/pasak/layout.blade.php'),
                'pasakAnnounce',
            );
            $this->warnIfViewsOutdated(
                resource_path('views/pages/pasak/layout.blade.php'),
                'pasak.dashboard.threat-map',
            );
            $this->warnIfViewsOutdated(
                resource_path('views/pages/pasak/settings.blade.php'),
                'auto_block_level_thresholds',
            );
        }

        if (File::exists(resource_path('views/errors/blocked.blade.php'))) {
            $this->call('vendor:publish', [
                '--tag' => 'pasak-views',
                '--force' => (bool) $this->option('sync-views'),
            ]);
            $this->info(
                '  ✓ Halaman error blokir (errors/blocked.blade.php) telah disinkronkan.',
            );
        }

        // 4b. Sync AI Agent Skills & AGENTS.md Guidelines
        $this->syncAgentSkillsAndGuidelines((bool) $this->option('sync-views') || (bool) $this->option('force'));
        $this->newLine();

        // 5. Clear Caches & Refresh
        $this->comment(
            '5/5. Membersihkan cache konfigurasi, rute, dan tampilan...',
        );
        try {
            $this->callSilent('optimize:clear');
        } catch (Throwable) {
            // Abaikan jika optimize:clear tidak didukung di environment tertentu
        }
        Cache::forget(PasakVersionCheckService::CACHE_KEY);
        $this->info('  ✓ Cache framework telah disegarkan.');
        $this->newLine();

        $refreshedVersion = $versionChecker->getCurrentVersion();
        $this->info(
            "  🎉 Pasak berhasil diperbarui dan disinkronkan (v{$refreshedVersion})!",
        );
        $this->newLine();

        return 0;
    }

    /**
     * Migrasikan jejak instalasi lama (era penamaan `security`) ke `pasak`
     * agar instalasi lama otomatis menyesuaikan: isi kustom user dipertahankan,
     * hanya nama berkas/direktori yang dipindahkan bila target baru belum ada.
     */
    protected function migrateLegacyHostFiles(): void
    {
        // Berkas rute era lama -> nama baru (beserta require di web.php/api.php).
        foreach (
            [
                ['routes/security.php', 'routes/pasak.php', 'routes/web.php'],
                [
                    'routes/security-api.php',
                    'routes/pasak-api.php',
                    'routes/api.php',
                ],
            ] as [$legacy, $current, $wiring]
        ) {
            $legacyPath = base_path($legacy);
            $currentPath = base_path($current);

            if (File::exists($legacyPath) && ! File::exists($currentPath)) {
                File::move($legacyPath, $currentPath);
                $this->info(
                    "  ✓ Berkas {$legacy} dipindahkan ke {$current} (kustomisasi dipertahankan).",
                );
            }

            $wiringPath = base_path($wiring);
            if (File::exists($wiringPath)) {
                $content = File::get($wiringPath);
                $updated = str_replace(
                    ["require __DIR__.'/".basename($legacy)."';"],
                    ["require __DIR__.'/".basename($current)."';"],
                    $content,
                );

                if ($updated !== $content) {
                    File::put($wiringPath, $updated);
                    $this->info(
                        "  ✓ Referensi rute di {$wiring} diperbarui ke {$current}.",
                    );
                }
            }
        }

        // Direktori dashboard era lama -> lokasi baru (bila target belum ada).
        foreach (
            [
                'views/pages/security' => 'views/pages/pasak',
                'js/pages/security' => 'js/pages/pasak',
                'js/components/security' => 'js/components/pasak',
            ] as $legacy => $current
        ) {
            $legacyPath = resource_path($legacy);
            $currentPath = resource_path($current);

            if (
                File::isDirectory($legacyPath) &&
                ! File::isDirectory($currentPath)
            ) {
                File::moveDirectory($legacyPath, $currentPath);
                $this->info(
                    "  ✓ Direktori resources/{$legacy} dipindahkan ke resources/{$current}.",
                );
            }
        }

        // Konfigurasi era lama -> nama baru (bila target belum ada).
        $legacyConfig = config_path('security.php');
        $currentConfig = config_path('pasak.php');

        if (File::exists($legacyConfig) && ! File::exists($currentConfig)) {
            File::copy($legacyConfig, $currentConfig);
            $this->info(
                '  ✓ config/security.php disalin ke config/pasak.php (asli dibiarkan sebagai cadangan).',
            );
        }

        $this->line(
            '  ✓ Kunci config baru dicakup bawaan paket (tidak ada kustomisasi yang ditimpa).',
        );
    }

    /**
     * Sesuaikan instalasi lama ke standar baru tanpa menimpa kustomisasi:
     * salin nilai SECURITY_* ke PASAK_*, sediakan sidecar .htaccess.pasak,
     * dan peringatkan driver dashboard yang tidak cocok dengan stack host.
     *
     * Kunci config baru otomatis diisi dari bawaan paket saat runtime via
     * mergeConfigFrom, jadi tidak ada berkas config yang perlu ditulis.
     */
    protected function migrateLegacyEnvAndConfig(): void
    {
        // 1. .env: SECURITY_* lama -> PASAK_* baru (hanya kunci yang user sudah set).
        $stubKeys = $this->stubEnvKeys();

        foreach (['.env', '.env.example'] as $envFile) {
            $path = base_path($envFile);

            if (! File::exists($path)) {
                continue;
            }

            $content = File::get($path);
            $append = '';

            foreach ($stubKeys as $pasakKey) {
                if (
                    preg_match(
                        '/^'.preg_quote($pasakKey, '/').'=.*/m',
                        $content,
                    )
                ) {
                    continue;
                }

                $legacyKey = preg_replace('/^PASAK_/', 'SECURITY_', $pasakKey);

                if (
                    $legacyKey !== $pasakKey &&
                    preg_match(
                        '/^'.preg_quote($legacyKey, '/').'=(.*)$/m',
                        $content,
                        $m,
                    )
                ) {
                    $append .= "\n{$pasakKey}=".trim($m[1]);
                }
            }

            if ($append !== '') {
                File::append($path, "\n".$append."\n");
                $this->info(
                    "  ✓ Nilai lama SECURITY_* disalin ke kunci PASAK_* di {$envFile} (asli dipertahankan).",
                );
            }
        }

        // 1b. URL dashboard acak sekali pakai: lengkapi kunci yang belum ada
        // bila dashboard aktif, tanpa menimpa pilihan eksplisit pengguna.
        $this->ensureRotatingEnvKeys();

        // 2. config/pasak.php host versi lama: migrasikan default table_names ke prefix pasak_
        //    (kunci baru otomatis diisi dari bawaan paket saat runtime via mergeConfigFrom).
        $this->migrateLegacyTableNamesInConfig();
        // 2b. config/pasak.php host versi lama: sisipkan kunci URL dashboard acak
        // sekali pakai (nested, tidak tercakup mergeConfigFrom yang dangkal).
        $this->migrateDashboardRotatingKeysInConfig();
        $this->line(
            '  ✓ Kunci config baru dicakup bawaan paket (tidak ada kustomisasi yang ditimpa).',
        );

        // 3. Sidecar .htaccess.pasak untuk standar baru (tidak menyentuh milik user).
        $sidecar = public_path('.htaccess.pasak');

        if (! File::exists($sidecar)) {
            foreach (
                [
                    __DIR__.'/../../../stubs/htaccess.stub',
                    dirname(__DIR__, 2).'/stubs/htaccess.stub',
                ] as $stubPath
            ) {
                if (File::exists($stubPath)) {
                    try {
                        File::ensureDirectoryExists(dirname($sidecar));
                        File::copy($stubPath, $sidecar);
                        $this->info(
                            '  ✓ Berkas tinjauan public/.htaccess.pasak dibuat (milik Anda tidak diubah).',
                        );
                    } catch (Throwable $e) {
                        $this->warn(
                            '  ⚠ Gagal membuat public/.htaccess.pasak: '.
                                $e->getMessage(),
                        );
                    }
                    break;
                }
            }
        }

        // 4. Normalisasi driver dashboard warisan ke 'blade' (satu-satunya driver).
        $driver = strtolower(
            (string) config('pasak.dashboard.driver', 'blade'),
        );

        if (! in_array($driver, ['blade', 'plain'], true)) {
            $this->warn(
                "  ⚠ Driver dashboard '{$driver}' tidak lagi didukung (dashboard kini Blade murni). Gunakan PASAK_DASHBOARD_DRIVER=blade.",
            );
        }
    }

    /**
     * Lengkapi kunci URL dashboard acak sekali pakai pada .env lama bila
     * dashboard aktif, tanpa menimpa pilihan eksplisit pengguna.
     */
    protected function ensureRotatingEnvKeys(): void
    {
        if (! (bool) config('pasak.dashboard.enabled', false)) {
            return;
        }

        $defaults = [
            'PASAK_DASHBOARD_ROTATING' => 'true',
            'PASAK_DASHBOARD_TOKEN_LENGTH' => '16',
            'PASAK_DASHBOARD_SINGLE_USE' => 'true',
            'PASAK_DASHBOARD_GRACE_SECONDS' => '120',
        ];

        foreach (['.env', '.env.example'] as $envFile) {
            $path = base_path($envFile);

            if (! File::exists($path)) {
                continue;
            }

            $content = File::get($path);
            $original = $content;

            foreach ($defaults as $key => $value) {
                if (! str_contains($content, $key.'=')) {
                    $content = rtrim($content)."\n{$key}={$value}\n";
                }
            }

            if (! str_contains($content, PasakDashboardSecret::ENV_KEY.'=')) {
                $content =
                    rtrim($content).
                    "\n".
                    PasakDashboardSecret::ENV_KEY.
                    "=\n";
            }

            if ($content !== $original) {
                File::put($path, $content);
                $this->info(
                    "  ✓ Kunci URL acak sekali pakai dashboard dilengkapi di {$envFile} (pilihan eksplisit dipertahankan).",
                );
            }
        }

        // Nilai rahasia nyata hanya ditulis ke .env; .env.example dibiarkan
        // kosong agar tidak ikut ter-commit.
        $real = PasakDashboardSecret::ensureInEnvFile(
            base_path('.env'),
            false,
            true,
        );

        PasakDashboardSecret::ensureInEnvFile(
            base_path('.env.example'),
            false,
            false,
        );

        if ($real['generated']) {
            $this->info(
                '  ✓ PASAK_DASHBOARD_SECRET unik dibuat untuk berkas .env.',
            );
        }
    }

    /**
     * Sisipkan kunci URL dashboard acak sekali pakai ke config/pasak.php host
     * versi lama bila belum ada. Kunci nested tidak tercakup mergeConfigFrom
     * (dangkal), jadi tanpa ini env PASAK_DASHBOARD_ROTATING diabaikan.
     * Kustomisasi pengguna tidak disentuh (hanya sisip bila belum ada).
     */
    protected function migrateDashboardRotatingKeysInConfig(): void
    {
        $configPath = config_path('pasak.php');

        if (! File::exists($configPath)) {
            return;
        }

        $content = File::get($configPath);

        if (str_contains($content, "'rotating' =>")) {
            return;
        }

        $anchor =
            "'prefix' => env('PASAK_DASHBOARD_PREFIX', env('SECURITY_DASHBOARD_PREFIX', 'pasak')),";

        if (! str_contains($content, $anchor)) {
            return;
        }

        $block = <<<'PHP'
        'prefix' => env('PASAK_DASHBOARD_PREFIX', env('SECURITY_DASHBOARD_PREFIX', 'pasak')),
                // URL acak sekali pakai (moving-target): bila true, pintu stabil
                // "/pasak" selalu 302 ke "/pasak-{token}/" milik sesi pengguna.
                // Token diputar setiap akses HTML sehingga reload selalu berubah,
                // mis. /pasak-95856857476jdhjs5/logs lalu /pasak-634664873647jbdjshf/logs.
                'rotating' => (bool) env('PASAK_DASHBOARD_ROTATING', env('SECURITY_DASHBOARD_ROTATING', false)),
                'secret' => env('PASAK_DASHBOARD_SECRET', env('SECURITY_DASHBOARD_SECRET')),
                'token_length' => (int) env('PASAK_DASHBOARD_TOKEN_LENGTH', env('SECURITY_DASHBOARD_TOKEN_LENGTH', 16)),
                'single_use' => (bool) env('PASAK_DASHBOARD_SINGLE_USE', env('SECURITY_DASHBOARD_SINGLE_USE', true)),
                'grace_seconds' => (int) env('PASAK_DASHBOARD_GRACE_SECONDS', env('SECURITY_DASHBOARD_GRACE_SECONDS', 120)),
        PHP;

        $updated = str_replace($anchor, $block, $content);

        if ($updated !== $content) {
            File::put($configPath, $updated);
            $this->info(
                '  ✓ Kunci URL acak sekali pakai disisipkan ke config/pasak.php (kustomisasi Anda dipertahankan).',
            );
        }
    }

    /**
     * Perbarui default table_names di config/pasak.php host ke prefix pasak_
     * jika pengguna belum mengkustomisasinya ke nama tabel lain.
     */
    protected function migrateLegacyTableNamesInConfig(): void
    {
        $configPath = config_path('pasak.php');

        if (! File::exists($configPath)) {
            return;
        }

        $content = File::get($configPath);
        $replacements = [
            "'blocked_ips' => env('PASAK_BLOCKED_IPS_TABLE', env('SECURITY_BLOCKED_IPS_TABLE', 'blocked_ips'))" => "'blocked_ips' => env('PASAK_BLOCKED_IPS_TABLE', env('SECURITY_BLOCKED_IPS_TABLE', 'pasak_blocked_ips'))",
            "'blocked_ips' => env('PASAK_BLOCKED_IPS_TABLE', 'blocked_ips')" => "'blocked_ips' => env('PASAK_BLOCKED_IPS_TABLE', 'pasak_blocked_ips')",
            "'login_attempts' => env('PASAK_LOGIN_ATTEMPTS_TABLE', env('SECURITY_LOGIN_ATTEMPTS_TABLE', 'login_attempts'))" => "'login_attempts' => env('PASAK_LOGIN_ATTEMPTS_TABLE', env('SECURITY_LOGIN_ATTEMPTS_TABLE', 'pasak_login_attempts'))",
            "'login_attempts' => env('PASAK_LOGIN_ATTEMPTS_TABLE', 'login_attempts')" => "'login_attempts' => env('PASAK_LOGIN_ATTEMPTS_TABLE', 'pasak_login_attempts')",
            "'ip_unblock_requests' => env('PASAK_IP_UNBLOCK_REQUESTS_TABLE', env('SECURITY_IP_UNBLOCK_REQUESTS_TABLE', 'ip_unblock_requests'))" => "'ip_unblock_requests' => env('PASAK_IP_UNBLOCK_REQUESTS_TABLE', env('SECURITY_IP_UNBLOCK_REQUESTS_TABLE', 'pasak_ip_unblock_requests'))",
            "'ip_unblock_requests' => env('PASAK_IP_UNBLOCK_REQUESTS_TABLE', 'ip_unblock_requests')" => "'ip_unblock_requests' => env('PASAK_IP_UNBLOCK_REQUESTS_TABLE', 'pasak_ip_unblock_requests')",
            "'user_logins' => env('PASAK_USER_LOGINS_TABLE', env('SECURITY_USER_LOGINS_TABLE', 'user_logins'))" => "'user_logins' => env('PASAK_USER_LOGINS_TABLE', env('SECURITY_USER_LOGINS_TABLE', 'pasak_user_logins'))",
            "'user_logins' => env('PASAK_USER_LOGINS_TABLE', 'user_logins')" => "'user_logins' => env('PASAK_USER_LOGINS_TABLE', 'pasak_user_logins')",
            "'trusted_ips' => env('PASAK_TRUSTED_IPS_TABLE', env('SECURITY_TRUSTED_IPS_TABLE', 'trusted_ips'))" => "'trusted_ips' => env('PASAK_TRUSTED_IPS_TABLE', env('SECURITY_TRUSTED_IPS_TABLE', 'pasak_trusted_ips'))",
            "'trusted_ips' => env('PASAK_TRUSTED_IPS_TABLE', 'trusted_ips')" => "'trusted_ips' => env('PASAK_TRUSTED_IPS_TABLE', 'pasak_trusted_ips')",
            "'allowed_ips' => env('PASAK_ALLOWED_IPS_TABLE', 'allowed_ips')" => "'allowed_ips' => env('PASAK_ALLOWED_IPS_TABLE', 'pasak_allowed_ips')",
        ];

        $updated = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $content,
        );

        if ($updated !== $content) {
            File::put($configPath, $updated);
            $this->info(
                '  ✓ Nilai default nama tabel di config/pasak.php diperbarui ke prefix pasak_ (kustomisasi Anda dipertahankan).',
            );
        }
    }

    /**
     * @return array<int, string>
     */
    protected function stubEnvKeys(): array
    {
        foreach (
            [
                __DIR__.'/../../../stubs/env.stub',
                dirname(__DIR__, 2).'/stubs/env.stub',
            ] as $stubPath
        ) {
            if (! File::exists($stubPath)) {
                continue;
            }

            preg_match_all('/^(PASAK_[A-Z0-9_]+)=/m', File::get($stubPath), $m);

            // Kunci perilaku legacy di luar stub inti tetap didukung sebagai
            // override manual, jadi migrasi SECURITY_* -> PASAK_* harus tetap
            // mencakup semuanya.
            return array_values(
                array_unique(
                    array_merge($m[1], [
                        'PASAK_AUTO_BLOCK_ENABLED',
                        'PASAK_AUTO_BLOCK_THRESHOLD',
                        'PASAK_AUTO_BLOCK_WINDOW',
                        'PASAK_AUTO_BLOCK_DURATION',
                        'PASAK_AUTO_BLOCK_LEVELS',
                        'PASAK_AUTO_BLOCK_SCOPE',
                        'PASAK_AUTO_BLOCK_LEVEL_THRESHOLDS',
                        'PASAK_AUTO_BLOCK_COMBINED_THRESHOLD',
                        'PASAK_INSTANT_BLOCK_ENABLED',
                        'PASAK_INSTANT_BLOCK_DURATION',
                        'PASAK_INSTANT_BLOCK_SCOPE',
                        'PASAK_LOGIN_LOCKOUT_ENABLED',
                        'PASAK_LOGIN_LOCKOUT_THRESHOLD',
                        'PASAK_LOGIN_LOCKOUT_BASE_MINUTES',
                        'PASAK_LOGIN_LOCKOUT_MAX_MINUTES',
                        'PASAK_LOGIN_LOCKOUT_DECAY_MINUTES',
                        'PASAK_LOG_RETENTION_DAYS',
                        'PASAK_BLOCK_ENFORCEMENT',
                        'PASAK_MONITOR_ENABLED',
                        'PASAK_ANNOUNCEMENT_ENABLED',
                        'PASAK_VERSION_CHECK_ENABLED',
                        'PASAK_DASHBOARD_DRIVER',
                    ]),
                ),
            );
        }

        return [];
    }

    /**
     * Peringatkan bila tampilan terpublish kedaluwarsa (tidak memuat marker
     * fitur terbaru) agar user menjalankan --sync-views. Tidak mengubah apa pun.
     */
    protected function warnIfViewsOutdated(string $path, string $marker): void
    {
        if (! File::exists($path)) {
            return;
        }

        if (! str_contains(File::get($path), $marker)) {
            $this->warn(
                "  ⚠ Tampilan terpublish kedaluwarsa ({$this->relativeHostPath(
                    $path,
                )} tidak memuat fitur terbaru).",
            );
            $this->line(
                '    Jalankan <fg=yellow>php artisan pasak:upgrade --sync-views</fg=yellow> untuk memperbarui (kustomisasi dicadangkan dulu).',
            );
        }
    }

    protected function relativeHostPath(string $path): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base)
            ? substr($path, strlen($base))
            : $path;
    }

    /**
     * Cadangkan berkas/direktori host sebelum ditimpa agar kustomisasi
     * pengguna tidak pernah hilang diam-diam.
     *
     * @param  array<int, string>  $paths
     */
    protected function backupHostFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $backup = $path.'.backup-'.date('Ymd_His');

            try {
                if (File::isDirectory($path)) {
                    File::copyDirectory($path, $backup);
                } else {
                    File::copy($path, $backup);
                }
                $this->line("  ℹ Cadangan kustomisasi disimpan di: {$backup}");
            } catch (Throwable $e) {
                $this->warn(
                    '  ⚠ Gagal mencadangkan '.$path.': '.$e->getMessage(),
                );
            }
        }
    }

    /**
     * Temukan biner composer yang valid di lingkungan sistem.
     *
     * @return array<int, string>
     */
    protected function findComposer(): array
    {
        if (File::exists(base_path('composer.phar'))) {
            return [PHP_BINARY, base_path('composer.phar')];
        }

        return ['composer'];
    }

    /**
     * Sinkronkan skill AI (.agents/skills/pasak/SKILL.md) dan panduan AGENTS.md.
     */
    protected function syncAgentSkillsAndGuidelines(bool $force = false): void
    {
        $stubSkill = __DIR__.'/../../../stubs/skills/pasak/SKILL.md';
        $targetSkillDir = base_path('.agents/skills/pasak');
        $targetSkillFile = $targetSkillDir.'/SKILL.md';

        if (File::exists($stubSkill)) {
            File::ensureDirectoryExists($targetSkillDir);
            if (! File::exists($targetSkillFile) || $force) {
                File::copy($stubSkill, $targetSkillFile);
                $this->info('  ✓ Skill agen AI (.agents/skills/pasak/SKILL.md) disinkronkan.');
            }
        }

        $guidelinesBlock = <<<'MARKDOWN'
<!-- pasak-guidelines:start -->
## Pasak WAF & Security Guidelines (`robyajo/pasak`)

This application is protected by **`robyajo/pasak`** (Bulwark), a standalone headless Web Application Firewall (WAF) and security toolkit for Laravel.

- **Official Online Documentation**: https://pasak.is-a.dev/

### 1. Skills Activation
- Activate the **`pasak`** skill (`.agents/skills/pasak/SKILL.md`) whenever writing, refactoring, or testing WAF rules, IP blocking, custom security dashboards, REST APIs, or server audits.

### 2. Zero-Vendor-Edit Policy
- **NEVER modify files in `vendor/robyajo/pasak/`**. Any custom dashboard, controller, view, or API endpoint must be built inside the host application (`app/Http/Controllers/`, `resources/views/`, `routes/web.php`, `routes/api.php`).

### 3. Core Facade & Service Usage
- Always interact via `Internal\Pasak\Facades\Pasak`:
  - `Pasak::isBlocked($ip)` — Check if an IP is currently blocked.
  - `Pasak::blockIp($ip, $reason, $durationHours, $source, $userId, $scope)` — Block an IP or specific device.
  - `Pasak::unblockIp($ip)` — Lift an IP block.
  - `Pasak::stats()` — Retrieve threat and blocking statistics.
  - `Pasak::getSettings()` / `Pasak::updateSettings($array)` — Runtime database settings.
- Server security audits & SHA-256 integrity: use `Internal\Pasak\Services\PasakServerSecurityService`.

### 4. Testing & Verification
- Test all security endpoints with Pest/PHPUnit. Run `php artisan test` or `./vendor/bin/pest`.
<!-- pasak-guidelines:end -->
MARKDOWN;

        $this->syncAgentFile(base_path('AGENTS.md'), $guidelinesBlock);

        $claudeFile = base_path('CLAUDE.md');
        if (File::exists($claudeFile) && ! is_link($claudeFile)) {
            $this->syncAgentFile($claudeFile, $guidelinesBlock);
        }
    }

    protected function syncAgentFile(string $filePath, string $guidelinesBlock): void
    {
        $rel = $this->relativeHostPath($filePath);

        if (! File::exists($filePath)) {
            File::put($filePath, $guidelinesBlock.PHP_EOL);
            $this->info("  ✓ Berkas {$rel} dibuat dengan panduan Pasak.");

            return;
        }

        $content = File::get($filePath);
        $pattern = '/<!-- pasak-guidelines:start -->.*?<!-- pasak-guidelines:end -->/s';

        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, trim($guidelinesBlock), $content);
            if ($newContent !== null && $newContent !== $content) {
                File::put($filePath, $newContent);
                $this->info("  ✓ Panduan Pasak di {$rel} diperbarui.");
            }
        } else {
            $separator = str_ends_with($content, "\n") ? "\n" : "\n\n";
            File::put($filePath, $content.$separator.trim($guidelinesBlock).PHP_EOL);
            $this->info("  ✓ Panduan Pasak disematkan ke {$rel}.");
        }
    }

    /**
     * Selaraskan constraint versi robyajo/pasak di composer.json ke versi lengkap (^1.6.5).
     * Mencegah Composer hanya menuliskan constraint ringkas seperti ^1.6.
     */
    protected function syncComposerConstraint(string $version): void
    {
        $composerPath = base_path('composer.json');
        if (! File::exists($composerPath) || ! is_writable($composerPath)) {
            return;
        }

        $content = File::get($composerPath);
        $pattern = '/("robyajo\/pasak"\s*:\s*")([^"]+)(")/';

        if (preg_match($pattern, $content, $matches)) {
            $current = $matches[2];
            $target = "^{$version}";

            if ($current !== $target && ! in_array($current, ['@dev', 'dev-main', '*'], true)) {
                $newContent = preg_replace($pattern, '${1}'.$target.'${3}', $content, 1);
                if ($newContent !== null && $newContent !== $content) {
                    File::put($composerPath, $newContent);
                    $this->info("  ✓ Versi robyajo/pasak di composer.json diselaraskan ke \"{$target}\".");
                }
            }
        }
    }
}
