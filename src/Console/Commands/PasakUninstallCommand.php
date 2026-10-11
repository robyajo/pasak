<?php

namespace Internal\Pasak\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PasakUninstallCommand extends Command
{
    protected $signature = 'pasak:uninstall
                            {--force : Jalankan tanpa konfirmasi interaktif}
                            {--keep-tables : Jangan hapus tabel database milik Pasak}
                            {--keep-env : Jangan hapus variabel PASAK_* dari .env}';

    protected $description = 'Hapus seluruh jejak Pasak dari aplikasi host (konfigurasi, rute, middleware, trait, tabel, dan berkas publikasi) tanpa merusak aplikasi';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if (! $force && ! $this->confirm(
            'Hapus SELURUH instalasi Pasak dari aplikasi ini? (konfigurasi, rute, middleware, trait User, dan TABEL database)',
            false
        )) {
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
        $this->removeComposerPackage();

        try {
            $this->callSilent('optimize:clear');
            $this->info('  ✓ Cache konfigurasi, rute, dan tampilan dibersihkan.');
        } catch (\Throwable) {
            // Abaikan bila tidak didukung environment tersebut.
        }

        $this->newLine();
        $this->info('Pencopotan selesai. Aplikasi Anda kembali seperti sebelum Pasak dipasang.');
        $this->line('  Disarankan: <fg=yellow>php artisan config:clear && php artisan route:clear && composer dump-autoload</>');

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
            config('pasak.table_names.trusted_ips', 'trusted_ips'),
            config('pasak.table_names.user_logins', 'user_logins'),
            config('pasak.table_names.ip_unblock_requests', 'ip_unblock_requests'),
            config('pasak.table_names.login_attempts', 'login_attempts'),
            config(
                'pasak.table_names.pasak_logs',
                config('pasak.table_names.security_logs', 'pasak_logs')
            ),
            config('pasak.table_names.blocked_ips', 'blocked_ips'),
            // Varian lama (pra-rename) agar tidak tertinggal.
            'security_logs',
            'security_settings',
        ];

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
                $this->warn("  ⚠ Gagal menghapus tabel '{$table}': ".$e->getMessage());
            }
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
            $cleaned = str_replace(
                array_map(fn ($line) => "\n".$line, $requireLines),
                '',
                $content
            );
            $cleaned = str_replace($requireLines, '', $cleaned);

            if ($cleaned !== $content) {
                File::put($path, $cleaned);
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
            'Internal\\Pasak\\Http\\Middleware\\BlockIpAddress::class',
            'Internal\\Pasak\\Http\\Middleware\\DetectSecurityThreats::class',
            // Varian lama (pra-rename namespace).
            'Internal\\SecurityMonitor\\Http\\Middleware\\BlockIpAddress::class',
            'Internal\\SecurityMonitor\\Http\\Middleware\\DetectSecurityThreats::class',
        ];

        foreach ($targets as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $lines = explode("\n", File::get($path));
            $filtered = array_values(array_filter(
                $lines,
                fn ($line) => ! $this->lineReferences($line, $markers)
            ));

            // Bersihkan blok append yang kini kosong: "->append([\n]);"
            $content = preg_replace(
                '/->[a-zA-Z_]+->append\\(\\[\\s*\\]\\);\\n?/',
                '',
                implode("\n", $filtered)
            );

            if ($content !== null && $content !== File::get($path)) {
                File::put($path, $content);
                $this->info('  ✓ Middleware Pasak dihapus dari '.basename((string) $path).'.');
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
     * Lepas trait HasSecurityRelations dari model User host.
     */
    protected function removeUserTrait(): void
    {
        $candidates = [
            app_path('Models/User.php'),
            base_path('app/User.php'),
        ];

        $trait = 'HasSecurityRelations';
        $imports = [
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

            // "use HasSecurityRelations;" mandiri -> hapus barisnya.
            $content = (string) preg_replace(
                '/^[ \t]*use\s+'.preg_quote($trait, '/').'\s*;[ \t]*\r?\n?/m',
                '',
                $content
            );
            // Daftar gabungan "use A, HasSecurityRelations, B;" -> keluarkan namanya.
            $content = (string) preg_replace(
                '/,\s*'.preg_quote($trait, '/').'\b/',
                '',
                $content
            );
            $content = (string) preg_replace(
                '/\b'.preg_quote($trait, '/').'\s*,\s*/',
                '',
                $content
            );

            if ($content !== $original) {
                File::put($path, $content);
                $this->info('  ✓ Trait HasSecurityRelations dilepas dari model User.');
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
        foreach (['__DIR__/../../../stubs/env.stub', dirname(__DIR__, 2).'/stubs/env.stub'] as $candidate) {
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

                if ($trimmed !== '' && str_starts_with($trimmed, '#') && isset($stubComments[$trimmed])) {
                    continue;
                }

                $kept[] = $line;
            }

            $cleaned = preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept));
            File::put($path, $cleaned ?? implode("\n", $kept));
            $this->info("  ✓ Variabel PASAK_* dihapus dari {$envFile}.");
        }
    }

    /**
     * Kembalikan public/.htaccess dari cadangan; jangan pernah merusak aplikasi.
     */
    protected function restoreHtaccess(): void
    {
        $htaccessPath = public_path('.htaccess');

        $backups = glob(public_path('.htaccess.backup-*')) ?: [];
        if ($backups !== []) {
            rsort($backups);
            File::copy($backups[0], $htaccessPath);
            $this->info('  ✓ public/.htaccess dikembalikan dari cadangan ('.basename((string) $backups[0]).').');

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
            if (File::exists($stubPath) && File::get($htaccessPath) === File::get($stubPath)) {
                File::delete($htaccessPath);
                $this->info('  ✓ public/.htaccess (murni dari Pasak) dihapus.');

                return;
            }
        }

        $this->line('  - public/.htaccess dibiarkan (kustom; tanpa cadangan untuk dipulihkan).');
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

        foreach (glob(database_path('migrations/*_pasak_*.php')) ?: [] as $migration) {
            File::delete($migration);
            $this->info('  ✓ Dihapus: '.$this->relativePath((string) $migration));
        }
        foreach (glob(database_path('migrations/*_security_*.php')) ?: [] as $migration) {
            if (str_contains(File::get((string) $migration), 'Internal\\Pasak')
                || str_contains(File::get((string) $migration), 'pasak.table_names')) {
                File::delete($migration);
                $this->info('  ✓ Dihapus: '.$this->relativePath((string) $migration));
            }
        }

        foreach (['views/pages/pasak', 'js/pages/pasak', 'js/components/pasak'] as $relative) {
            $path = resource_path($relative);
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
                $this->info("  ✓ Direktori resources/{$relative} dihapus.");
            }
        }

        foreach (['nginx.conf.stub' => 'nginx.conf', 'apache2.conf.stub' => 'apache2.conf'] as $stub => $target) {
            $targetPath = base_path($target);
            if (! File::exists($targetPath)) {
                continue;
            }

            foreach ([
                __DIR__.'/../../../stubs/'.$stub,
                dirname(__DIR__, 2).'/stubs/'.$stub,
            ] as $stubPath) {
                if (File::exists($stubPath) && File::get($targetPath) === File::get($stubPath)) {
                    File::delete($targetPath);
                    $this->info("  ✓ Berkas {$target} (murni dari Pasak) dihapus.");
                    break;
                }
            }
        }
    }

    protected function relativePath(string $path): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    /**
     * Lepas paket via Composer (proses terpisah; command ini ikut terhapus bersamanya).
     */
    protected function removeComposerPackage(): void
    {
        $composer = trim((string) shell_exec('command -v composer'));

        if ($composer === '') {
            $this->line('  - Composer tidak ditemukan di PATH; jalankan manual: <fg=yellow>composer remove robyajo/pasak</>');

            return;
        }

        $this->comment('  Melepas paket via Composer (perintah ini ikut terhapus bersamanya)...');
        $code = 0;
        exec('composer remove robyajo/pasak --no-interaction --no-audit 2>&1', $output, $code);

        if ($code === 0) {
            $this->info('  ✓ Paket robyajo/pasak dilepas dari composer.json.');
        } else {
            $this->warn('  ⚠ composer remove gagal; jalankan manual: composer remove robyajo/pasak');
        }
    }
}
