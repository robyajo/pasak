# Pasak (Bulwark)

[![Run Tests](https://github.com/robyajo/pasak/actions/workflows/run-tests.yml/badge.svg)](https://github.com/robyajo/pasak/actions/workflows/run-tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/robyajo/pasak.svg)](https://packagist.org/packages/robyajo/pasak)
[![Total Downloads](https://img.shields.io/packagist/dt/robyajo/pasak.svg)](https://packagist.org/packages/robyajo/pasak)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2%20%7C%20%5E8.3%20%7C%20%5E8.4%20%7C%20%5E8.5-blue.svg)](https://www.php.net/supported-versions.php)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E10.0%20%7C%20%5E11.0%20%7C%20%5E12.0%20%7C%20%5E13.0-red.svg)](https://laravel.com/docs/releases)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

**Pasak** (Bulwark) adalah paket keamanan komprehensif (_Self-Hosted WAF & Threat Engine_) berbasis **Headless REST API** untuk ekosistem Laravel. Paket ini murni PHP Composer library (Zero-NPM / standar Spatie) yang dirancang khusus untuk memproteksi aplikasi web internal dari serangan siber tingkat lanjut, injeksi payload pentest, eksploitasi webshell, dan brute force tanpa mengikat aplikasi ke template frontend tertentu.

> 📚 **Portal Dokumentasi Resmi Lengkap**: Tersedia 25 bab dokumentasi mendalam di direktori [`documents/`](./documents/README.md) serta portal interaktif offline [`documents/index.html`](./documents/index.html).

---

## 🌟 Fitur Utama

1. **Self-Hosted WAF & Zero-Tolerance Threat Detection**:
    - Deteksi instan tanpa batas ambang (_zero-tolerance_) untuk null-byte upload (`.php%00.jpg`), ekstensi ganda (`.php.jpg`), path traversal (`../../../public/`), probe file sensitif (`.htaccess`, `.env`, `.git`), dan SSTI canary (`{{7*7}}`).
    - Deteksi komprehensif untuk SQL Injection, Cross-Site Scripting (XSS), Local File Inclusion (LFI), Command Injection, dan Scanner User-Agents.
    - Pola regex yang diperketat dan kebal terhadap serangan ReDoS (_Regular Expression Denial of Service_).

2. **Isolasi Perangkat Granular (Device-Level Quarantine)**:
    - Dukungan isolasi di tingkat perangkat menggunakan `device_id` (WebRTC/fingerprint) dan `local_ip`.
    - Memastikan perangkat penyerang terblokir tanpa mengganggu pengguna sah lain yang berbagi alamat IP publik yang sama (seperti kantor atau router Wi-Fi publik).

3. **Tiket Banding & Permohonan Buka Blokir (Appeal Tickets)**:
    - Endpoint publik REST API bagi pengguna yang terblokir untuk mengajukan tiket permohonan buka blokir beserta status pelacakannya.
    - Antarmuka persetujuan admin yang secara otomatis mencabut karantina IP/perangkat dan memasukkannya ke whitelist.

4. **Multi-Tier Stepped Login Lockout**:
    - Sistem pencegahan _credential stuffing_ & _brute force_ berjenjang (1 menit, 5 menit, 15 menit, 1 jam, hingga 24 jam).
    - Pencatatan otomatis riwayat kegagalan otentikasi ke log audit keamanan.

5. **Server Integrity & Webshell Scanner**:
    - Pembuatan dan verifikasi baseline hash SHA-256 untuk berkas-berkas aplikasi inti.
    - Pemindaian berkas mencurigakan / webshell (ekstensi ganda, skrip di direktori publik/upload, polyglot media).
    - Fitur penghapusan berkas berbahaya yang aman dengan proteksi path traversal dan berkas sistem vital.
    - Audit konfigurasi keamanan server (`APP_DEBUG`, secure session cookie, Fortify 2FA).

6. **Streaming Access Log Scanner**:
    - Pemindai berkas log mentah Apache / Nginx secara _streaming_ berdaya hemat memori untuk menangkap penyerang yang ditolak oleh web server sebelum request mencapai proses PHP Laravel.

7. **Headless & Arsitektur Terkopel Longgar**:
    - 100% REST API JSON murni.
    - Model `User` dan nama tabel database sepenuhnya dapat dikonfigurasi melalui `config/pasak.php`.
    - Trait `HasSecurityRelations` untuk kemudahan integrasi relasi Eloquent.

---

## 📋 Persyaratan Sistem

- PHP: `^8.2`, `^8.3`, `^8.4`, atau `^8.5`
- Laravel: `^10.0`, `^11.0`, `^12.0`, atau `^13.0`

---

## 🚀 Instalasi

### 1. Pasang Paket via Composer

```bash
composer require "robyajo/pasak:^1.6.7"
```

> **Penting — gunakan versi rilis lengkap.** Menjalankan
> `composer require "robyajo/pasak:^1.6.7"` memastikan `composer.json` mencatat rilis
> patch yang akurat (Composer secara bawaan menyingkat menjadi `^1.6` bila tanpa versi).
> Perintah `php artisan pasak:install` juga otomatis menyelaraskan constraint di
> `composer.json` ke versi lengkap terbaru (`^1.6.7`). Jangan gunakan `@dev` di produksi.

### 2. Publikasikan Aset Otomatis (`pasak:install`)

Gunakan perintah satu langkah untuk mempublikasikan dan menerapkan seluruh aset keamanan secara otomatis:

```bash
php artisan pasak:install
```

#### Aset yang Didapat Pengguna Setelah Menjalankan Perintah Ini:

1. 📄 **`config/pasak.php`**: Konfigurasi lengkap WAF, ambang batas blokir, IP whitelist, stepped login lockout, dan log scanner.
2. 🗄️ **`database/migrations/` (migrasi konsolidasi + tambahan)**: Menyiapkan tabel `blocked_ips`, `pasak_logs`, `login_attempts`, `ip_unblock_requests`, `user_logins`, `trusted_ips`, `settings`, dan `allowed_ips` (otomatis memindahkan data dari tabel lama `security_logs`/`security_settings` bila ada).
3. 🌐 **`nginx.conf`**: Konfigurasi produksi Nginx Hardened WAF (Dual-zone rate limit, single-PHP execution `/index.php`, storage sandboxing).
4. 🛡️ **`public/.htaccess`**: Hardening web server Apache & LiteSpeed (Blokir dotfiles, double extension `.php.jpg`, file backup dump `.sql`, dan matikan directory listing).
    > _Catatan Keamanan_: Jika `public/.htaccess` lama sudah ada, installer otomatis membuat cadangan `public/.htaccess.backup-YYYYMMDD_HHMMSS` dan menyisipkan aturan keamanan di bawah tanpa merusak rewrite rules aplikasi Anda.
5. 🚫 **`resources/views/errors/blocked.blade.php`**: Halaman 403 default yang menampilkan alasan blokir, kode referensi, dan **formulir banding** yang terhubung langsung ke endpoint publik tiket banding. Dapat disesuaikan sesuai branding aplikasi Anda.
6. ⚙️ **Penyematan Variabel ke `.env` & `.env.example`**: Installer secara otomatis menambahkan blok konfigurasi lengkap disertai **penjelasan fungsi berbahasa Indonesia** untuk setiap variabel (`PASAK_*`) langsung ke berkas `.env` dan `.env.example` aplikasi Anda.
7. 📊 **(Opsional) Dashboard monitoring Blade murni**: Tampilan monitoring `resources/views/pages/pasak/` (100% Pure PHP, jalan di semua aplikasi Laravel tanpa Livewire/Inertia/Vue), wajib login. Dipublikasikan bila Anda menambahkan opsi `--with-dashboard` (alias: `--with-plain`, `--with-blade`).

#### Opsi Perintah `pasak:install`:

| Opsi                     | Keterangan                                                                                         |
| :----------------------- | :------------------------------------------------------------------------------------------------- |
| `--force`                | Menimpa seluruh berkas konfigurasi, migrasi, `nginx.conf`, `public/.htaccess`, dan halaman blokir. |
| `--without-nginx`        | Melewatkan pembuatan berkas `nginx.conf`.                                                          |
| `--without-apache`       | Melewatkan pembuatan berkas `apache2.conf`.                                                        |
| `--without-htaccess`     | Melewatkan pembaruan berkas `public/.htaccess`.                                                    |
| `--without-views`        | Melewatkan publikasi halaman blokir `errors/blocked.blade.php`.                                    |
| `--without-env`          | Melewatkan penyematan variabel konfigurasi ke berkas `.env` dan `.env.example`.                    |
| `--without-user-trait`   | Melewatkan penyematan trait `HasSecurityRelations` ke model User.                                  |
| `--without-middleware`   | Melewatkan pendaftaran middleware WAF ke `bootstrap/app.php` / Kernel.                             |
| `--without-routes`       | Melewatkan publikasi berkas `routes/pasak.php` dan `routes/pasak-api.php`.                          |
| `--without-api`          | Melewatkan pemasangan dukungan API (tanpa rute API).                                               |
| `--without-web`          | Melewatkan pemasangan dashboard web (tanpa halaman/dashboard).                                     |
| `--api-only`             | Jalan pintas: API ya, Web tidak (inti + keamanan saja).                                            |
| `--with-dashboard`       | Mempublikasikan dashboard monitoring Blade murni, jalan di semua aplikasi Laravel (wajib login).   |
| `--with-plain`           | Alias dari `--with-dashboard`.                                                                     |
| `--with-blade`           | Alias dari `--with-dashboard`.                                                                     |
| `--with-htaccess`        | Memaksa pembaruan berkas `public/.htaccess`.                                                       |

#### Publikasi Aset Secara Parsial (Manual):

```bash
# 1. Konfigurasi saja
php artisan vendor:publish --tag=pasak-config

# 2. Migrasi database saja
php artisan vendor:publish --tag=pasak-migrations

# 3. Konfigurasi server Nginx saja
php artisan vendor:publish --tag=pasak-nginx

# 4. Aturan hardening Apache .htaccess saja
php artisan vendor:publish --tag=pasak-htaccess --force

# 5. Halaman blokir default saja
php artisan vendor:publish --tag=pasak-views --force

# 6. Dashboard monitoring Blade murni
php artisan vendor:publish --tag=plain-views --force

# 7. Seluruh aset sekaligus
php artisan vendor:publish --tag=pasak-all --force
```

Jalankan migrasi database:

```bash
php artisan migrate
```

### 3. Dashboard Monitoring Blade Murni

Paket ini menyediakan panel monitoring siap pakai **Blade murni (100% Pure PHP, tanpa Livewire/Inertia/Vue)** yang dapat dipublikasikan langsung ke dalam aplikasi host di prefix **`/pasak`** — berjalan di semua aplikasi Laravel:

```bash
php artisan pasak:install --with-dashboard
# atau: php artisan vendor:publish --tag=plain-views
```

Perintah tersebut menyalin **`resources/views/pages/pasak/*.blade.php`** (tujuh halaman + layout dengan CSS/JS mandiri, tanpa build step) beserta konfigurasi `dashboard` pada `config/pasak.php`.

#### Mengaktifkan Dashboard & Wajib Login

Konfigurasi di `.env`:

```dotenv
PASAK_DASHBOARD_ENABLED=true
PASAK_DASHBOARD_PREFIX=pasak
# Batasi hanya role admin (default: false, cukup login pengguna)
PASAK_DASHBOARD_ADMIN_ONLY=false
```

Setelah diaktifkan, panel dapat diakses pada **`/pasak`**.

> 🔒 **Wajib Login**: Seluruh halaman dashboard di prefix `/pasak` dilindungi oleh middleware `web` + `auth`. Pengguna yang belum login otomatis diarahkan ke halaman login. Jika Anda ingin membatasi dashboard khusus untuk administrator, set `PASAK_DASHBOARD_ADMIN_ONLY=true` di berkas `.env`.

---

## ⚙️ Konfigurasi & Integrasi

### 1. Tambahkan Trait ke Model User

Buka model `App\Models\User.php` dan tambahkan trait `HasSecurityRelations`:

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Internal\Pasak\Concerns\HasSecurityRelations;

class User extends Authenticatable
{
    use HasSecurityRelations;

    // ...
}
```

Trait ini menyediakan relasi Eloquent bawaan:

- `$user->logins()`: Riwayat login pengguna (`UserLogin`).
- `$user->trustedIps()`: Daftar IP terpercaya pengguna (`TrustedIp`).
- `$user->securityLogs()`: Riwayat event keamanan pengguna (`SecurityLog`).
- `$user->blockedIps()`: Riwayat pemblokiran yang dilakukan oleh pengguna (`BlockedIp`).
- `$user->allowedIps()`: Entri allowlist yang dibuat oleh pengguna (`AllowedIp`).
- `$user->resolvedTickets()`: Tiket permohonan buka blokir yang diselesaikan admin (`IpUnblockRequest`).

### 2. Daftarkan Middleware

#### Pada Laravel 11 / 12 / 13 (`bootstrap/app.php`)

```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Daftarkan penegakan WAF dan blokir IP secara global
        $middleware->append(\Internal\Pasak\Http\Middleware\BlockIpAddress::class);
        $middleware->append(\Internal\Pasak\Http\Middleware\DetectSecurityThreats::class);

        // Lacak aktivitas sesi pengguna di grup web
        $middleware->web(append: [
            \Internal\Pasak\Http\Middleware\TrackUserActivity::class,
        ]);

        // Alias middleware keamanan
        $middleware->alias([
            'pasak.block' => \Internal\Pasak\Http\Middleware\BlockIpAddress::class,
            'pasak.detect' => \Internal\Pasak\Http\Middleware\DetectSecurityThreats::class,
            'pasak.admin' => \Internal\Pasak\Http\Middleware\EnsureSecurityAdmin::class,
            'pasak.activity' => \Internal\Pasak\Http\Middleware\TrackUserActivity::class,
        ]);
    })
    ->create();
```

#### Pada Laravel 10 (`app/Http/Kernel.php`)

```php
protected $middleware = [
    // ...
    \Internal\Pasak\Http\Middleware\BlockIpAddress::class,
    \Internal\Pasak\Http\Middleware\DetectSecurityThreats::class,
];

protected $middlewareGroups = [
    'web' => [
        // ...
        \Internal\Pasak\Http\Middleware\TrackUserActivity::class,
    ],
];

protected $middlewareAliases = [
    'pasak.block' => \Internal\Pasak\Http\Middleware\BlockIpAddress::class,
    'pasak.detect' => \Internal\Pasak\Http\Middleware\DetectSecurityThreats::class,
    'pasak.admin' => \Internal\Pasak\Http\Middleware\EnsureSecurityAdmin::class,
    'pasak.activity' => \Internal\Pasak\Http\Middleware\TrackUserActivity::class,
];
```

---

## 📡 Headless REST API Reference

Semua rute REST API didaftarkan secara default dengan prefix `/api/pasak` (dapat diubah melalui `config('pasak.routes.prefix')`).

### 1. Endpoint Publik

| Metode | URI                                                  | Deskripsi                                                                                   |
| :----- | :--------------------------------------------------- | :------------------------------------------------------------------------------------------ |
| `POST` | `/api/pasak/unblock-tickets/submit`               | Mengirim permohonan banding pembukaan blokir IP/perangkat                                   |
| `GET`  | `/api/pasak/unblock-tickets/check/{ticketNumber}` | Memeriksa status tiket permohonan banding                                                   |

#### Contoh Payload Pengajuan Tiket:

```json
POST /api/pasak/unblock-tickets/submit
{
  "name": "Budi Santoso",
  "email": "budi@example.com",
  "phone": "081234567890",
  "reason": "Alamat IP kantor saya terblokir saat mengakses dashboard.",
  "device_id": "client-uuid-1234",
  "local_ip": "192.168.1.50"
}
```

### 2. Endpoint Pengguna Terautentikasi (`auth`)

| Metode | URI                                    | Deskripsi                                                   |
| :----- | :------------------------------------- | :---------------------------------------------------------- |
| `POST` | `/api/pasak/trusted-ips/save-my-ip` | Menyimpan alamat IP saat ini sebagai IP terpercaya pengguna |

### 3. Endpoint Manajemen Admin (`auth` + `pasak.admin`)

#### Log Keamanan & Analitik Serangan

| Metode   | URI                        | Deskripsi                                                                                     |
| :------- | :------------------------- | :-------------------------------------------------------------------------------------------- |
| `GET`    | `/api/pasak/logs`       | Mendapatkan log ancaman (paginasi, filter event/level/tanggal, statistik, tren 24 jam/7 hari) |
| `DELETE` | `/api/pasak/logs/clear` | Mengosongkan seluruh log audit keamanan                                                       |
| `DELETE` | `/api/pasak/logs/{id}`  | Menghapus satu entri log keamanan tertentu                                                    |

#### Daftar Blokir IP & Perangkat

| Metode   | URI                                     | Deskripsi                                                 |
| :------- | :-------------------------------------- | :-------------------------------------------------------- |
| `GET`    | `/api/pasak/blocked-ips`             | Mendapatkan daftar IP yang diblokir (aktif & kedaluwarsa) |
| `POST`   | `/api/pasak/blocked-ips`             | Memblokir IP atau perangkat secara manual                 |
| `GET`    | `/api/pasak/blocked-ips/{id}`        | Melihat detail data pemblokiran                           |
| `PATCH`  | `/api/pasak/blocked-ips/{id}/toggle` | Mengaktifkan / menonaktifkan status blokir                |
| `DELETE` | `/api/pasak/blocked-ips/{id}`        | Mencabut blokir dan menghapus entri                       |

#### Audit Server & Integritas Berkas

| Metode   | URI                                     | Deskripsi                                                      |
| :------- | :-------------------------------------- | :------------------------------------------------------------- |
| `GET`    | `/api/pasak/server`                  | Laporan komprehensif audit keamanan server & integritas berkas |
| `POST`   | `/api/pasak/server/baseline`         | Membuat baseline hash SHA-256 berkas aplikasi baru             |
| `DELETE` | `/api/pasak/server/baseline`         | Menghapus baseline integritas                                  |
| `DELETE` | `/api/pasak/server/suspicious-files` | Menghapus berkas mencurigakan/webshell yang terdeteksi         |
| `DELETE` | `/api/pasak/lockouts/{id}`           | Membuka kunci akun yang terkena lockout login berjenjang       |

#### Sesi Pengguna & IP Terpercaya

| Metode   | URI                                               | Deskripsi                                       |
| :------- | :------------------------------------------------ | :---------------------------------------------- |
| `GET`    | `/api/pasak/user-sessions`                     | Riwayat sesi login seluruh pengguna             |
| `GET`    | `/api/pasak/user-sessions/realtime`            | Daftar pengguna yang aktif secara real-time     |
| `DELETE` | `/api/pasak/user-sessions/{id}`                | Menghapus log sesi pengguna                     |
| `DELETE` | `/api/pasak/user-sessions/session/{sessionId}` | Memutus sesi pengguna tertentu (_force logout_) |
| `DELETE` | `/api/pasak/trusted-ips/{id}`                  | Menghapus IP dari daftar terpercaya             |

#### Pengelolaan Tiket Banding

| Metode   | URI                                          | Deskripsi                                               |
| :------- | :------------------------------------------- | :------------------------------------------------------ |
| `GET`    | `/api/pasak/unblock-tickets`              | Daftar seluruh tiket permohonan banding                 |
| `POST`   | `/api/pasak/unblock-tickets/{id}/respond` | Menyetujui atau menolak tiket (buka blokir & whitelist) |
| `DELETE` | `/api/pasak/unblock-tickets/{id}`         | Menghapus arsip tiket                                   |

---

## 🛡️ Validation Rules Bawaan

Paket menyediakan aturan validasi siap pakai untuk request form aplikasi Anda:

### 1. `SafeImageFile`

Memvalidasi unggahan gambar dan mencegah serangan _polyglot image_ (gambar JPEG/PNG sah yang diinjeksi kode `<?php`), ekstensi ganda berbahaya, dan SVG bereksekusi JavaScript/XSS:

```php
use Internal\Pasak\Rules\SafeImageFile;

$request->validate([
    'avatar' => ['required', 'file', new SafeImageFile(maxKilobytes: 2048)],
]);
```

### 2. `SafeAssetPath`

Memvalidasi string path aset gambar/ikon agar terbebas dari path traversal, probe direktori sensitif, dan file `.htaccess`:

```php
use Internal\Pasak\Rules\SafeAssetPath;

$request->validate([
    'icon_path' => ['required', 'string', new SafeAssetPath],
]);
```

---

## 💻 Perintah Artisan CLI

Paket ini menyertakan perintah Artisan lengkap untuk otomasi di server produksi:

### 1. Pemindaian Log Akses Web Server (`pasak:scan-logs`)

Memindai berkas log Apache atau Nginx, mendeteksi pola serangan, dan memblokir IP penyerang secara otomatis:

```bash
# Pratinjau temuan tanpa memodifikasi database (dry-run)
php artisan pasak:scan-logs --file=/var/log/nginx/access.log --dry-run

# Pindai, impor ke log keamanan, dan blokir IP penyerang zero-tolerance
php artisan pasak:scan-logs --file=/var/log/nginx/access.log --import --block
```

### 2. Manajemen Baseline Integritas Berkas (`pasak:baseline`)

```bash
# Menampilkan status verifikasi integritas berkas
php artisan pasak:baseline

# Membuat baseline baru
php artisan pasak:baseline --create

# Menghapus baseline
php artisan pasak:baseline --destroy
```

### 3. Membuka Blokir IP (`pasak:unblock-ip`)

```bash
php artisan pasak:unblock-ip 198.51.100.50
```

### 4. Pembersihan Log Kedaluwarsa (`pasak:prune-logs`)

```bash
# Menghapus log lebih tua dari durasi retensi terkonfigurasi (default: 90 hari)
php artisan pasak:prune-logs

# Menghapus log lebih tua dari 30 hari
php artisan pasak:prune-logs --days=30
```

### 5. Pembersihan Data Residu Pentest (`pasak:purge-injected-data`)

Mendeteksi dan menghapus payload injeksi sisa pengujian keamanan (seperti `{{7*7}}`, `.htaccess`, path traversal) dari tabel aplikasi:

```bash
# Mode simulasi (hanya mendeteksi data tercemar)
php artisan pasak:purge-injected-data

# Hapus data yang terindikasi
php artisan pasak:purge-injected-data --force
```

### 6. Pembaruan Otomatis & Sinkronisasi Komponen (`pasak:upgrade`)

Periksa rilis terbaru dan perbarui package sekaligus sinkronkan migrasi database, rute kustom, dan tampilan dashboard monitoring:

```bash
# Periksa apakah ada versi baru yang tersedia
php artisan pasak:upgrade --check

# Jalankan proses upgrade dan sinkronisasi otomatis
php artisan pasak:upgrade

# Sinkronkan aset lokal tanpa composer update
php artisan pasak:upgrade --no-composer --force
```

> **Notifikasi Otomatis**: Saat menjalankan `php artisan serve` atau `composer run dev`, sistem akan secara otomatis memeriksa versi terbaru dari Packagist/GitHub secara non-blocking dan menampilkan notifikasi di terminal konsol bila versi baru telah dirilis.

---

## 🌐 Konfigurasi Web Server Hardened

Paket ini menyertakan template konfigurasi hardened siap pakai untuk web server **Nginx** maupun **Apache / LiteSpeed / cPanel**.

### 1. Nginx Hardened WAF (`nginx.conf`)

Diterbitkan via `php artisan vendor:publish --tag=pasak-nginx`:

1. **Dua Zona Rate Limiting Terpisah**:
    - `auth_limit`: 5 request/menit (burst 5) untuk endpoint sensitif (`/login`, `/register`, `/forgot-password`, `/reset-password`, dll.).
    - `general_limit`: 30 request/detik (burst 50) untuk rute umum aplikasi.
2. **Proteksi Aset Statis Vite / Frontend**:
    - Direktori `/build/` dibebaskan dari rate-limiting agar chunk parallel JS tidak memicu HTTP 429 atau `NS_ERROR_CORRUPTED_CONTENT`.
3. **Strict Single-PHP Execution**:
    - **Hanya `/index.php`** yang boleh dieksekusi oleh PHP-FPM. Berkas skrip lain yang berada di direktori publik langsung ditolak dengan **HTTP 403**.
4. **Pencegahan Double Extension & Ekstensi Berbahaya**:
    - Menolak ekstensi ganda (`.php.jpg`, `.phtml.zip`, dll.).
5. **Sandboxing Direktori Storage / Upload**:
    - Folder `/storage/` dimatikan dari eksekusi PHP dengan header `X-Content-Type-Options: nosniff` dan CSP sandbox.
6. **Blokir Dotfiles & Berkas Backup**:
    - Menolak akses berkas `.env`, `.git`, `.htaccess`, `.sql`, `.bak`, dan `.log`.

### 2. Apache & LiteSpeed Hardened (`public/.htaccess`)

Diterapkan otomatis via `php artisan pasak:install` atau `php artisan vendor:publish --tag=pasak-htaccess`:

1. **Front Controller & Authorization Header**: Routing Laravel standar, pemeliharaan header `Authorization` dan `X-XSRF-Token`.
2. **Blokir Akses ke Dotfile (`<FilesMatch "^\.">`)**:
    - Menutup akses ke `.htaccess`, `.env`, `.git`, `.htpasswd` (kompatibel Apache 2.4+ `Require all denied` dan Apache 2.2 `Deny from all`).
3. **Blokir Serangan Ekstensi Ganda (Double Extension Webshell)**:
    - Menolak berkas berbahaya seperti `shell.php.jpg` atau trik null-byte `wne.php%00.jpg`:
    ```apache
    <FilesMatch "\.(php[0-9]?|phtml|pht|phar|phps|asp|aspx|ashx|asmx|jsp|jspx|cgi|pl|py|rb|sh|bash|exe|dll|bat|cmd|scr)\.[a-z0-9]+$">
        Require all denied
    </FilesMatch>
    ```
4. **Blokir Berkas Backup, Dump Database, dan Log Sensitif**:
    - Menutup berkas `.sql`, `.bak`, `.old`, `.orig`, `.save`, `.swp`, `.log`, `.ini`, `.conf`, `.yml`, `.yaml`.
5. **Matikan Directory Listing**:
    - `Options -Indexes` mencegah browser menampilkan daftar berkas di dalam folder publik/storage.

## 🧪 Menjalankan Pengujian (Testing)

Paket ini dilengkapi dengan pengujian menyeluruh menggunakan **Pest PHP** dan **Orchestra Testbench**:

```bash
./vendor/bin/pest
```

Hasil uji: **115 passed (478 assertions)** 100% Passed mencakup:

- `DetectorTuningTest`: Verifikasi akurasi pola deteksi dan ketahanan ReDoS.
- `InstantBlockTest`: Verifikasi zero-tolerance instant blocking pada percobaan pertama.
- `PolyglotImageTest`: Uji penolakan polyglot image ber-tag PHP dan SVG XSS.
- `SecurityAdminApiTest`: Pengujian lengkap otorisasi, mutasi data, dan respons JSON REST API.
- `DeviceLevelBlockingTest`: Uji isolasi perangkat pada IP publik bersama.
- `ServerSecurityTest`: Audit keamanan lingkungan, baseline SHA-256, dan webshell sanitizer.
- `AccessLogScanTest`: Uji parser streaming log akses web server.
- `PasakTest`: Uji ambang batas auto-blocking dan rotasi log.
- `NginxPublishTest`: Verifikasi publikasi konfigurasi hardened virtual host Nginx.
- `HtaccessPublishTest`: Verifikasi publikasi, penambahan aturan otomatis, dan pencadangan `.htaccess` Apache.

---

## ❤️ Dukungan (Support)

Jika paket ini bermanfaat untuk proyek Anda, Anda dapat mendukung pengembangan
berkelanjutannya melalui:

- 🇮🇩 **Saweria**: <https://saweria.co/robykartis>

Dukungan Anda sangat membantu agar paket ini tetap terawat, aman, dan terus
diperbarui. Terima kasih! 🙏

---

## 📄 Lisensi

Paket ini dilisensikan di bawah lisensi terbuka [MIT](LICENSE).
