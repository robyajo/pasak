<?php

namespace Internal\Pasak\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Internal\Pasak\Support\PasakDashboardSecret;

/**
 * Membuat atau membuat ulang `PASAK_DASHBOARD_SECRET` pada berkas `.env`.
 *
 * Berguna saat deploy/clone baru: `.env.example` hanya memuat baris kosong,
 * sehingga aplikasi hasil clone belum memiliki rahasia dashboard. Jalankan
 * `php artisan pasak:secret` sekali, lalu `php artisan config:clear`.
 */
class PasakSecretCommand extends Command
{
    protected $signature = 'pasak:secret
                            {--force : Buat ulang (regenerate) meski secret sudah terisi}
                            {--show : Tampilkan nilai secret aktif}
                            {--path= : Berkas env tujuan (default: .env)}';

    protected $description = 'Buat atau buat ulang PASAK_DASHBOARD_SECRET untuk URL dashboard acak';

    public function handle(): int
    {
        $path = (string) ($this->option('path') ?: base_path('.env'));

        if (! File::exists($path)) {
            $this->error('Berkas env tidak ditemukan: '.$path);
            $this->line('Buat berkas tersebut lebih dulu, atau tentukan lokasi lewat --path=.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $before = PasakDashboardSecret::readFromEnvFile($path);
        $result = PasakDashboardSecret::ensureInEnvFile($path, $force, true);

        if (! $result['generated']) {
            $this->info('PASAK_DASHBOARD_SECRET sudah terisi.');
            $this->line('Gunakan --force untuk membuat ulang (URL dashboard lama otomatis diarahkan ke URL baru saat diakses).');

            if ($this->option('show') && $before !== null) {
                $this->line('PASAK_DASHBOARD_SECRET='.$before);
            }

            return self::SUCCESS;
        }

        $verb = $before === null ? 'dibuat' : 'dibuat ulang';

        $this->info('PASAK_DASHBOARD_SECRET berhasil '.$verb.'.');
        $this->line('Berkas  : '.$path);

        if ($this->option('show')) {
            $this->line('Nilai   : '.$result['secret']);
        } else {
            $this->line('Nilai   : '.str_repeat('*', 12).' (gunakan --show untuk menampilkan)');
        }

        $this->newLine();
        $this->line('Langkah berikutnya: jalankan `php artisan config:clear` agar nilai baru dipakai.');
        $this->line('Token dashboard pada sesi lama otomatis ditandatangani ulang saat pengguna membuka /pasak.');

        return self::SUCCESS;
    }
}
