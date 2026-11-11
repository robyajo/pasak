<?php

namespace Internal\Pasak\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Layanan integrasi Pasak Cloud API dan Pasak Sentinel Fleet Monitoring.
 *
 * Menghubungkan instalasi WAF lokal (PHP/Laravel) ke Pasak Cloud Control Plane (Hono + Neon DB)
 * dan Pasak Sentinel host daemon (Go + nftables) melalui PASAK_CLOUD_KEY.
 *
 * Menggunakan metadata aplikasi:
 * - APP_NAME : Nama aplikasi / layanan
 * - APP_URL  : URL dan domain yang dipantau di armada SOC
 */
class PasakCloudService
{
    protected ?string $cloudKey;

    protected string $cloudUrl;

    protected float $timeout;

    protected bool $enabled;

    protected ?PasakSentinelBridge $sentinel;

    public function __construct(
        ?string $cloudKey = null,
        ?string $cloudUrl = null,
        ?float $timeout = null,
        ?bool $enabled = null,
        ?PasakSentinelBridge $sentinel = null
    ) {
        $this->cloudKey = $cloudKey ?? config('pasak.cloud.key');
        $this->cloudUrl = rtrim($cloudUrl ?? config('pasak.cloud.url', 'https://pasak-api.vercel.app'), '/');
        $this->timeout = $timeout ?? (float) config('pasak.cloud.timeout', 3.0);
        $this->enabled = $enabled ?? (bool) config('pasak.cloud.enabled', true);
        $this->sentinel = $sentinel ?? (app()->bound(PasakSentinelBridge::class) ? app(PasakSentinelBridge::class) : null);
    }

    /**
     * Periksa apakah integrasi Pasak Cloud aktif dan kunci lisensi telah dikonfigurasi.
     */
    public function isConfigured(): bool
    {
        $key = trim((string) ($this->cloudKey ?? ''));

        return $this->enabled && $key !== '' && str_starts_with($key, 'psk_') && strlen($key) >= 16;
    }

    /**
     * Ambil metadata aplikasi host (APP_NAME & APP_URL).
     */
    public function getAppMetadata(): array
    {
        $appName = config('app.name', 'Laravel');
        $appUrl = config('app.url', 'http://localhost:8000');
        $parsedUrl = parse_url($appUrl);
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? null;
        $isHttps = ($parsedUrl['scheme'] ?? 'http') === 'https';

        return [
            'name' => $appName,
            'url' => $appUrl,
            'host' => $host,
            'port' => $port,
            'is_https' => $isHttps,
            'environment' => config('app.env', 'production'),
        ];
    }

    /**
     * Identifikasi ID Node untuk instalasi ini.
     */
    public function resolveNodeId(): string
    {
        $configured = config('pasak.cloud.node_id');
        if (! empty($configured)) {
            return (string) $configured;
        }

        // Jika Sentinel daemon aktif, gunakan node_id dari Sentinel status
        if ($this->sentinel !== null && $this->sentinel->isAvailable()) {
            $status = $this->sentinel->status();
            if (! empty($status['node_id'])) {
                return (string) $status['node_id'];
            }
        }

        // Fallback deterministik berdasarkan hostname & app.url
        $appUrl = config('app.url', 'http://localhost:8000');
        $hash = substr(hash('sha256', gethostname().'|'.$appUrl), 0, 10);

        return "nod_app_{$hash}";
    }

    /**
     * Periksa status kesehatan Pasak Cloud API.
     */
    public function checkHealth(): array
    {
        if (! $this->isConfigured()) {
            return [
                'reachable' => false,
                'status' => 'inactive',
                'message' => 'Pasak Cloud tidak aktif (PASAK_CLOUD_KEY belum dikonfigurasi).',
            ];
        }

        try {
            $response = Http::timeout($this->timeout)->get("{$this->cloudUrl}/api/v1/health");

            if ($response->successful()) {
                return [
                    'reachable' => true,
                    'status' => $response->json('status', 'ok'),
                    'database' => $response->json('database', []),
                    'latency_ms' => $response->json('database.latency_ms', 0),
                ];
            }

            return [
                'reachable' => false,
                'status' => 'http_error',
                'http_status' => $response->status(),
            ];
        } catch (Throwable $e) {
            return [
                'reachable' => false,
                'status' => 'connection_failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Kirim detak jantung (heartbeat) dan registrasi virtual host aplikasi ke Pasak Cloud API.
     * Menggunakan data APP_NAME dan APP_URL agar situs muncul di Pasak SOC Dashboard.
     */
    public function sendHeartbeat(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'PASAK_CLOUD_KEY belum dikonfigurasi di .env',
            ];
        }

        $meta = $this->getAppMetadata();
        $nodeId = $this->resolveNodeId();
        $now = time();

        $payload = [
            'node_id' => $nodeId,
            'hostname' => gethostname(),
            'public_ip' => request()->server('SERVER_ADDR', '127.0.0.1'),
            'os_info' => PHP_OS.' / PHP '.PHP_VERSION,
            'agent_version' => '1.0.0',
            'system' => [
                'hostname' => gethostname(),
                'ip_v4' => request()->server('SERVER_ADDR', '127.0.0.1'),
                'os' => PHP_OS.' / PHP '.PHP_VERSION,
                'app_name' => $meta['name'],
                'app_url' => $meta['url'],
            ],
            'webservers' => [
                'app' => [
                    'installed' => true,
                    'running' => true,
                    'vhosts' => [
                        [
                            'domain' => $meta['host'],
                            'upstream' => $meta['url'],
                            'ssl_status' => $meta['is_https'] ? 'valid' : 'none',
                            'ssl_expires_at' => $meta['is_https'] ? ($now + (90 * 86400)) : null,
                        ],
                    ],
                ],
            ],
        ];

        try {
            // 1. Kirim telemetry untuk mendaftarkan webserver & vhosts (APP_NAME & APP_URL)
            $response = Http::withToken((string) $this->cloudKey)
                ->timeout($this->timeout)
                ->post("{$this->cloudUrl}/api/v1/nodes/telemetry", $payload);

            // 2. Jika Sentinel tersedia di host, laporkan juga status sinkronisasi kernel
            $sentinelStatus = $this->sentinel?->isAvailable() ? $this->sentinel->status() : null;

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'node_id' => $nodeId,
                'app_name' => $meta['name'],
                'app_url' => $meta['url'],
                'data' => $response->json(),
                'sentinel_connected' => $sentinelStatus !== null,
                'sentinel_firewall' => $sentinelStatus['firewall_driver'] ?? null,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'node_id' => $nodeId,
            ];
        }
    }

    /**
     * Laporkan insiden ancaman yang terdeteksi atau diblokir ke Pasak Cloud Control Plane.
     */
    public function reportThreat(array $threatData): bool
    {
        if (! $this->isConfigured() || ! config('pasak.cloud.sync_threats', true)) {
            return false;
        }

        $meta = $this->getAppMetadata();
        $nodeId = $this->resolveNodeId();

        $payload = [
            'node_id' => $nodeId,
            'target_host' => $meta['url'],
            'threat_type' => $threatData['event_type'] ?? 'SECURITY_VIOLATION',
            'attacker_ip' => $threatData['ip_address'] ?? 'unknown',
            'severity' => strtoupper($threatData['threat_level'] ?? 'medium'),
            'blocked_at_kernel' => ! empty($threatData['blocked']),
            'details' => [
                'app_name' => $meta['name'],
                'app_url' => $meta['url'],
                'path' => $threatData['path'] ?? '/',
                'method' => $threatData['method'] ?? 'GET',
                'rule_label' => $threatData['rule_label'] ?? null,
                'evidence' => $threatData['evidence'] ?? null,
                'user_agent' => $threatData['user_agent'] ?? null,
            ],
        ];

        try {
            $response = Http::withToken((string) $this->cloudKey)
                ->timeout($this->timeout)
                ->post("{$this->cloudUrl}/api/v1/events/threat", $payload);

            return $response->successful();
        } catch (Throwable) {
            // Fail silently agar tidak memperlambat atau mengganggu request pengguna
            return false;
        }
    }

    /**
     * Tarik daftar IP penyerang global (Global Threat Blocklist) dari Pasak Cloud API.
     *
     * @return array<int, string>
     */
    public function fetchGlobalBlocklist(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $client = Http::timeout($this->timeout);
            if (! empty($this->cloudKey)) {
                $client = $client->withToken((string) $this->cloudKey);
            }

            $response = $client->get("{$this->cloudUrl}/api/v1/threats/global-blocklist");

            if ($response->successful()) {
                return (array) $response->json('ips', []);
            }

            return [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Ambil masked license key untuk ditampilkan di CLI atau dashboard tanpa mengekspos token rahasia.
     */
    public function getMaskedKey(): string
    {
        if (empty($this->cloudKey)) {
            return '(belum dikonfigurasi)';
        }

        $len = strlen($this->cloudKey);
        if ($len <= 12) {
            return 'psk_***';
        }

        return substr($this->cloudKey, 0, 8).'...'.substr($this->cloudKey, -4);
    }
}
