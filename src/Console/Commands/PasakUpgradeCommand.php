<?php

namespace Internal\Pasak\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Internal\Pasak\Database\Seeders\PasakDefaultSettingsSeeder;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Services\PasakVersionCheckService;
use Internal\Pasak\Support\PasakDashboardSecret;
use Symfony\Component\Process\Process;
use Throwable;

class PasakUpgradeCommand extends Command
{
    /**
     * Alias perintah konsol agar salah ketik umum seperti pasak:upgrae tetap berjalan mulus.
     *
     * @var array<int, string>
     */
    protected $aliases = ['pasak:upgrae'];

    protected $signature = 'pasak:upgrade
                            {--check : Hanya periksa apakah ada versi baru tanpa menjalankan pembaruan}
                            {--force : Paksa jalankan pembaruan dan sinkronisasi meskipun sudah di versi terbaru}
                            {--no-composer : Lewati pembaruan package via Composer (hanya sinkronkan aset lokal)}
                            {--no-migrate : Lewati eksekusi migrasi database}
                            {--no-backup : Lewati pencadangan berkas host sebelum ditimpa}
                            {--clean-backups : Hapus semua berkas cadangan (*.backup-*) lama di routes, views, dan config}
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

        $force = (bool) $this->option('force');

        $this->info('Memulai proses pembaruan dan sinkronisasi...');
        $this->newLine();

        // 1. Composer Update
        if (! $this->option('no-composer')) {
            $this->comment('1/6. Memperbarui package via Composer...');
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
                '  (1/6. Pembaruan Composer dilewati via --no-composer).',
            );
            $this->newLine();
        }

        $this->syncComposerConstraint(PasakService::VERSION);

        // 2. Publish & Run Migrations
        $this->comment('2/6. Menyinkronkan dan menerapkan migrasi database...');
        $existingMigrationFiles = File::isDirectory(database_path('migrations'))
            ? File::files(database_path('migrations'))
            : [];
        $existingMigrationNames = array_map(fn ($f) => $f->getFilename(), $existingMigrationFiles);

        $this->call('vendor:publish', [
            '--tag' => 'pasak-migrations',
            '--force' => true,
        ]);

        $afterMigrationFiles = File::isDirectory(database_path('migrations'))
            ? File::files(database_path('migrations'))
            : [];
        $afterMigrationNames = array_map(fn ($f) => $f->getFilename(), $afterMigrationFiles);

        $newlyPublishedCount = count(array_diff($afterMigrationNames, $existingMigrationNames));
        $pendingCountBefore = $this->detectPendingMigrations();
        $hasNewMigrations = $pendingCountBefore > 0 || $newlyPublishedCount > 0;
        $totalNewMigrations = max($pendingCountBefore, $newlyPublishedCount);

        if (! $this->option('no-migrate')) {
            if ($pendingCountBefore > 0) {
                $this->warn("  ⚠ Terdeteksi {$pendingCountBefore} migrasi database baru yang perlu diterapkan.");
                $this->call('migrate', ['--force' => true]);
                $this->info("  ✓ {$pendingCountBefore} migrasi database baru berhasil diterapkan ke database.");
            } else {
                $this->call('migrate', ['--force' => true]);
                $this->info('  ✓ Skema database mutakhir (tidak ada migrasi baru yang tertunda).');
            }
        } else {
            $this->comment('  (Migrasi database dilewati via --no-migrate).');
        }
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

        // 3. Synchronize routes & configs
        $this->comment('3/6. Menyinkronkan berkas rute dan konfigurasi...');
        $this->migrateLegacyHostFiles();
        $this->migrateLegacyEnvAndConfig();
        $hasWebRoutes = File::exists(base_path('routes/pasak.php'));
        $hasApiRoutes = File::exists(base_path('routes/pasak-api.php'));
        $syncRoutes = (bool) $this->option('sync-routes') || $force;

        if ($hasWebRoutes || $hasApiRoutes || $syncRoutes) {
            if ($hasWebRoutes || $hasApiRoutes) {
                $this->backupHostFiles([
                    base_path('routes/pasak.php'),
                    base_path('routes/pasak-api.php'),
                ]);
            }
            $this->call('vendor:publish', [
                '--tag' => 'pasak-routes',
                '--force' => $syncRoutes,
            ]);
            if ($syncRoutes) {
                $stubDir = __DIR__.'/../../../stubs/routes';
                File::ensureDirectoryExists(base_path('routes'));
                if (File::exists($stubDir.'/pasak.php.stub')) {
                    File::copy($stubDir.'/pasak.php.stub', base_path('routes/pasak.php'));
                }
                if (File::exists($stubDir.'/pasak-api.php.stub')) {
                    File::copy($stubDir.'/pasak-api.php.stub', base_path('routes/pasak-api.php'));
                }
            }
            $this->ensureMissingRoutesInHostFiles();
            $this->info(
                '  ✓ Berkas routes/pasak.php dan routes/pasak-api.php telah disinkronkan ke versi terbaru.',
            );
        } else {
            $this->line(
                '  (Rute internal paket aktif secara otomatis, berkas rute lokal tidak ditemukan).',
            );
        }
        $this->newLine();

        // 4. Synchronize dashboard views
        $this->comment('4/6. Menyinkronkan tampilan dashboard monitoring...');
        $hasBladeViews = File::isDirectory(resource_path('views/pages/pasak'));
        $syncViews = (bool) $this->option('sync-views') || $force;

        if ($hasBladeViews || $syncViews) {
            if ($hasBladeViews) {
                $this->backupHostFiles([resource_path('views/pages/pasak')]);
            }
            $this->call('vendor:publish', [
                '--tag' => 'plain-views',
                '--force' => $syncViews,
            ]);
            if ($syncViews) {
                $stubViews = __DIR__.'/../../../stubs/blade/pages/pasak';
                if (File::isDirectory($stubViews)) {
                    File::ensureDirectoryExists(resource_path('views/pages/pasak'));
                    File::copyDirectory($stubViews, resource_path('views/pages/pasak'));
                }
            }
            $this->info(
                '  ✓ Tampilan dashboard Blade murni telah disinkronkan ke versi terbaru.',
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
                resource_path('views/pages/pasak/layout.blade.php'),
                'sec-sidebar-version',
            );
            $this->warnIfViewsOutdated(
                resource_path('views/pages/pasak/settings.blade.php'),
                'auto_block_level_thresholds',
            );
            $this->warnIfViewsOutdated(
                resource_path('views/pages/pasak/server.blade.php'),
                'serverLocationForm',
            );
        }

        $hasBlockedView = File::exists(resource_path('views/errors/blocked.blade.php'));
        $syncBlocked = (bool) $this->option('sync-views') || $force;

        if ($hasBlockedView || $syncBlocked) {
            if ($hasBlockedView) {
                $this->backupHostFiles([resource_path('views/errors/blocked.blade.php')]);
            }
            $this->call('vendor:publish', [
                '--tag' => 'pasak-views',
                '--force' => $syncBlocked,
            ]);
            if ($syncBlocked) {
                $stubBlocked = __DIR__.'/../../../stubs/blocked.blade.php';
                if (File::exists($stubBlocked)) {
                    File::ensureDirectoryExists(resource_path('views/errors'));
                    File::copy($stubBlocked, resource_path('views/errors/blocked.blade.php'));
                }
                $stubThrottle = __DIR__.'/../../../stubs/pasak-login-throttle.blade.php';
                if (File::exists($stubThrottle)) {
                    File::ensureDirectoryExists(resource_path('views/errors'));
                    File::copy($stubThrottle, resource_path('views/errors/pasak-login-throttle.blade.php'));
                }
            }
            $this->info(
                '  ✓ Halaman error blokir (errors/blocked.blade.php) telah disinkronkan ke versi terbaru.',
            );
        }

        // 4b. Sync AI Agent Skills & AGENTS.md Guidelines
        $this->syncAgentSkillsAndGuidelines($syncViews || $force);
        $this->newLine();

        // 5. Cleanup Obsolete Legacy Files
        $this->comment('5/6. Membersihkan berkas dan direktori usang dari versi lama...');
        $this->cleanupObsoleteFiles($force);
        $this->newLine();

        // 6. Clear Caches & Refresh
        $this->comment(
            '6/6. Membersihkan cache konfigurasi, rute, dan tampilan...',
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

        $remainingPending = $this->detectPendingMigrations();
        if ($remainingPending > 0) {
            $this->warn('  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->warn("  🔔 PERHATIAN: Terdapat {$remainingPending} migrasi database baru yang belum dijalankan!");
            $this->line('  Harap segera jalankan perintah migrasi berikut untuk memperbarui database:');
            $this->newLine();
            $this->line('    <options=bold;fg=yellow>php artisan migrate</>');
            $this->line('    <comment>(atau <info>php artisan migrate --force</info> pada server production)</comment>');
            $this->warn('  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->newLine();
        } elseif ($hasNewMigrations) {
            $countLabel = $totalNewMigrations > 0 ? " ({$totalNewMigrations} migrasi baru)" : '';
            $this->line('  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->line("  <options=bold;fg=cyan>🔔 PEMBERITAHUAN MIGRASI DATABASE{$countLabel}:</>");
            $this->line('  Pembaruan versi ini menyertakan migrasi struktur tabel database.');
            $this->line('  Pastikan Anda atau tim menjalankan migrasi di seluruh server / lingkungan:');
            $this->newLine();
            $this->line('    <options=bold;fg=yellow>php artisan migrate</>');
            $this->line('    <comment>(atau <info>php artisan migrate --force</info> pada server production)</comment>');
            $this->line('  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->newLine();
        }

        return 0;
    }

    /**
     * Migrasikan jejak instalasi lama (era penamaan `security`) ke `pasak`
     * agar instalasi lama otomatis menyesuaikan: isi kustom user dipertahankan,
     * hanya nama berkas/direktori yang dipindahkan bila target baru belum ada.
     */
    /**
     * Pastikan rute-rute baru (seperti lokasi & waktu server) disematkan ke
     * berkas rute lokal host jika pengguna belum menggunakan flag --sync-routes.
     */
    protected function ensureMissingRoutesInHostFiles(): void
    {
        $webRoutePath = base_path('routes/pasak.php');
        if (File::exists($webRoutePath)) {
            $content = File::get($webRoutePath);
            if (! str_contains($content, "'server.location'")) {
                $search = "Route::post('server/suspicious-files/unverify', [PasakServerController::class, 'unverifySuspiciousFile'])->name('server.suspicious-files.unverify');";
                if (str_contains($content, $search)) {
                    $insert = $search."\n    Route::post('server', [PasakServerController::class, 'updateLocation']);\n    Route::post('server/location', [PasakServerController::class, 'updateLocation'])->name('server.location');";
                    $content = str_replace($search, $insert, $content);
                    File::put($webRoutePath, $content);
                    $this->info('  ✓ Rute server/location berhasil disematkan ke routes/pasak.php.');
                }
            }
        }

        $apiRoutePath = base_path('routes/pasak-api.php');
        if (File::exists($apiRoutePath)) {
            $content = File::get($apiRoutePath);
            if (! str_contains($content, "'server.location'")) {
                $search = "Route::post('server/suspicious-files/unverify', [PasakServerSecurityApiController::class, 'unverifySuspiciousFile'])->name('server.suspicious-files.unverify');";
                if (str_contains($content, $search)) {
                    $insert = $search."\n        Route::get('server/location', [PasakServerSecurityApiController::class, 'getLocation'])->name('server.location.get');\n        Route::post('server/location', [PasakServerSecurityApiController::class, 'updateLocation'])->name('server.location');";
                    $content = str_replace($search, $insert, $content);
                    File::put($apiRoutePath, $content);
                    $this->info('  ✓ Rute server/location berhasil disematkan ke routes/pasak-api.php.');
                }
            }
        }
    }

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
                $pattern = "/require\\s+__DIR__\\s*\\.\\s*[\x27\"\\/]+".preg_quote(basename($legacy), '/')."[\x27\"\\s]*;/m";
                $updated = preg_replace($pattern, "require __DIR__.'/".basename($current)."';", $content);

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
        $this->migrateDashboardThreatMapInConfig();
        $this->migrateSentinelInConfig();
        $this->line(
            '  ✓ Kunci config baru dicakup bawaan paket (tidak ada kustomisasi yang ditimpa).',
        );

        // 3. Sidecar .htaccess.pasak untuk standar baru (tidak menyentuh milik user).
        $sidecar = public_path('.htaccess.pasak');

        if (! File::exists($sidecar)) {
            foreach (
                [
                    __DIR__.'/../../../stubs/htaccess.stub',
                    __DIR__.'/../../../stubs/htaccess.stub',
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
                __DIR__.'/../../../stubs/env.stub',
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
        if ((bool) $this->option('no-backup')) {
            return;
        }

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

    /**
     * Deteksi jumlah berkas migrasi yang belum dijalankan pada database.
     */
    protected function detectPendingMigrations(): int
    {
        try {
            /** @var Migrator $migrator */
            $migrator = app('migrator');
            $paths = array_unique(array_merge($migrator->paths(), [database_path('migrations')]));
            $files = $migrator->getMigrationFiles($paths);

            if (! $migrator->repositoryExists()) {
                return count($files);
            }
            $ran = $migrator->getRepository()->getRan();
            $pending = array_diff(array_keys($files), $ran);

            return count($pending);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Bersihkan berkas dan direktori usang dari versi paket terdahulu
     * yang tidak lagi digunakan agar project host tetap bersih dan mutakhir.
     */
    protected function cleanupObsoleteFiles(bool $force = false): void
    {
        if (! $force) {
            return;
        }
        $obsoleteFiles = [
            base_path('routes/security.php'),
            base_path('routes/security-api.php'),
            config_path('security.php'),
            base_path('finis.sh'),
            base_path('install-dash.sh'),
        ];

        $obsoleteDirs = [
            resource_path('views/pages/security'),
            resource_path('js/pages/security'),
            resource_path('js/components/security'),
            base_path('web-docs'),
        ];

        $deletedCount = 0;

        foreach ($obsoleteFiles as $file) {
            if (File::exists($file)) {
                // Pastikan padanan berkas baru sudah ada sebelum menghapus rute atau config lama
                if (str_ends_with($file, 'routes/security.php') && ! File::exists(base_path('routes/pasak.php'))) {
                    continue;
                }
                if (str_ends_with($file, 'routes/security-api.php') && ! File::exists(base_path('routes/pasak-api.php'))) {
                    continue;
                }
                if (str_ends_with($file, 'config/security.php') && ! File::exists(config_path('pasak.php'))) {
                    continue;
                }

                File::delete($file);
                $rel = $this->relativeHostPath($file);
                $this->info("  ✓ Berkas lama usang dibersihkan: {$rel}");
                $deletedCount++;
            }
        }

        foreach ($obsoleteDirs as $dir) {
            if (File::isDirectory($dir)) {
                if (str_ends_with($dir, 'views/pages/security') && ! File::isDirectory(resource_path('views/pages/pasak'))) {
                    continue;
                }

                File::deleteDirectory($dir);
                $rel = $this->relativeHostPath($dir);
                $this->info("  ✓ Direktori lama usang dibersihkan: {$rel}");
                $deletedCount++;
            }
        }

        // Bersihkan tampilan di views/pages/pasak yang tidak lagi digunakan versi baru paket
        $stubViewsDir = __DIR__.'/../../../stubs/blade/pages/pasak';
        $hostViewsDir = resource_path('views/pages/pasak');
        if (File::isDirectory($stubViewsDir) && File::isDirectory($hostViewsDir)) {
            $stubFiles = array_map('basename', File::files($stubViewsDir));
            foreach (File::files($hostViewsDir) as $hostFile) {
                $base = $hostFile->getFilename();
                if (! in_array($base, $stubFiles, true) && ! str_contains($base, 'backup')) {
                    File::delete($hostFile->getPathname());
                    $rel = $this->relativeHostPath($hostFile->getPathname());
                    $this->info("  ✓ Tampilan usang tidak lagi digunakan dibersihkan: {$rel}");
                    $deletedCount++;
                }
            }
        }

        // Pangkas cadangan berlebih (.backup-*) agar tidak menumpuk di disk
        $this->pruneExcessiveBackups();

        if ($deletedCount === 0) {
            $this->line('  ✓ Tidak ditemukan berkas atau direktori usang dari versi lama.');
        }
    }

    /**
     * Pangkas berkas/direktori cadangan .backup-* lama.
     * Jika --clean-backups aktif, hapus semua cadangan.
     * Jika tidak, sisakan maksimal 1 cadangan terbaru.
     */
    protected function pruneExcessiveBackups(): void
    {
        $searchDirs = [
            resource_path('views/pages'),
            resource_path('views/errors'),
            base_path('routes'),
            config_path(),
        ];

        $deleteAll = (bool) $this->option('clean-backups');

        foreach ($searchDirs as $dir) {
            if (! File::isDirectory($dir)) {
                continue;
            }

            $items = glob($dir.DIRECTORY_SEPARATOR.'*backup-*');
            if (! is_array($items) || count($items) === 0) {
                continue;
            }

            if ($deleteAll) {
                foreach ($items as $item) {
                    try {
                        if (is_dir($item)) {
                            File::deleteDirectory($item);
                        } else {
                            File::delete($item);
                        }
                        $rel = $this->relativeHostPath($item);
                        $this->line("  ℹ Cadangan lama '{$rel}' dibersihkan.");
                    } catch (Throwable) {
                        // Abaikan bila tidak dapat dihapus
                    }
                }

                continue;
            }

            // Kelompokkan per berkas target agar tiap berkas tetap memiliki 1 cadangan terbaru
            $grouped = [];
            foreach ($items as $item) {
                $base = preg_replace('/\.backup-.*$/', '', $item);
                $grouped[$base][] = $item;
            }

            foreach ($grouped as $group) {
                if (count($group) <= 1) {
                    continue;
                }
                usort($group, fn ($a, $b) => filemtime($b) <=> filemtime($a));
                $toDelete = array_slice($group, 1);
                foreach ($toDelete as $item) {
                    try {
                        if (is_dir($item)) {
                            File::deleteDirectory($item);
                        } else {
                            File::delete($item);
                        }
                        $rel = $this->relativeHostPath($item);
                        $this->line("  ℹ Cadangan lama '{$rel}' dibersihkan.");
                    } catch (Throwable) {
                        // Abaikan bila tidak dapat dihapus
                    }
                }
            }
        }
    }

    /**
     * Sisipkan konfigurasi threat_map ke config/pasak.php host jika belum ada.
     */
    protected function migrateDashboardThreatMapInConfig(): void
    {
        $configPath = config_path('pasak.php');

        if (! File::exists($configPath)) {
            return;
        }

        $content = File::get($configPath);

        if (str_contains($content, "'threat_map' =>")) {
            return;
        }

        $anchors = [
            "'grace_seconds' => (int) env('PASAK_DASHBOARD_GRACE_SECONDS', env('SECURITY_DASHBOARD_GRACE_SECONDS', 120)),",
            "'prefix' => env('PASAK_DASHBOARD_PREFIX', env('SECURITY_DASHBOARD_PREFIX', 'pasak')),",
            "'dashboard' => [",
        ];

        $matchedAnchor = null;
        foreach ($anchors as $a) {
            if (str_contains($content, $a)) {
                $matchedAnchor = $a;
                break;
            }
        }

        if (! $matchedAnchor) {
            return;
        }

        $block = <<<'PHP'

        // Peta Persebaran Serangan Global (Interactive 3D Threat Map Globe)
        'threat_map' => [
            'enabled' => (bool) env('PASAK_DASHBOARD_THREAT_MAP_ENABLED', true),
            'window_hours' => (int) env('PASAK_DASHBOARD_MAP_WINDOW_HOURS', 24),
            'max_origins' => (int) env('PASAK_DASHBOARD_MAP_MAX_ORIGINS', 10),
            'refresh_seconds' => (int) env('PASAK_DASHBOARD_MAP_REFRESH_SECONDS', 8),
        ],
PHP;

        $updated = str_replace($matchedAnchor, $matchedAnchor.$block, $content);

        if ($updated !== $content) {
            File::put($configPath, $updated);
            $this->info(
                '  ✓ Konfigurasi threat_map disisipkan ke config/pasak.php (kustomisasi Anda dipertahankan).',
            );
        }
    }

    /**
     * Sisipkan konfigurasi sentinel ke config/pasak.php host jika belum ada.
     */
    protected function migrateSentinelInConfig(): void
    {
        $configPath = config_path('pasak.php');

        if (! File::exists($configPath)) {
            return;
        }

        $content = File::get($configPath);

        if (str_contains($content, "'sentinel' =>")) {
            return;
        }

        $block = <<<'PHP'

    /*
    |--------------------------------------------------------------------------
    | Pasak Sentinel Host Daemon IPC Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi komunikasi Unix Domain Socket antara aplikasi Laravel dan
    | daemon Pasak Sentinel (Go host daemon). Memungkinkan delegasi pemblokiran
    | IP ke kernel firewall Linux (nftables/iptables) secara instan (< 0.2ms).
    |
    */
    'sentinel' => [
        'enabled' => (bool) env('PASAK_SENTINEL_ENABLED', true),
        'socket' => env('PASAK_SENTINEL_SOCKET', '/run/pasak/agent.sock'),
        'timeout' => (float) env('PASAK_SENTINEL_TIMEOUT', 0.5),
    ],
];
PHP;

        $pattern = '/\];\s*$/';
        if (preg_match($pattern, $content)) {
            $updated = preg_replace($pattern, $block, $content);
            if ($updated !== null && $updated !== $content) {
                File::put($configPath, $updated);
                $this->info(
                    '  ✓ Konfigurasi sentinel IPC disisipkan ke config/pasak.php (kustomisasi Anda dipertahankan).',
                );
            }
        }
    }
}
