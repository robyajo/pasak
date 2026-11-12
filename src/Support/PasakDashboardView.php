<?php

namespace Internal\Pasak\Support;

use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Internal\Pasak\Models\PasakSetting;
use Internal\Pasak\Services\PasakUserLoginService;
use Throwable;

/**
 * Helper presentasi bersama untuk dashboard Blade murni (tanpa Livewire).
 */
class PasakDashboardView
{
    public static function levelLabel(string $level): string
    {
        return match ($level) {
            'critical' => 'Critical',
            'high' => 'High',
            'medium' => 'Medium',
            default => 'Low',
        };
    }

    public static function levelClass(string $level): string
    {
        return match ($level) {
            'critical' => 'sec-badge-critical',
            'high' => 'sec-badge-high',
            'medium' => 'sec-badge-medium',
            default => 'sec-badge-low',
        };
    }

    public static function trendBarHeight(mixed $total, int $maxTrend): string
    {
        return max(4, (int) round(((int) $total / max(1, $maxTrend)) * 120)).'px';
    }

    public static function severityPercent(int $count, int $levelTotal): string
    {
        return (int) round(($count / max(1, $levelTotal)) * 100).'%';
    }

    public static function severityColor(string $level): string
    {
        return match ($level) {
            'critical' => 'var(--sec-danger)',
            'high' => 'var(--sec-warning)',
            'medium' => '#d97706',
            default => 'var(--sec-text-subtle)',
        };
    }

    public static function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'critical' => 'sec-badge-critical',
            'warning' => 'sec-badge-high',
            'ok' => 'sec-badge-success',
            default => 'sec-badge-low',
        };
    }

    /**
     * Konversi kode negara 2 huruf ISO ke bendera emoji.
     */
    public static function countryFlag(?string $countryCode): string
    {
        if (empty($countryCode) || strlen($countryCode) !== 2 || ! ctype_alpha($countryCode)) {
            return '🌐';
        }

        $code = strtoupper($countryCode);
        $first = ord($code[0]) - ord('A') + 0x1F1E6;
        $second = ord($code[1]) - ord('A') + 0x1F1E6;

        return mb_chr($first, 'UTF-8').mb_chr($second, 'UTF-8');
    }

    /**
     * Resolusi lokasi IP (negara, provinsi, kota, ISP) dengan fallback aman.
     *
     * @return array{city: string|null, region: string|null, country: string|null, country_code: string|null, latitude: float|null, longitude: float|null, isp: string|null}
     */
    public static function resolveLocation(string $ip): array
    {
        try {
            return app(PasakUserLoginService::class)->resolveLocation($ip);
        } catch (Throwable) {
            return [
                'city' => null,
                'region' => null,
                'country' => null,
                'country_code' => null,
                'latitude' => null,
                'longitude' => null,
                'isp' => null,
            ];
        }
    }

    /**
     * Resolusi timezone tampilan dasbor:
     * 1. Prioritas konfigurasi eksplisit: config('pasak.dashboard.timezone') atau env('PASAK_DASHBOARD_TIMEZONE')
     * 2. env('APP_TIMEZONE') dari .env aplikasi host
     * 3. config('app.timezone') jika bukan 'UTC' default bawaan Laravel
     * 4. Auto-detect timezone server fisik tempat aplikasi dipasang (/etc/localtime atau /etc/timezone)
     * 5. Fallback ke config('app.timezone', 'UTC')
     */
    public static function timezone(): string
    {
        try {
            $dbTz = Cache::remember('pasak:server_timezone', 86400, function (): ?string {
                return PasakSetting::getServerTimezone();
            });
            if (! empty($dbTz) && in_array($dbTz, DateTimeZone::listIdentifiers(), true)) {
                return (string) $dbTz;
            }
        } catch (Throwable) {
        }

        $custom = config('pasak.dashboard.timezone') ?: config('app.timezone');
        if (! empty($custom) && in_array($custom, DateTimeZone::listIdentifiers(), true)) {
            return (string) $custom;
        }

        $appTz = config('app.timezone');
        if (! empty($appTz) && $appTz !== 'UTC' && in_array($appTz, DateTimeZone::listIdentifiers(), true)) {
            return (string) $appTz;
        }

        // Auto-detect timezone sistem/server tempat aplikasi diinstal
        $detected = null;
        if (is_link('/etc/localtime')) {
            $link = @readlink('/etc/localtime');
            if ($link && preg_match('#(?:zoneinfo|zoneinfo\\.default)/(.+)$#', $link, $matches)) {
                $detected = $matches[1];
            }
        }
        if (! $detected && file_exists('/etc/timezone') && is_readable('/etc/timezone')) {
            $t = trim((string) @file_get_contents('/etc/timezone'));
            if ($t !== '') {
                $detected = $t;
            }
        }

        if ($detected && in_array($detected, DateTimeZone::listIdentifiers(), true)) {
            return (string) $detected;
        }

        return ! empty($appTz) ? (string) $appTz : 'UTC';
    }

    /**
     * Dapatkan objek Carbon saat ini dalam zona waktu server yang dikonfigurasi.
     */
    public static function now(): Carbon
    {
        return Carbon::now(static::timezone());
    }

    /**
     * Format tanggal/waktu sesuai locale aplikasi (APP_LOCALE=id / en) dan timezone server pengguna.
     */
    public static function formatDate(mixed $date, string $format = 'd M Y H:i:s'): string
    {
        if (! $date) {
            return '—';
        }

        if (! ($date instanceof DateTimeInterface)) {
            try {
                $date = Carbon::parse($date);
            } catch (Throwable) {
                return (string) $date;
            }
        }

        $carbon = $date instanceof Carbon
            ? $date->copy()
            : Carbon::instance($date);

        $locale = app()->getLocale();
        if (in_array($locale, ['id', 'en'], true)) {
            $carbon->locale($locale);
        }

        try {
            $carbon->setTimezone(static::timezone());
        } catch (Throwable) {
            // Abaikan jika timezone tidak valid
        }

        return $carbon->translatedFormat($format);
    }

    /**
     * Tampilan waktu relatif (misal: "5 menit yang lalu" atau "5 minutes ago") sesuai locale dan timezone server.
     */
    public static function diffForHumans(mixed $date, int|array|null $syntax = null): string
    {
        if (! $date) {
            return '—';
        }

        if (! ($date instanceof DateTimeInterface)) {
            try {
                $date = Carbon::parse($date);
            } catch (Throwable) {
                return (string) $date;
            }
        }

        $carbon = $date instanceof Carbon
            ? $date->copy()
            : Carbon::instance($date);

        $locale = app()->getLocale();
        if (in_array($locale, ['id', 'en'], true)) {
            $carbon->locale($locale);
        }

        try {
            $carbon->setTimezone(static::timezone());
        } catch (Throwable) {
            // Abaikan jika timezone tidak valid
        }

        return $carbon->diffForHumans(null, $syntax);
    }

    /**
     * Analisis profil penyerang / client berdasarkan User-Agent dan karakteristik request.
     * Mengidentifikasi Scanner Keamanan, Bot/Script Otomatis, Web Crawler, Headless Browser, atau Browser Biasa.
     *
     * @return array{
     *     category: 'scanner'|'bot'|'crawler'|'headless'|'browser',
     *     category_label: string,
     *     name: string,
     *     icon: string,
     *     badge_class: string,
     *     is_bot: bool,
     *     os: string,
     *     browser: string,
     *     device_type: string,
     *     raw_ua: string
     * }
     */
    public static function attackerInfo(?string $userAgent, ?string $eventType = null): array
    {
        $rawUa = trim((string) $userAgent);

        if ($rawUa === '' || $rawUa === '-' || $rawUa === '—') {
            return [
                'category' => 'headless',
                'category_label' => 'Tanpa User-Agent (Headless)',
                'name' => 'Headless / Raw HTTP',
                'icon' => '🕵️',
                'badge_class' => 'sec-badge-critical',
                'is_bot' => true,
                'os' => 'Tidak Diketahui',
                'browser' => 'None',
                'device_type' => 'bot',
                'raw_ua' => $rawUa,
            ];
        }

        // 1. Security Exploit Scanners & Reconnaissance Tools
        $scanners = [
            'sqlmap' => 'SQLMap (Automated SQLi)',
            'nuclei' => 'Nuclei (Vuln Scanner)',
            'nikto' => 'Nikto (Web Scanner)',
            'acunetix' => 'Acunetix (Web Scanner)',
            'wvs' => 'Acunetix WVS',
            'nessus' => 'Nessus (Vuln Scanner)',
            'burpcollaborator' => 'Burp Suite Collaborator',
            'burpsuite' => 'Burp Suite Proxy',
            'burp' => 'Burp Suite',
            'zaproxy' => 'OWASP ZAP',
            'owasp zap' => 'OWASP ZAP',
            'dirbuster' => 'DirBuster (Path Fuzzer)',
            'gobuster' => 'Gobuster (URI Bruter)',
            'ffuf' => 'FFUF (Web Fuzzer)',
            'wpscan' => 'WPScan (WordPress)',
            'masscan' => 'Masscan (Port/Web)',
            'nmap' => 'Nmap (Network Scanner)',
            'openvas' => 'OpenVAS (Security Scanner)',
            'qualys' => 'Qualys (Vuln Scanner)',
            'arachni' => 'Arachni (Web Scanner)',
            'whatweb' => 'WhatWeb (Tech Scanner)',
            'w3af' => 'w3af (Attack Framework)',
            'hydra' => 'THC-Hydra (Logon Cracker)',
            'metasploit' => 'Metasploit Framework',
            'zgrab' => 'ZGrab (Banner Prober)',
            'havij' => 'Havij (SQLi Tool)',
            'netsparker' => 'Netsparker (Scanner)',
            'censys' => 'Censys (Recon Prober)',
            'shodan' => 'Shodan (Network Prober)',
            'zoomeye' => 'ZoomEye (Cyberspace Scanner)',
            'binaryedge' => 'BinaryEdge (Threat Prober)',
            'morfeus' => 'Morfeus Scanner',
            'commix' => 'Commix (Command Injection)',
            'sublist3r' => 'Sublist3r (Subdomain Recon)',
            'jaeles' => 'Jaeles (Web Scanner)',
            'dsss' => 'Damn Small SQLi Scanner',
        ];

        foreach ($scanners as $sig => $label) {
            if (stripos($rawUa, $sig) !== false) {
                return [
                    'category' => 'scanner',
                    'category_label' => 'Alat Scanner Eksploitasi',
                    'name' => $label,
                    'icon' => '🚨',
                    'badge_class' => 'sec-badge-critical',
                    'is_bot' => true,
                    'os' => 'Security Tool',
                    'browser' => $label,
                    'device_type' => 'scanner',
                    'raw_ua' => $rawUa,
                ];
            }
        }

        // 2. Headless Browsers & Automation Frameworks
        $automation = [
            'headlesschrome' => 'Headless Chrome',
            'phantomjs' => 'PhantomJS Headless',
            'selenium' => 'Selenium Automation',
            'puppeteer' => 'Puppeteer Automation',
            'playwright' => 'Playwright Automation',
            'cypress' => 'Cypress Test Automation',
            'webdriver' => 'WebDriver Automation',
        ];

        foreach ($automation as $sig => $label) {
            if (stripos($rawUa, $sig) !== false) {
                return [
                    'category' => 'bot',
                    'category_label' => 'Headless / Browser Otomasi',
                    'name' => $label,
                    'icon' => '🤖',
                    'badge_class' => 'sec-badge-critical',
                    'is_bot' => true,
                    'os' => 'Automation Environment',
                    'browser' => $label,
                    'device_type' => 'bot',
                    'raw_ua' => $rawUa,
                ];
            }
        }

        // 3. Automated HTTP Clients & Scripting Engines
        $scripts = [
            'python-requests' => 'Python Requests',
            'python-urllib' => 'Python urllib',
            'python/' => 'Python HTTP',
            'aiohttp' => 'Python aiohttp',
            'httpx' => 'Python HTTPX',
            'scrapy' => 'Scrapy Crawler',
            'guzzlehttp' => 'Guzzle PHP Client',
            'guzzle' => 'Guzzle PHP Client',
            'go-http-client' => 'Go HTTP Client',
            'axios' => 'Axios HTTP Client',
            'postmanruntime' => 'Postman API Client',
            'postman' => 'Postman Client',
            'insomnia' => 'Insomnia REST Client',
            'httpie' => 'HTTPie CLI',
            'curl' => 'cURL CLI Tool',
            'wget' => 'GNU Wget',
            'libwww-perl' => 'Perl LWP Library',
            'lwp-' => 'Perl LWP',
            'apache-httpclient' => 'Apache HttpClient',
            'java/' => 'Java HTTP Client',
            'okhttp' => 'OkHttp Library',
            'node-fetch' => 'Node.js Fetch Client',
            'undici' => 'Node.js Undici Client',
            'winhttp' => 'Windows WinHTTP API',
            'powershell' => 'PowerShell WebRequest',
            'ruby' => 'Ruby Net::HTTP',
            'restsharp' => 'RestSharp .NET Client',
            'colly' => 'Colly Golang Scraper',
            'reqwest' => 'Rust Reqwest Client',
        ];

        foreach ($scripts as $sig => $label) {
            if (stripos($rawUa, $sig) !== false) {
                return [
                    'category' => 'bot',
                    'category_label' => 'Script / Bot HTTP Otomatis',
                    'name' => $label,
                    'icon' => '🤖',
                    'badge_class' => 'sec-badge-high',
                    'is_bot' => true,
                    'os' => 'Script Engine',
                    'browser' => $label,
                    'device_type' => 'script',
                    'raw_ua' => $rawUa,
                ];
            }
        }

        // 4. Known Web Crawlers & Search Engine Bots
        $crawlers = [
            'googlebot' => 'Googlebot',
            'bingbot' => 'Bingbot',
            'yandexbot' => 'Yandex Bot',
            'yandex' => 'Yandex Bot',
            'baiduspider' => 'Baidu Spider',
            'duckduckbot' => 'DuckDuckBot',
            'semrushbot' => 'SEMrush SEO Bot',
            'ahrefsbot' => 'Ahrefs SEO Bot',
            'dotbot' => 'DotBot Crawler',
            'mj12bot' => 'Majestic-12 Bot',
            'bytespider' => 'ByteSpider (ByteDance)',
            'petalbot' => 'PetalBot (Huawei)',
            'applebot' => 'Applebot',
            'facebookexternalhit' => 'Meta / Facebook Bot',
            'twitterbot' => 'Twitterbot / X',
            'linkedinbot' => 'LinkedIn Bot',
            'discordbot' => 'Discord Bot',
            'telegrambot' => 'Telegram Bot',
            'whatsapp' => 'WhatsApp Bot',
            'slackbot' => 'Slack Bot',
        ];

        foreach ($crawlers as $sig => $label) {
            if (stripos($rawUa, $sig) !== false) {
                return [
                    'category' => 'crawler',
                    'category_label' => 'Web Crawler / Indexer',
                    'name' => $label,
                    'icon' => '🕷️',
                    'badge_class' => 'sec-badge-medium',
                    'is_bot' => true,
                    'os' => 'Crawler Platform',
                    'browser' => $label,
                    'device_type' => 'crawler',
                    'raw_ua' => $rawUa,
                ];
            }
        }

        // 5. Generic bot/spider regex
        if (preg_match('/(bot|crawler|spider|scraper|prober|scan)/i', $rawUa, $m)) {
            $matchedWord = ucfirst(strtolower($m[1]));

            return [
                'category' => 'bot',
                'category_label' => 'Bot Otomatis Terdeteksi',
                'name' => "Generic {$matchedWord}",
                'icon' => '🤖',
                'badge_class' => 'sec-badge-high',
                'is_bot' => true,
                'os' => 'Automated Bot',
                'browser' => "Bot/{$matchedWord}",
                'device_type' => 'bot',
                'raw_ua' => $rawUa,
            ];
        }

        // 6. Standard Browser (Human or Spoofed UA)
        try {
            $parsed = app(PasakUserLoginService::class)->parseUserAgent($rawUa);
            $os = $parsed['os'];
            $browser = $parsed['browser'];
            $device = $parsed['device'];
        } catch (Throwable) {
            $os = 'Unknown OS';
            $browser = 'Unknown Browser';
            $device = 'desktop';
        }

        $icon = match ($device) {
            'mobile' => '📱',
            'tablet' => '📱',
            default => '💻',
        };

        $displayName = ($browser !== 'Unknown Browser' && $os !== 'Unknown OS')
            ? "{$browser} ({$os})"
            : ($browser !== 'Unknown Browser' ? $browser : ($os !== 'Unknown OS' ? $os : 'Web Browser'));

        return [
            'category' => 'browser',
            'category_label' => 'Browser Web (Human / Spoofed UA)',
            'name' => $displayName,
            'icon' => $icon,
            'badge_class' => 'sec-badge-low',
            'is_bot' => false,
            'os' => $os,
            'browser' => $browser,
            'device_type' => $device,
            'raw_ua' => $rawUa,
        ];
    }
}
