<?php

namespace Internal\Pasak\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PasakUninstallCommand extends Command
{
    protected $signature = 'pasak:uninstall
                            {--force : Jalankan tanpa konfirmasi interaktif}
                            {--keep-tables : Jangan hapus tabel database milik Pasak}
                            {--keep-env : Jangan hapus variabel PASAK_* dari .env}
                            {--keep-skills : Jangan hapus skill AI (.agents/skills/pasak) dan panduan AGENTS.md}';

    protected $description = 'Hapus seluruh jejak Pasak dari aplikasi host (konfigurasi, rute, middleware, trait, tabel, dan berkas publikasi) tanpa merusak aplikasi';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if (
            ! $force &&
            ! $this->confirm(
                'Hapus SELURUH instalasi Pasak dari aplikasi ini? (konfigurasi, rute, middleware, trait User, dan TABEL database)',
                false,
            )
        ) {
            $this->line('Pembatalan: tidak ada perubahan yang dilakukan.');

            return self::SUCCESS;
        }

        $this->info('Memulai pencopotan Pasak...');
        $this->newLine();

        $this->removeDatabaseTables((bool) $this->option('keep-tables'));
        $this->removeRouteFiles();
        $this->removeMiddlewareFromApp();
        $this->removeUserTrait();
        $this->removeEnvironmentVariables((bool) $this->option('keep-env'));
        $this->restoreHtaccess();
        $this->removePublishedFiles();
        if (! (bool) $this->option('keep-skills')) {
            $this->removeAgentSkillsAndGuidelines();
        }
        $this->removeBackupFiles();
        $this->removeComposerPackage();

        try {
            $this->callSilent('optimize:clear');
            $this->info(
                '  ✓ Cache konfigurasi, rute, dan tampilan dibersihkan.',
            );
        } catch (\Throwable) {
            // Abaikan bila tidak didukung environment tersebut.
        }

        $this->newLine();
        $this->info(
            'Pencopotan selesai. Aplikasi Anda kembali seperti sebelum Pasak dipasang.',
        );
        $this->line(
            '  Disarankan: <fg=yellow>php artisan config:clear && php artisan route:clear && composer dump-autoload</>',
        );

        return self::SUCCESS;
    }

    /**
     * Hapus HANYA tabel milik Pasak (tidak menyentuh tabel aplikasi).
     */
    protected function removeDatabaseTables(bool $keep): void
    {
        if ($keep) {
            $this->line('  - Tabel database dilewati (--keep-tables).');

            return;
        }

        $tables = [
            config('pasak.table_names.settings', 'pasak_settings'),
            config('pasak.table_names.allowed_ips', 'pasak_allowed_ips'),
            config('pasak.table_names.trusted_ips', 'pasak_trusted_ips'),
            config('pasak.table_names.user_logins', 'pasak_user_logins'),
            config(
                'pasak.table_names.ip_unblock_requests',
                'pasak_ip_unblock_requests',
            ),
            config('pasak.table_names.login_attempts', 'pasak_login_attempts'),
            config(
                'pasak.table_names.pasak_logs',
                config('pasak.table_names.security_logs', 'pasak_logs'),
            ),
            config('pasak.table_names.blocked_ips', 'pasak_blocked_ips'),
            // Varian lama (pra-prefix) agar tidak tertinggal.
            'allowed_ips',
            'trusted_ips',
            'user_logins',
            'ip_unblock_requests',
            'login_attempts',
            'blocked_ips',
            // Varian lama (pra-rename) agar tidak tertinggal.
            'security_logs',
            'security_settings',
        ];

        Schema::disableForeignKeyConstraints();
        try {
            foreach (array_unique($tables) as $table) {
                if (! is_string($table) || $table === '') {
                    continue;
                }

                try {
                    if (Schema::hasTable($table)) {
                        Schema::dropIfExists($table);
                        $this->info("  ✓ Tabel '{$table}' dihapus.");
                    }
                } catch (\Throwable $e) {
                    $this->warn(
                        "  ⚠ Gagal menghapus tabel '{$table}': ".
                            $e->getMessage(),
                    );
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Hapus record migrasi milik Pasak agar install ulang berjalan bersih
        // (jika tidak, "Nothing to migrate" padahal tabel sudah tidak ada).
        try {
            if (Schema::hasTable('migrations')) {
                $deleted = DB::table('migrations')
                    ->where('migration', 'like', '%pasak%')
                    ->orWhereIn('migration', [
                        '2026_01_01_000001_create_security_blocked_ips_table',
                        '2026_01_01_000002_create_security_logs_table',
                        '2026_01_01_000003_create_security_login_attempts_table',
                        '2026_01_01_000004_create_security_ip_unblock_requests_table',
                        '2026_01_01_000005_create_security_user_logins_and_trusted_ips_table',
                        '2026_01_01_000006_create_security_settings_table',
                    ])
                    ->delete();

                if ($deleted > 0) {
                    $this->info(
                        "  ✓ {$deleted} record migrasi Pasak dibersihkan.",
                    );
                }
            }
        } catch (\Throwable $e) {
            $this->warn(
                '  ⚠ Gagal membersihkan record migrasi: '.$e->getMessage(),
            );
        }
    }

    /**
     * Hapus berkas rute publikasi + baris require di web.php / api.php.
     */
    protected function removeRouteFiles(): void
    {
        foreach (['routes/pasak.php', 'routes/pasak-api.php'] as $relative) {
            $path = base_path($relative);
            if (File::exists($path)) {
                File::delete($path);
                $this->info("  ✓ Berkas {$relative} dihapus.");
            }
        }

        $requireLines = [
            "require __DIR__.'/pasak.php';",
            "require __DIR__.'/pasak-api.php';",
            // Varian lama yang pernah ditulis installer (sudah diperbaiki).
            "require __DIR__.'/security.php';",
            "require __DIR__.'/security-api.php';",
        ];

        foreach (['routes/web.php', 'routes/api.php'] as $relative) {
            $path = base_path($relative);
            if (! File::exists($path)) {
                continue;
            }

            $content = File::get($path);

            // Bersihkan via regex untuk menangani variasi spasi (misal Pint/CS-Fixer: require __DIR__ . '/pasak.php';)
            $cleaned = (string) preg_replace(
                '/^[ \t]*(?:require|require_once|include|include_once)\s+__DIR__\s*\.\s*[\'"]\/(?:pasak|pasak-api|security|security-api)\.php[\'"]\s*;[ \t]*\r?\n?/m',
                '',
                $content,
            );

            $cleaned = str_replace(
                array_map(fn ($line) => "\n".$line, $requireLines),
                '',
                $cleaned,
            );
            $cleaned = str_replace($requireLines, '', $cleaned);

            if ($cleaned !== $content) {
                File::put($path, $this->normalizePhpFile($cleaned));
                $this->info("  ✓ Referensi Pasak di {$relative} dibersihkan.");
            }
        }
    }

    /**
     * Hapus baris middleware Pasak dari bootstrap/app.php / Kernel.php.
     */
    protected function removeMiddlewareFromApp(): void
    {
        $targets = [
            base_path('bootstrap/app.php'),
            app_path('Http/Kernel.php'),
        ];

        $markers = [
            'Internal\\Pasak\\Http\\Middleware\\PasakBlockIpAddress::class',
            'Internal\\Pasak\\Http\\Middleware\\PasakDetectSecurityThreats::class',
            'Internal\\Pasak\\Http\\Middleware\\PasakInjectAnnouncementBanner::class',
            'Internal\\Pasak\\Http\\Middleware\\BlockIpAddress::class',
            'Internal\\Pasak\\Http\\Middleware\\DetectSecurityThreats::class',
            'Internal\\Pasak\\Http\\Middleware\\InjectAnnouncementBanner::class',
            // Varian lama (pra-rename namespace).
            'Internal\\SecurityMonitor\\Http\\Middleware\\BlockIpAddress::class',
            'Internal\\SecurityMonitor\\Http\\Middleware\\DetectSecurityThreats::class',
        ];
        // Gaya import "use Internal\Pasak\...;" (nama pendek tanpa namespace).
        $importMarkers = [
            'use Internal\\Pasak\\Http\\Middleware\\',
            'use Internal\\SecurityMonitor\\Http\\Middleware\\',
        ];
        // Pemakaian nama pendek ("PasakBlockIpAddress::class," / "BlockIpAddress::class,").
        $shortClassMarkers = [
            'PasakBlockIpAddress::class',
            'PasakDetectSecurityThreats::class',
            'PasakInjectAnnouncementBanner::class',
            'BlockIpAddress::class',
            'DetectSecurityThreats::class',
            'InjectAnnouncementBanner::class',
        ];

        foreach ($targets as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $lines = explode("\n", File::get($path));
            $filtered = array_values(
                array_filter(
                    $lines,
                    fn ($line) => ! $this->lineReferences($line, $markers) &&
                        ! $this->shortClassReferenced($line, $shortClassMarkers),
                ),
            );

            foreach ($importMarkers as $marker) {
                $filtered = array_values(
                    array_filter(
                        $filtered,
                        fn ($line) => ! str_contains($line, $marker),
                    ),
                );
            }

            // Bersihkan blok append yang kini kosong: "$middleware->append([\n]);"
            // (termasuk receiver $middleware agar tidak tersisa statement gantung).
            $content = (string) preg_replace(
                '/(?:\$middleware\s*)?->append\(\[\s*\]\);\n?/',
                '',
                implode("\n", $filtered),
            );

            // Baris sisa berupa statement variabel gantung ("$middleware" / "$middleware;") -> buang.
            $content = (string) preg_replace(
                '/^[ \t]*\$middleware[ \t]*;?[ \t]*\r?$/m',
                '',
                $content,
            );

            if ($content !== null && $content !== File::get($path)) {
                File::put($path, $this->normalizePhpFile($content));
                $this->info(
                    '  ✓ Middleware Pasak dihapus dari '.
                        basename((string) $path).
                        '.',
                );
            }
        }
    }

    protected function lineReferences(string $line, array $markers): bool
    {
        foreach ($markers as $marker) {
            if (str_contains($line, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek pemakaian nama kelas pendek pada baris dengan batas kata,
     * agar baris seperti "SomeBlockIpAddress::class," tidak ikut tersapu.
     */
    protected function shortClassReferenced(string $line, array $markers): bool
    {
        foreach ($markers as $marker) {
            if (
                preg_match(
                    '/(^|[^A-Za-z0-9_\\\\])'.preg_quote($marker, '/').'/',
                    $line,
                ) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalisasi akhir berkas PHP host: maksimal satu baris kosong
     * antar blok dan tepat satu newline di EOF (ramah Pint).
     */
    protected function normalizePhpFile(string $content): string
    {
        $content = (string) preg_replace("/\n{3,}/", "\n\n", $content);

        return rtrim($content)."\n";
    }

    /**
     * Lepas trait HasSecurityRelations dari model User host.
     */
    protected function removeUserTrait(): void
    {
        $candidates = [app_path('Models/User.php'), base_path('app/User.php')];

        $traits = ['HasPasakSecurityRelations', 'HasSecurityRelations'];
        $imports = [
            'use Internal\\Pasak\\Concerns\\HasPasakSecurityRelations;',
            'use Internal\\Pasak\\Concerns\\HasSecurityRelations;',
            'use Internal\\SecurityMonitor\\Concerns\\HasSecurityRelations;',
        ];

        foreach ($candidates as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $content = File::get($path);
            $original = $content;

            $content = str_replace($imports, '', $content);

            foreach ($traits as $trait) {
                // "use HasPasakSecurityRelations;" mandiri -> hapus barisnya.
                $content = (string) preg_replace(
                    '/^[ \t]*use\s+'.
                        preg_quote($trait, '/').
                        '\s*;[ \t]*\r?\n?/m',
                    '',
                    $content,
                );
                // Daftar gabungan "use A, HasPasakSecurityRelations, B;" -> keluarkan namanya.
                $content = (string) preg_replace(
                    '/,\\s*'.preg_quote($trait, '/').'\\b/',
                    '',
                    $content,
                );
                $content = (string) preg_replace(
                    '/\\b'.preg_quote($trait, '/').'\\s*,\\s*/',
                    '',
                    $content,
                );
            }

            if ($content !== $original) {
                File::put($path, $this->normalizePhpFile($content));
                $this->info('  ✓ Trait Pasak dilepas dari model User.');
            }
        }
    }

    /**
     * Hapus blok variabel PASAK_* (sesuai stubs/env.stub) dari .env & .env.example.
     */
    protected function removeEnvironmentVariables(bool $keep): void
    {
        if ($keep) {
            $this->line('  - Variabel .env dilewati (--keep-env).');

            return;
        }

        $stubLines = [];
        foreach (
            [
                __DIR__.'/../../../stubs/env.stub',
                dirname(__DIR__, 3).'/stubs/env.stub',
            ] as $candidate
        ) {
            if (File::exists($candidate)) {
                $stubLines = explode("\n", File::get($candidate));
                break;
            }
        }

        $stubComments = [];
        foreach ($stubLines as $stubLine) {
            if (str_starts_with(trim($stubLine), '#')) {
                $stubComments[trim($stubLine)] = true;
            }
        }

        foreach (['.env', '.env.example'] as $envFile) {
            $path = base_path($envFile);
            if (! File::exists($path)) {
                continue;
            }

            $kept = [];
            foreach (explode("\n", File::get($path)) as $line) {
                $trimmed = trim($line);

                if (preg_match('/^PASAK_[A-Z0-9_]+=/', $trimmed)) {
                    continue;
                }

                if (
                    $trimmed !== '' &&
                    str_starts_with($trimmed, '#') &&
                    isset($stubComments[$trimmed])
                ) {
                    continue;
                }

                $kept[] = $line;
            }

            $cleaned = preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept));
            File::put($path, rtrim($cleaned ?? implode("\n", $kept))."\n");
            $this->info("  ✓ Variabel PASAK_* dihapus dari {$envFile}.");
        }
    }

    /**
     * Kembalikan public/.htaccess dari cadangan; jangan pernah merusak aplikasi.
     */
    protected function restoreHtaccess(): void
    {
        $htaccessPath = public_path('.htaccess');
        $pasakHtaccessPath = public_path('.htaccess.pasak');

        if (File::exists($pasakHtaccessPath)) {
            File::delete($pasakHtaccessPath);
            $this->info('  ✓ Berkas public/.htaccess.pasak dihapus.');
        }

        $backups = glob(public_path('.htaccess.backup-*')) ?: [];
        if ($backups !== []) {
            rsort($backups);
            File::copy($backups[0], $htaccessPath);
            $this->info(
                '  ✓ public/.htaccess dikembalikan dari cadangan ('.
                    basename((string) $backups[0]).
                    ').',
            );

            return;
        }

        if (! File::exists($htaccessPath)) {
            return;
        }

        // Tanpa cadangan: hapus hanya bila isinya persis stub kita.
        $stubCandidates = [
            __DIR__.'/../../../stubs/htaccess.stub',
            dirname(__DIR__, 2).'/stubs/htaccess.stub',
        ];

        foreach ($stubCandidates as $stubPath) {
            if (
                File::exists($stubPath) &&
                File::get($htaccessPath) === File::get($stubPath)
            ) {
                File::delete($htaccessPath);
                $this->info('  ✓ public/.htaccess (murni dari Pasak) dihapus.');

                return;
            }
        }

        $this->line(
            '  - public/.htaccess dibiarkan (kustom; tanpa cadangan untuk dipulihkan).',
        );
    }

    /**
     * Hapus berkas publikasi; nginx/apache hanya bila isinya persis stub kita.
     */
    protected function removePublishedFiles(): void
    {
        $deletions = [
            config_path('pasak.php'),
            config_path('security.php'),
            resource_path('views/errors/blocked.blade.php'),
        ];

        foreach ($deletions as $path) {
            if (File::exists($path)) {
                File::delete($path);
                $this->info('  ✓ Dihapus: '.$this->relativePath($path));
            }
        }

        foreach (
            glob(database_path('migrations/*_pasak_*.php')) ?: [] as $migration
        ) {
            File::delete($migration);
            $this->info(
                '  ✓ Dihapus: '.$this->relativePath((string) $migration),
            );
        }
        foreach (
            glob(database_path('migrations/*_security_*.php')) ?: [] as $migration
        ) {
            if (
                str_contains(
                    File::get((string) $migration),
                    'Internal\\Pasak',
                ) ||
                str_contains(
                    File::get((string) $migration),
                    'pasak.table_names',
                )
            ) {
                File::delete($migration);
                $this->info(
                    '  ✓ Dihapus: '.$this->relativePath((string) $migration),
                );
            }
        }

        foreach (
            ['views/pages/pasak', 'js/pages/pasak', 'js/components/pasak'] as $relative
        ) {
            $path = resource_path($relative);
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
                $this->info("  ✓ Direktori resources/{$relative} dihapus.");
            }
        }

        foreach (
            [
                'nginx.conf.stub' => 'nginx.conf',
                'apache2.conf.stub' => 'apache2.conf',
            ] as $stub => $target
        ) {
            $targetPath = base_path($target);
            if (! File::exists($targetPath)) {
                continue;
            }

            foreach (
                [
                    __DIR__.'/../../../stubs/'.$stub,
                    dirname(__DIR__, 2).'/stubs/'.$stub,
                ] as $stubPath
            ) {
                if (
                    File::exists($stubPath) &&
                    File::get($targetPath) === File::get($stubPath)
                ) {
                    File::delete($targetPath);
                    $this->info(
                        "  ✓ Berkas {$target} (murni dari Pasak) dihapus.",
                    );
                    break;
                }
            }
        }

        // Hapus direktori induk yang kini kosong agar tidak ada folder yatim.
        foreach (
            [
                resource_path('views/errors'),
                resource_path('views/pages'),
                resource_path('js/pages'),
                resource_path('js/components'),
            ] as $dir
        ) {
            $this->removeDirectoryIfEmpty($dir);
        }
    }

    /**
     * Hapus skill AI (.agents/skills/pasak/SKILL.md) dan bersihkan blok panduan di AGENTS.md.
     */
    protected function removeAgentSkillsAndGuidelines(): void
    {
        $skillFile = base_path('.agents/skills/pasak/SKILL.md');
        if (File::exists($skillFile)) {
            File::delete($skillFile);
            $this->info('  ✓ Dihapus: '.$this->relativePath($skillFile));
            $this->removeDirectoryIfEmpty(base_path('.agents/skills/pasak'));
            $this->removeDirectoryIfEmpty(base_path('.agents/skills'));
            $this->removeDirectoryIfEmpty(base_path('.agents'));
        }

        foreach (['AGENTS.md', 'CLAUDE.md'] as $agentFileName) {
            $agentFile = base_path($agentFileName);
            if (! File::exists($agentFile) || is_link($agentFile)) {
                continue;
            }

            $content = File::get($agentFile);
            $pattern = '/\s*<!-- pasak-guidelines:start -->.*?<!-- pasak-guidelines:end -->\s*/s';

            if (preg_match($pattern, $content)) {
                $cleaned = trim(preg_replace($pattern, '', $content));
                if ($cleaned === '') {
                    File::delete($agentFile);
                    $this->info("  ✓ Berkas {$agentFileName} yang dibuat oleh Pasak dihapus.");
                } else {
                    File::put($agentFile, $cleaned.PHP_EOL);
                    $this->info("  ✓ Panduan Pasak dibersihkan dari {$agentFileName}.");
                }
            }
        }
    }

    /**
     * Hapus direktori bila kosong (termasuk berkas tersembunyi seperti .gitkeep
     * ikut dihitung; direktori berisi apa pun dibiarkan).
     */
    protected function removeDirectoryIfEmpty(string $dir): void
    {
        if (! File::isDirectory($dir)) {
            return;
        }

        $entries = array_diff(scandir($dir) ?: [], ['.', '..']);

        if ($entries === []) {
            try {
                File::deleteDirectory($dir);
                $this->info(
                    '  ✓ Direktori kosong dihapus: '.
                        $this->relativePath($dir),
                );
            } catch (\Throwable $e) {
                $this->warn(
                    '  ⚠ Gagal menghapus direktori '.
                        $dir.
                        ': '.
                        $e->getMessage(),
                );
            }
        }
    }

    /**
     * Hapus berkas cadangan (*.backup-*) yang dibuat oleh perintah Pasak
     * (installer & pasak:upgrade --sync-*) agar tidak ada jejak tertinggal.
     * Hanya pola milik Pasak yang disentuh; cadangan pengguna tidak diganggu.
     */
    protected function removeBackupFiles(): void
    {
        $patterns = [
            base_path('routes/pasak.php.backup-*'),
            base_path('routes/pasak-api.php.backup-*'),
            resource_path('views/pages/pasak.backup-*'),
            public_path('.htaccess.backup-*'),
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $path) {
                try {
                    if (File::isDirectory($path)) {
                        File::deleteDirectory($path);
                    } else {
                        File::delete($path);
                    }
                    $this->info(
                        '  ✓ Cadangan dihapus: '.
                            $this->relativePath((string) $path),
                    );
                } catch (\Throwable $e) {
                    $this->warn(
                        '  ⚠ Gagal menghapus cadangan '.
                            $path.
                            ': '.
                            $e->getMessage(),
                    );
                }
            }
        }
    }

    protected function relativePath(string $path): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base)
            ? substr($path, strlen($base))
            : $path;
    }

    /**
     * Lepas paket via Composer (proses terpisah; command ini ikut terhapus bersamanya).
     */
    protected function removeComposerPackage(): void
    {
        $composer = $this->findComposerBinary();

        if ($composer === null) {
            $this->line(
                '  - Composer tidak ditemukan di PATH; jalankan manual: <fg=yellow>composer remove robyajo/pasak</>',
            );

            return;
        }

        $this->comment(
            '  Melepas paket via Composer (perintah ini ikut terhapus bersamanya)...',
        );
        $code = 0;
        $cmd =
            escapeshellcmd($composer).
            ' remove robyajo/pasak --no-interaction --no-audit 2>&1';
        exec($cmd, $output, $code);

        if ($code === 0) {
            $this->info('  ✓ Paket robyajo/pasak dilepas dari composer.json.');
        } else {
            $this->warn(
                '  ⚠ composer remove gagal; jalankan manual: composer remove robyajo/pasak',
            );
        }
    }

    protected function findComposerBinary(): ?string
    {
        if (File::exists(base_path('composer.phar'))) {
            return PHP_BINARY.' '.base_path('composer.phar');
        }

        $composer = trim((string) shell_exec('command -v composer'));

        return $composer !== '' ? $composer : null;
    }
}
