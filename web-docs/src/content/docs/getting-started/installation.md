---
title: "Panduan Instalasi & Migrasi"
description: "Halaman ini memandu proses instalasi paket robyajo/pasak ke dalam aplikasi Laravel Anda."
sidebar:
  order: 2
---

Halaman ini memandu proses instalasi paket **`robyajo/pasak`** ke dalam aplikasi Laravel Anda.

> 🌟 **Nol Ketergantungan NPM (Zero NPM)**: Paket ini murni PHP Composer library standar seperti paket Spatie. Anda **tidak perlu** menginstal dependensi npm atau menjalankan build tools frontend.

---

## 1. Persyaratan Sistem

Pastikan lingkungan server Anda memenuhi spesifikasi minimum berikut:

- **PHP**: `^8.2`, `^8.3`, `^8.4`, atau `^8.5`
- **Laravel**: `^10.0`, `^11.0`, `^12.0`, atau `^13.0`
- **Ekstensi PHP**: `pdo`, `mbstring`, `json`, `filter`, `openssl`.

---

## 2. Pemasangan via Composer

### Menggunakan Packagist (Publik)

Jika paket sudah diterbitkan di Packagist:

```bash
composer require robyajo/pasak
```

### Menggunakan Repositori Lokal / Private Git (Monorepo atau Path)

Jika Anda menggunakan paket ini secara internal sebelum publikasi ke Packagist, tambahkan konfigurasi repositori pada berkas `composer.json` proyek Laravel Anda:

```json
"repositories": [
    {
        "type": "path",
        "url": "../pasak",
        "options": {
            "symlink": true
        }
    }
]
```

Kemudian pasang paket:

```bash
composer require robyajo/pasak:@dev
```

---

## 3. Publikasi Aset Otomatis (`pasak:install`)

Paket menyediakan perintah Artisan interaktif satu langkah untuk mempublikasikan seluruh berkas konfigurasi, migrasi, template Nginx, serta menerapkan aturan **Hardening Apache `.htaccess`**:

```bash
php artisan pasak:install
```

Perintah ini akan secara otomatis:

1. Mempublikasikan berkas konfigurasi `config/pasak.php`.
2. Mempublikasikan berkas migrasi database ke `database/migrations/`.
3. Mempublikasikan template virtual host `nginx.conf` di root proyek.
4. **Memperbarui berkas `public/.htaccess`**:
    - Jika berkas belum ada: membuat `public/.htaccess` baru dengan aturan rewrite standar Laravel + blok hardening keamanan.
    - Jika berkas sudah ada: membuat cadangan otomatis `public/.htaccess.backup-YYYYMMDD_HHMMSS` dan menyisipkan blok hardening keamanan di bagian bawah berkas tanpa merusak aturan rewrite kustom Anda.
    - Jika sudah memiliki aturan hardening: mendeteksi dan mempertahankan berkas yang sudah terlindungi.
5. **Menyematkan Variabel Lingkungan ke `.env` & `.env.example`**:
    - Menambahkan blok konfigurasi lengkap (`PASAK_*`) disertai dokumentasi penjelasan fungsi berbahasa Indonesia langsung di bagian bawah berkas `.env` dan `.env.example`.
    - Menggunakan deteksi cerdas agar tidak terjadi duplikasi jika variabel sudah pernah ditambahkan sebelumnya.
6. **Menyematkan Trait `HasSecurityRelations` ke Model User**:
    - Secara otomatis mendeteksi model `User` (`app/Models/User.php`) dan menambahkan import `use Internal\Pasak\Concerns\HasSecurityRelations;` serta menyematkan trait `HasSecurityRelations`.
    - Idempotent: tidak akan menduplikasi jika trait sudah ada.
7. **Mendaftarkan Middleware WAF ke Aplikasi Host**:
    - Otomatis mendaftarkan `BlockIpAddress` dan `DetectSecurityThreats` ke dalam `bootstrap/app.php` (Laravel 11 & 12) atau `app/Http/Kernel.php` (Laravel 10).
    - Idempotent: memeriksa keberadaan middleware terlebih dahulu sebelum mendaftarkan.
8. **Menyiapkan Dukungan API & Berkas Rute Kustom (`routes/`)**:
    - Memeriksa apakah berkas `routes/api.php` sudah tersedia. Jika belum (pada Laravel 11+), otomatis menjalankan `php artisan install:api` agar dukungan API aktif.
    - Menghasilkan berkas `routes/pasak-api.php` untuk endpoint headless REST API dan menyertakannya di `routes/api.php` (`require __DIR__.'/pasak-api.php';`).
    - Menghasilkan berkas `routes/pasak.php` untuk rute web dashboard Blade murni dan menyertakannya di `routes/web.php` (`require __DIR__.'/pasak.php';`). Dilewati untuk `--api-only` (tanpa dashboard).
    - Memberikan kebebasan penuh kepada developer untuk mengkustomisasi prefix URL, middleware, otorisasi, dan tampilan tanpa terkunci pada rute internal paket.

### Opsi Perintah:

| Opsi                 | Fungsi                                                                                                                    |
| :------------------- | :------------------------------------------------------------------------------------------------------------------------ |
| `--force`            | Menimpa seluruh berkas konfigurasi, migrasi, `nginx.conf`, dan `public/.htaccess` dengan template bawaan paket.           |
| `--with-dashboard` / `--with-plain` / `--with-blade` | Mempublikasikan dashboard Blade murni — jalan di semua aplikasi Laravel, tanpa dependensi frontend. |
| `--api-only`         | Jalan pintas: API ya, Web tidak (inti + keamanan saja, tanpa halaman/dashboard).                  |
| `--without-api`      | Melewatkan pemasangan dukungan API (tanpa rute API).                                               |
| `--without-web`      | Melewatkan pemasangan dashboard web (tanpa halaman/dashboard).                                     |
| `--lang=...`         | Bahasa wizard instalasi (`id` default, `en`).                                                                             |
| `--without-user-trait` | Melewatkan penyematan otomatis trait `HasSecurityRelations` ke model User.                                               |
| `--without-middleware` | Melewatkan pendaftaran otomatis middleware WAF di `bootstrap/app.php` / `Kernel.php`.                                    |
| `--without-routes`   | Melewatkan pembuatan berkas rute `routes/pasak.php` dan `routes/pasak-api.php`.                                     |
| `--without-api`      | Melewatkan pemeriksaan dan instalasi rute API (`install:api`).                                                            |
| `--without-nginx`    | Melewatkan publikasi berkas `nginx.conf` jika server Anda tidak menggunakan web server Nginx.                             |
| `--without-htaccess` | Melewatkan pembaruan berkas `public/.htaccess` jika Anda menggunakan Nginx murni dan tidak memerlukan Apache `.htaccess`. |
| `--with-htaccess`    | Memaksa pembaruan berkas `public/.htaccess` dengan aturan hardening keamanan paket.                                       |
| `--without-env`      | Melewatkan penyematan variabel konfigurasi ke berkas `.env` dan `.env.example`.                                           |

---

## 4. Publikasi Aset Manual via Vendor Publish

Jika Anda ingin mempublikasikan aset secara bertahap atau terpisah:

### 1. Publikasikan Konfigurasi Saja

```bash
php artisan vendor:publish --tag=pasak-config
```

Berkas akan ditempatkan di: `config/pasak.php`.

### 2. Publikasikan Migrasi Saja

```bash
php artisan vendor:publish --tag=pasak-migrations
```

Berkas migrasi akan disalin ke folder `database/migrations/`.

### 3. Publikasikan Berkas Rute Kustom

```bash
# Publikasikan rute web dan API sekaligus:
php artisan vendor:publish --tag=pasak-routes

# Atau publikasikan terpisah:
php artisan vendor:publish --tag=pasak-routes-web
php artisan vendor:publish --tag=pasak-routes-api
```

Berkas akan ditempatkan di:
- `routes/pasak.php` (rute web dashboard yang langsung me-render view).
- `routes/pasak-api.php` (rute headless REST API).

### 4. Publikasikan Template Nginx WAF Hardened Saja

```bash
php artisan vendor:publish --tag=pasak-nginx
```

Berkas akan ditempatkan di root proyek: `nginx.conf`.

### 4. Publikasikan Template Apache `.htaccess` Hardened Saja

```bash
php artisan vendor:publish --tag=pasak-htaccess --force
```

Berkas akan ditempatkan di: `public/.htaccess`.

### 5. Publikasikan Seluruh Aset Sekaligus

```bash
php artisan vendor:publish --tag=pasak-all --force
```

### 6. Publikasikan Dashboard Monitoring Blade Murni

```bash
php artisan vendor:publish --tag=plain-views
```

Perintah ini menyalin tujuh halaman Blade ke `resources/views/pages/pasak/` (CSS/JS mandiri, tanpa build step dan tanpa dependensi frontend apa pun) beserta konfigurasi `dashboard` pada `config/pasak.php`. Dashboard diakses di prefix `/pasak` dan **wajib login** (lihat bagian [Dashboard Monitoring](#8-dashboard-monitoring-opsional).

---

## 5. Menjalankan Migrasi Database

Jalankan perintah migrasi Laravel untuk membuat tabel-tabel pendukung paket:

```bash
php artisan migrate
```

Tabel-tabel yang dibuat secara default (satu migrasi `create_pasak_tables`):

1. `blocked_ips`: Menyimpan daftar karantina IP dan perangkat aktif/kedaluwarsa beserta hit counter.
2. `pasak_logs`: Log audit ancaman keamanan, pola yang terdeteksi, bukti payload, dan aksi mitigasi.
3. `login_attempts`: Pelacakan kegagalan login bertingkat (_stepped lockout_) per kombinasi email & IP.
4. `ip_unblock_requests`: Tiket banding pembukaan blokir yang diajukan oleh pengguna publik.
5. `user_logins`: Rekam jejak riwayat login pengguna, detail perangkat/browser, sesi aktif, dan waktu aktivitas terakhir.
6. `trusted_ips`: Daftar alamat IP terpercaya per pengguna untuk otorisasi akses khusus.
7. `settings`: Penyimpanan key-value pengaturan dinamis paket.
8. `allowed_ips`: Allowlist global (IP/wildcard/CIDR) yang tidak pernah diblokir.

> Data dari tabel lama `security_logs` / `security_settings` otomatis dipindahkan bila migrasi menemukan keduanya.

---

## 6. Mendaftarkan Trait ke Model User (Standar Spatie)

Buka model pengguna aplikasi Anda (biasanya `app/Models/User.php`) dan sertakan trait `HasSecurityRelations`:

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Internal\Pasak\Concerns\HasSecurityRelations;

class User extends Authenticatable
{
    use HasSecurityRelations;

    // ... sisa model Anda
}
```

Metode relasi Eloquent yang otomatis tersedia:

- `$user->logins()`: Mengambil seluruh riwayat login (`HasMany` ke `UserLogin`).
- `$user->trustedIps()`: Mengambil daftar IP terpercaya milik pengguna (`HasMany` ke `TrustedIp`).
- `$user->securityLogs()`: Mengambil audit ancaman yang diasosiasikan dengan akun ini (`HasMany` ke `SecurityLog`).
- `$user->blockedIps()`: Mengambil daftar entri blokir yang dieksekusi oleh user admin ini (`HasMany` ke `BlockedIp`).
- `$user->resolvedTickets()`: Mengambil daftar tiket banding yang diselesaikan oleh user admin ini (`HasMany` ke `IpUnblockRequest`).

---

## 7. Mendaftarkan Middleware Keamanan ke Core Laravel

Paket menyediakan 4 middleware terisolasi:

| Alias               | Kelas Middleware                                                 | Peran                                                                                                       |
| :------------------ | :--------------------------------------------------------------- | :---------------------------------------------------------------------------------------------------------- |
| `pasak.block`    | `Internal\Pasak\Http\Middleware\BlockIpAddress`        | Menolak request dari IP/perangkat yang sedang terblokir aktif (HTTP 403).                                   |
| `pasak.detect`   | `Internal\Pasak\Http\Middleware\DetectSecurityThreats` | Menginspeksi payload request terhadap tanda-tanda serangan siber dan mengaktifkan auto-block/instant block. |
| `pasak.admin`    | `Internal\Pasak\Http\Middleware\EnsureSecurityAdmin`   | Memastikan hanya user dengan wewenang admin yang dapat mengakses REST API admin.                            |
| `pasak.activity` | `Internal\Pasak\Http\Middleware\TrackUserActivity`     | Memperbarui heartbeat aktivitas login pengguna di database (ter-throttle 45 detik).                         |

### Pendaftaran pada Laravel 11 / 12 / 13 (`bootstrap/app.php`)

```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Internal\Pasak\Http\Middleware\BlockIpAddress;
use Internal\Pasak\Http\Middleware\DetectSecurityThreats;
use Internal\Pasak\Http\Middleware\TrackUserActivity;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Pasang di lapisan global
        $middleware->append(BlockIpAddress::class);
        $middleware->append(DetectSecurityThreats::class);

        // Pasang di grup web untuk pelacakan aktivitas user login
        $middleware->web(append: [
            TrackUserActivity::class,
        ]);
    })
    ->create();
```

### Pendaftaran pada Laravel 10 (`app/Http/Kernel.php`)

Buka `app/Http/Kernel.php` dan tambahkan ke array `$middleware`:

```php
protected $middleware = [
    // ...
    \Internal\Pasak\Http\Middleware\BlockIpAddress::class,
    \Internal\Pasak\Http\Middleware\DetectSecurityThreats::class,
];
```

> **Tips Opsi Otomatis (Zero-Touch)**: Jika Anda mengatur `SECURITY_AUTO_REGISTER_MIDDLEWARE=true` di berkas `.env`, paket akan otomatis menyuntikkan `BlockIpAddress` dan `DetectSecurityThreats` ke Kernel HTTP aplikasi secara otomatis saat booting.

---

## 8. Dashboard Monitoring (Opsional)

Paket tetap 100% headless secara default. Bila membutuhkan panel monitoring siap pakai, pasang dashboard Blade murni:

```bash
php artisan pasak:install --with-dashboard
# atau: php artisan vendor:publish --tag=plain-views
```

Halaman tersimpan di `resources/views/pages/pasak/` dan dirender controller bawaan paket — cocok untuk semua aplikasi Laravel tanpa dependensi frontend.

### Mengaktifkan

```bash
# Aktifkan di .env
#    PASAK_DASHBOARD_ENABLED=true
#    PASAK_DASHBOARD_PREFIX=pasak

# Bersihkan cache
php artisan optimize:clear
```

Panel tersedia di `/pasak` dan berisi tujuh modul: **Overview**, **Security Logs**, **Blocked IPs**, **Server Audit**, **User Sessions**, dan **Unblock Appeals**, dan **Settings**.

### Keamanan & Kontrol Akses

- Rute hanya didaftarkan ketika `pasak.dashboard.enabled=true`.
- Seluruh rute memakai middleware `web` + `auth` (wajib login) dan, secara bawaan, `pasak.admin` (Gate `manage-pasak`).
- Kustomisasi melalui `config/pasak.php`:
    ```php
    'dashboard' => [
        'enabled' => (bool) env('PASAK_DASHBOARD_ENABLED', false),
        'prefix' => env('PASAK_DASHBOARD_PREFIX', 'pasak'),
        'middleware' => ['web', 'auth'],
        'admin_middleware' => ['Internal\\Pasak\\Http\\Middleware\\EnsureSecurityAdmin'],
    ],
    ```
