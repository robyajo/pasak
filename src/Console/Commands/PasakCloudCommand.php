<?php

namespace Internal\Pasak\Console\Commands;

use Illuminate\Console\Command;
use Internal\Pasak\Services\PasakCloudService;
use Internal\Pasak\Services\PasakSentinelBridge;

class PasakCloudCommand extends Command
{
    protected $signature = 'pasak:cloud
                            {--sync : Kirim heartbeat dan telemetri aplikasi seketika ke Cloud API}
                            {--blocklist : Ambil daftar IP penyerang global dari Cloud Control Plane}';

    protected $description = 'Periksa status koneksi Pasak Cloud SOC API dan integrasi Pasak Sentinel';

    public function handle(
        PasakCloudService $cloud,
        PasakSentinelBridge $sentinel,
    ): int {
        $this->info(
            '======================================================================',
        );
        $this->info(
            '  🛡️  Pasak Cloud SOC & Sentinel Fleet Integration Monitor',
        );
        $this->info(
            '======================================================================',
        );
        $this->newLine();

        $meta = $cloud->getAppMetadata();
        $this->line(
            "• Nama Aplikasi (APP_NAME) : <comment>{$meta['name']}</comment>",
        );
        $this->line(
            "• URL Aplikasi (APP_URL)   : <comment>{$meta['url']}</comment>",
        );
        $this->line(
            "• Domain Terpantau         : <comment>{$meta['host']}</comment>",
        );

        // Jika PASAK_CLOUD_KEY tidak diisi, mode cloud nonaktif (WAF lokal murni)
        if (! $cloud->isConfigured()) {
            $this->line(
                '• Status Cloud SOC         : <fg=yellow>STANDALONE (WAF Lokal Murni — Cloud Nonaktif)</>',
            );
            $this->newLine();

            $this->comment('ℹ Informasi:');
            $this->line(
                '  Fungsi Cloud SOC bersifat <comment>opsional</comment>. Saat ini <comment>PASAK_CLOUD_KEY</comment> kosong/belum diisi.',
            );
            $this->line(
                '  Aplikasi Anda tetap terlindungi 100% oleh WAF lokal mandiri tanpa perlu koneksi ke internet.',
            );
            $this->newLine();
            $this->line(
                '  Jika Anda memiliki lisensi Pasak Cloud SOC untuk memantau armada server terpusat:',
            );
            $this->line(
                '  Cukup tambahkan kunci lisensi Anda ke berkas <info>.env</info>:',
            );
            $this->line(
                '    <info>PASAK_CLOUD_KEY=psk_live_xxxxxxxxxxxxxxxx</info>',
            );
            $this->newLine();

            // Sentinel local socket check tetap diuji karena berjalan di host lokal
            $this->comment(
                '1. Memeriksa Koneksi Daemon Pasak Sentinel Host (Kernel Firewall)...',
            );
            if ($sentinel->isAvailable()) {
                $status = $sentinel->status();
                $driver = $status['firewall_driver'] ?? 'unknown';
                $blockedCount = $status['blocked_count'] ?? 0;
                $this->info(
                    "  ✓ Sentinel Daemon AKTIF pada socket IPC [Driver: {$driver}, Kernel Drops: {$blockedCount}]",
                );
            } else {
                $this->warn(
                    '  ⚠ Sentinel Daemon belum aktif pada socket lokal (pemblokiran memakai layer aplikasi database).',
                );
            }

            return Command::SUCCESS;
        }

        // Jika PASAK_CLOUD_KEY terisi valid:
        $this->line(
            '• Status Cloud SOC         : <fg=green>TERHUBUNG (Kunci Lisensi Terkonfigurasi)</>',
        );
        $this->line(
            "• Lisensi Cloud (Key)      : <comment>{$cloud->getMaskedKey()}</comment>",
        );
        $this->line(
            '• Endpoint Cloud API       : <comment>'.
                config('pasak.cloud.url', 'https://pasak-api.vercel.app').
                '</comment>',
        );
        $this->newLine();

        // 1. Cek Sentinel Local Daemon
        $this->comment(
            '1. Memeriksa Koneksi Daemon Pasak Sentinel Host (Kernel Firewall)...',
        );
        if ($sentinel->isAvailable()) {
            $status = $sentinel->status();
            $driver = $status['firewall_driver'] ?? 'unknown';
            $blockedCount = $status['blocked_count'] ?? 0;
            $this->info(
                "  ✓ Sentinel Daemon AKTIF pada socket IPC [Driver: {$driver}, Kernel Drops: {$blockedCount}]",
            );
        } else {
            $this->warn(
                '  ⚠ Sentinel Daemon belum aktif pada socket lokal (pemblokiran beralih ke layer aplikasi database).',
            );
        }
        $this->newLine();

        // 2. Cek Koneksi Pasak Cloud Control Plane
        $this->comment(
            '2. Memeriksa Kesehatan Pasak Cloud API (Vercel + Neon DB)...',
        );
        $health = $cloud->checkHealth();
        if ($health['reachable'] && ($health['status'] ?? '') === 'ok') {
            $latency = $health['latency_ms'] ?? 0;
            $this->info(
                "  ✓ Terhubung ke Pasak Cloud Control Plane (Latency DB: {$latency}ms)",
            );
        } else {
            $this->warn(
                '  ⚠ Cloud Control Plane belum merespons: '.
                    ($health['error'] ??
                        ($health['message'] ?? 'HTTP Timeout')),
            );
        }
        $this->newLine();

        // 3. Opsi --sync atau default saat key ada: Kirim Heartbeat
        $this->comment(
            '3. Mentransmisikan Telemetri Aplikasi & Node ke Cloud Dashboard...',
        );
        $syncRes = $cloud->sendHeartbeat();
        if ($syncRes['success']) {
            $this->info(
                "  ✓ Telemetri berhasil disinkronkan ke Cloud SOC! Node ID: {$syncRes['node_id']}",
            );
            $this->line(
                "    Situs '{$meta['name']}' ({$meta['url']}) kini aktif di Pasak Fleet Console.",
            );
        } else {
            $this->warn(
                '  ⚠ Sinkronisasi telemetri dilewati / gagal: '.
                    ($syncRes['message'] ?? ($syncRes['error'] ?? 'Unknown')),
            );
        }
        $this->newLine();

        // 4. Opsi --blocklist: Global Threat Intelligence
        if ($this->option('blocklist')) {
            $this->comment('4. Mengambil Global Threat Blocklist...');
            $ips = $cloud->fetchGlobalBlocklist();
            $count = count($ips);
            $this->info(
                "  ✓ Diperoleh {$count} IP penyerang global dari jaringan pertahanan Pasak.",
            );
            if ($count > 0) {
                $this->line(
                    '    Sample IP: '.implode(', ', array_slice($ips, 0, 5)),
                );
            }
            $this->newLine();
        }

        $this->info('Pemeriksaan integrasi selesai.');

        return Command::SUCCESS;
    }
}
