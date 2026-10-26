---
name: pasak
description: "Comprehensive guide for working with robyajo/pasak (Bulwark WAF & Security Toolkit) in this Laravel application. Activate whenever writing controllers, routes, views, APIs, or tests interacting with Pasak WAF, IP blocking, device quarantine, whitelist/allowed IPs, server security audit, user sessions, unblock appeal tickets, or runtime WAF settings."
license: MIT
metadata:
  author: robyajo
---

# Pasak WAF & Security Guide for Host Applications

This application is protected by **`robyajo/pasak`** (Bulwark), an enterprise-grade, standalone, headless self-hosted Web Application Firewall (WAF) and security auditing toolkit for Laravel applications.

> 📖 **Official Online Documentation Portal**: [https://pasak.is-a.dev/](https://pasak.is-a.dev/)  
> Panduan lengkap 26 bab, diagram arsitektur interaktif, peta ancaman realtime, REST API contract, dan resep produksi.
>
> **Rujukan Cepat Dokumentasi Online (`https://pasak.is-a.dev/`)**:
> - 🚀 [Panduan Instalasi & Pengaturan Awal](https://pasak.is-a.dev/getting-started/installation/)
> - ⚙️ [Konfigurasi Lengkap `config/pasak.php` & `.env`](https://pasak.is-a.dev/getting-started/configuration/)
> - 🛡️ [Mesin Deteksi Ancaman & Tanda Tangan ReDoS](https://pasak.is-a.dev/core-architecture/threat-detection-engine/)
> - 📱 [Device Quarantine & Resolusi Reverse Proxy](https://pasak.is-a.dev/core-architecture/device-quarantine/)
> - 🔒 [Audit Keamanan Server & Integritas Berkas](https://pasak.is-a.dev/security-modules/server-security-and-webshells/)
> - 📊 [Streaming Access Log Parser (< 15MB RAM)](https://pasak.is-a.dev/security-modules/access-log-streaming/)
> - 🌐 [Kontrak REST API & JSON Envelope](https://pasak.is-a.dev/api-reference/api-overview/)
> - 🎨 [Custom Dashboard & API Host (Tanpa Ubah Vendor)](https://pasak.is-a.dev/integration-guides/custom-dashboard-dan-api-tanpa-ubah-vendor/)
> - 🧱 [Hardening Webserver Nginx](https://pasak.is-a.dev/webserver-hardening/nginx-hardened-waf/) & [Apache .htaccess](https://pasak.is-a.dev/webserver-hardening/apache-htaccess-hardening/)

---

## 1. Zero-Vendor-Edit Policy

> ⚠️ **CRITICAL INVARIANT**:
> **NEVER modify, edit, or add files inside `vendor/robyajo/pasak/`**.
> Any changes in `vendor/` will be permanently erased by Composer updates and risk breaking WAF ReDoS immunity.
> All custom controllers, views, API endpoints, and business logic MUST live entirely inside the host application (`app/Http/Controllers/`, `resources/views/`, `routes/web.php`, `routes/api.php`).

---

## 2. Core Facade: `Internal\Pasak\Facades\Pasak`

Always use the official `Pasak` Facade for interacting with the WAF engine:

```php
use Internal\Pasak\Facades\Pasak;

// 1. Status & Inspeksi
$isEnabled = Pasak::isEnabled();
$isEnforced = Pasak::isBlockEnforcementEnabled();
$stats = Pasak::stats(); // total_logs, logs_today, critical_today, active_blocks
$breakdown = Pasak::levelBreakdown(24);
$topAttackers = Pasak::topAttackers(5);

// 2. Pemeriksaan & Pemblokiran IP
$isBlocked = Pasak::isBlocked('198.51.100.1');

Pasak::blockIp(
    ip: '198.51.100.1',
    reason: 'Percobaan serangan brute-force berulang',
    durationHours: 24, // null untuk permanen
    source: 'manual',
    userId: auth()->id(),
    scope: 'ip' // 'ip' (seluruh router/NAT) atau 'device' (quarantine spesifik via X-Device-Id)
);

Pasak::unblockIp('198.51.100.1');

// 3. Pengaturan Runtime Dinamis (tabel pasak_settings)
$settings = Pasak::getSettings();
Pasak::updateSettings([
    'auto_block_enabled' => true,
    'auto_block_threshold' => 5,
    'auto_block_duration' => 24,
    'auto_block_scope' => 'device',
    'instant_block_enabled' => true,
    'block_enforcement' => true,
]);
```

---

## 3. Model Eloquent & Database

Semua model memiliki awalan tabel dinamis dan dapat di-query langsung seperti model Eloquent standar:

| Model | FQCN | Nama Tabel Default | Deskripsi & Kolom Kunci |
|---|---|---|---|
| `PasakLog` | `Internal\Pasak\Models\PasakLog` | `pasak_logs` | Catatan deteksi ancaman (`ip_address`, `threat_level`, `path`, `rule_label`, `was_blocked`, `payload`). |
| `PasakBlockedIp` | `Internal\Pasak\Models\PasakBlockedIp` | `pasak_blocked_ips` | IP & perangkat terisolasi (`ip_address`, `device_id`, `block_scope`, `is_active`, `blocked_until`). |
| `PasakAllowedIp` | `Internal\Pasak\Models\PasakAllowedIp` | `pasak_allowed_ips` | Whitelist IP & subnet CIDR (kebal dari seluruh deteksi WAF). |
| `PasakUserLogin` | `Internal\Pasak\Models\PasakUserLogin` | `pasak_user_logins` | Sesi pengguna masuk, browser, OS, heartbeat (`last_activity_at`), dan waktu `logout_at`. |
| `PasakTrustedIp` | `Internal\Pasak\Models\PasakTrustedIp` | `pasak_trusted_ips` | Alamat IP admin/staf terpercaya yang dilewatkan dari login throttle. |
| `PasakIpUnblockRequest` | `Internal\Pasak\Models\PasakIpUnblockRequest` | `pasak_ip_unblock_requests` | Tiket permohonan banding unblock (`ticket_number`, `status`, `reason`, `admin_notes`). |
| `PasakLoginAttempt` | `Internal\Pasak\Models\PasakLoginAttempt` | `pasak_login_attempts` | Pelacakan login gagal dan penguncian bertingkat (*stepped login throttling*). |

---

## 4. Audit Keamanan Server & Integritas Berkas

Gunakan service resmi `PasakServerSecurityService`:

```php
use Internal\Pasak\Services\PasakServerSecurityService;

$service = app(PasakServerSecurityService::class);

// Pindai konfigurasi PHP, database, izin berkas, dan akun admin (cached)
$report = $service->scan($force = false); // summary, categories, checks

// Kelola baseline integritas kriptografis SHA-256 berkas sistem kritis
$summary = $service->baselineSummary(); // exists, files, watched
$baseline = $service->createBaseline(auth()->id());
$deleted = $service->deleteBaseline();

// Musnahkan berkas webshell / berbahaya secara defensif
// Validasi otomatis: terisolasi di base_path(), tolak path traversal, lindungi berkas vital (index.php, .env, composer.json)
$res = $service->deleteSuspiciousFile('storage/app/public/shell.php');
```

---

## 5. Middleware & Integrasi Rute

Paket menyediakan alias middleware bawaan:
- `'pasak.block'`: Memblokir IP atau device yang berstatus aktif di `pasak_blocked_ips` dengan HTTP 403 Forbidden.
- `'pasak.detect'`: Menginspeksi muatan request secara realtime terhadap tanda tangan ReDoS, SQLi, XSS, Path Traversal, SSTI, Polyglot Upload.
- `'pasak.admin'`: Melindungi endpoint manajemen dengan otorisasi `Gate::authorize('manage-pasak')`.
- `'pasak.activity'`: Melacak heartbeat aktivitas pengguna setiap 45 detik.

### Reverse Proxy & Real Client IP
Bila aplikasi berada di balik Cloudflare, AWS ALB, Nginx, atau LiteSpeed, daftarkan *trusted proxies* di `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: '*');
})
```

Header `CF-Connecting-IP`, `X-Real-IP`, dan `X-Forwarded-For` akan otomatis dievaluasi dengan aman.

---

## 6. Perintah Artisan

| Perintah | Deskripsi |
|---|---|
| `php artisan pasak:install` | Menyiapkan konfigurasi, migrasi, Nginx, .htaccess, dashboard, trait User, dan skill AI. |
| `php artisan pasak:upgrade` | Sinkronisasi migrasi, konfigurasi, stubs, dan pembaruan versi. |
| `php artisan pasak:uninstall` | Menghapus seluruh aset dan memulihkan aplikasi host ke kondisi bersih. |
| `php artisan pasak:unblock-ip {ip}` | Membuka blokir alamat IP secara manual melalui konsol. |
| `php artisan pasak:baseline` | Membuat atau memperbarui baseline sidik jari SHA-256 berkas sistem. |
| `php artisan pasak:scan-logs` | Streaming parser log webserver berkecepatan tinggi (< 15MB RAM). |
| `php artisan pasak:prune-logs` | Merotasi dan membersihkan riwayat log ancaman yang melampaui masa retensi. |
| `php artisan pasak:secret` | Mengelola token rahasia dashboard dan akses darurat. |

---

## 7. Praktik Pengujian (Pest & PHPUnit)

Saat menulis pengujian untuk fitur yang bersinggungan dengan keamanan:
```php
use App\Models\User;
use Internal\Pasak\Facades\Pasak;
use Internal\Pasak\Models\PasakLog;

test('blocked ip receives 403 forbidden', function () {
    Pasak::blockIp('203.0.113.99', 'Uji coba blokir');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
        ->get('/');

    $response->assertStatus(403);
});
```
