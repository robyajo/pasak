---
title: "Integrasi Frontend Blade"
description: "Jika aplikasi host Anda menggunakan template berbasis Blade, integrasi dengan robyajo/pasak sangat cepat dan intuitif — dashboard paket adalah Blade murni..."
sidebar:
  order: 2
---

Jika aplikasi host Anda menggunakan template berbasis Blade, integrasi dengan **`robyajo/pasak`** sangat cepat dan intuitif — dashboard paket adalah Blade murni (100% Pure PHP) sehingga jalan di semua aplikasi Laravel tanpa Livewire/Inertia/Vue.

---

## 1. Menyesuaikan Halaman Tampilan Blokir (`errors.blocked`)

Jika request bukan panggilan JSON API, middleware `BlockIpAddress` secara otomatis memeriksa apakah berkas view Blade `errors.blocked` tersedia di aplikasi Anda (`resources/views/errors/blocked.blade.php`).

Jika ada, paket akan me-render view tersebut dengan meneruskan variabel:

- `$ip`: Alamat IP klien yang diblokir.
- `$deviceId`: Fingerprint perangkat klien (jika ada).
- `$localIp`: IP lokal klien.
- `$reason`: Alasan pemblokiran (nama aturan atau catatan admin).
- `$message`: Pesan deskriptif.
- `$referenceId`: ID referensi unik (misal: `SEC-9A8B7C6D`).
- `$supportEmail`: Alamat email bantuan dari konfigurasi.

### Contoh Template Blade Cantik: `resources/views/errors/blocked.blade.php`

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Akses Ditolak | Sistem Keamanan</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; max-width: 520px; width: 100%; padding: 32px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
        .badge { display: inline-block; background: #ef4444; color: #fff; font-size: 12px; font-weight: bold; padding: 4px 10px; border-radius: 9999px; text-transform: uppercase; margin-bottom: 16px; }
        h1 { font-size: 22px; margin: 0 0 12px 0; color: #f1f5f9; }
        p { font-size: 14px; line-height: 1.6; color: #94a3b8; margin: 0 0 20px 0; }
        .meta-box { background: #0f172a; border-radius: 8px; padding: 16px; margin-bottom: 24px; font-family: monospace; font-size: 13px; }
        .meta-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .meta-row:last-child { margin-bottom: 0; }
        .meta-label { color: #64748b; }
        .meta-value { color: #38bdf8; font-weight: bold; }
        .btn { display: block; width: 100%; text-align: center; background: #2563eb; color: #fff; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: 600; font-size: 14px; transition: background 0.2s; }
        .btn:hover { background: #1d4ed8; }
        .footer { font-size: 12px; text-align: center; color: #64748b; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">Akses Ditolak (403)</span>
        <h1>Perlindungan Keamanan Aktif</h1>
        <p>{{ $message }}</p>

        <div class="meta-box">
            <div class="meta-row">
                <span class="meta-label">ID Referensi:</span>
                <span class="meta-value">{{ $referenceId }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Alamat IP:</span>
                <span class="meta-value">{{ $ip }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Indikasi:</span>
                <span class="meta-value" style="color: #f87171">{{ $reason }}</span>
            </div>
        </div>

        <a href="mailto:{{ $supportEmail }}?subject=Buka%20Blokir%20{{ $referenceId }}" class="btn">
            Hubungi Tim Keamanan IT
        </a>

        <div class="footer">
            Pusat Keamanan Siber &bullet; {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
```

---

## 2. Dashboard Monitoring Blade Murni Siap Pakai

Anda tidak perlu membangun panel monitoring dari nol. Paket menyediakan halaman Blade siap pakai berbasis **Pure Vanilla CSS** yang dapat dipublikasikan dengan satu perintah:

```bash
php artisan pasak:install --with-dashboard
# atau: php artisan vendor:publish --tag=plain-views
```

Perintah tersebut menyalin berkas berikut ke aplikasi Anda:

| Berkas yang Dipublikasikan                             | Deskripsi                                                   |
| :----------------------------------------------------- | :---------------------------------------------------------- |
| `resources/views/pages/pasak/overview.blade.php`    | Statistik, tren serangan, dan kartu top attacker 24 jam real-time. |
| `resources/views/pages/pasak/threat-map.blade.php`  | Peta geografis lintasan serangan dari asal penyerang menuju server (live globe). |
| `resources/views/pages/pasak/logs.blade.php`        | Data table log keamanan dengan filter rentang tanggal, pencarian, dan per-page. |
| `resources/views/pages/pasak/log-detail.blade.php`  | Detail satu entri log: evidence, konteks request, dan log terkait. |
| `resources/views/pages/pasak/blocked-ips.blade.php` | Data table karantina IP dengan filter tanggal, toggle status, dan buka blokir. |
| `resources/views/pages/pasak/blocked-ip-detail.blade.php` | Detail satu blokir: alasan, log pemicu, dan tiket banding terkait. |
| `resources/views/pages/pasak/allowed-ips.blade.php` | Data table daftar IP yang diizinkan (allowlist) dengan filter tanggal dan per-page. |
| `resources/views/pages/pasak/server.blade.php`      | Audit integritas, pemindai webshell, baseline SHA-256, dan status sistem. |
| `resources/views/pages/pasak/sessions.blade.php`    | Data table sesi pengguna aktif, riwayat login, dan IP terpercaya. |
| `resources/views/pages/pasak/tickets.blade.php`     | Data table review permohonan tiket banding buka blokir. |
| `resources/views/pages/pasak/ticket-detail.blade.php` | Detail satu tiket: alasan pemohon, blokir terkait, dan formulir respon. |
| `resources/views/pages/pasak/settings.blade.php`    | Pengaturan WAF: ambang per tingkat, lingkup adaptif, lockout login, dan simulasi live. |
| `resources/views/pages/pasak/pagination.blade.php`  | Komponen pagination terpadu: ringkasan entri dan tombol navigasi halaman. |
| `resources/views/pages/pasak/layout.blade.php`      | Sub-layout navigasi, tema (Dark Mode), dan styling custom properties. |

Setelah dipublikasikan, aktifkan dashboard melalui `.env`:

```dotenv
PASAK_DASHBOARD_ENABLED=true
PASAK_DASHBOARD_PREFIX=pasak
```

Panel dapat diakses pada `/pasak`. Halaman-halaman tersebut dirender controller bawaan paket sebagai Blade biasa.

---

### 3. Fitur Unggulan Dashboard Blade

#### A. Data Table Interaktif (Filter Tanggal, Per-Page, & Pagination)

Seluruh tabel data utama (`logs`, `blocked-ips`, `allowed-ips`, `sessions`, `tickets`) dilengkapi antarmuka Data Table modern:

- **Filter Rentang Tanggal (`date_from` & `date_to`)**: Menyaring data berdasarkan tanggal kejadian dengan input tanggal visual dan tombol **Reset** untuk membersihkan saringan secara instan.
- **Selector Entri Per Halaman**: Pilihan `10`, `25`, `50`, atau `100` entri per halaman dengan persistensi parameter URL otomatis saat berpindah halaman.
- **Komponen Pagination Terpadu (`pagination.blade.php`)**: Menampilkan ringkasan informatif (*"Showing X to Y of Z entries"*) beserta tombol navigasi halaman responsif (*Previous*, nomor halaman berjenjang, dan *Next*).

#### B. Threat Map — Peta Live Penyerang

Halaman **Threat Map** (`/pasak/threat-map`) menampilkan bola dunia 3D interaktif yang memetakan lintasan serangan dari koordinat asal penyerang menuju server:

- **Data Nyata & Warna Tingkat Ancaman**: Lintasan panah diambil dari `pasak_logs` 24 jam terakhir dengan warna sesuai tingkat bahaya (`critical` merah, `high` oranye, `medium` kuning, `low` hijau).
- **Streaming Serangan Baru (`?after=`)**: Polling otomatis tiap 8 detik memanfaatkan kursor id terakhir sehingga hanya serangan baru yang digambar di globe tanpa memuat ulang log arsip lama.
- **Mode Layar Penuh (Full Screen Mode)**: Tombol `⛶ Full screen` memperluas kanvas peta memenuhi layar secara fluid (`position: fixed; inset: 0`), dan tombol `Escape` mengembalikannya ke tampilan standar.
- **Tata Letak Responsif Seluler**: Pada layar ponsel/tablet, globe tetap tampil dominan di bagian atas dan metrik serangan ditata rapi dalam grid kartu.
- **Pemberitahuan Anggun (Graceful Fallback)**: Jika CDN visualisasi tidak dapat dijangkau, antarmuka menyediakan kartu pemberitahuan yang informatif tanpa merusak panel log.

#### C. Pengaturan Dinamis & Simulasi Live

Halaman **Pengaturan** (`/pasak/settings`) memungkinkan pengelola menyetel:
- Ambang batas auto-block per tingkat ancaman (`critical`, `high`, `medium`, `low`).
- Lingkup pemblokiran: `device` (hanya perangkat pelanggar), `ip` (seluruh IP), atau `auto` (adaptif, aman untuk Wi-Fi kantor bersama).
- Durasi pemblokiran bertingkat (6 jam hingga permanen).
- Ambang batas proteksi login bertingkat (stepped lockout) dan masa simpan log.
- **Kotak Simulasi Live**: Menguji skenario serangan secara interaktif untuk melihat apakah tindakan blokir akan terpicu berdasarkan pengaturan yang sedang aktif.

---

### 4. Desain Sistem Pure Vanilla CSS (Zero External UI Dependencies)

Stubs Blade dibangun menggunakan **Pure Vanilla CSS**:
- **Tanpa dependensi frontend apa pun** (tanpa NPM, Node.js, Vite, Livewire, Inertia, Vue, ataupun UI kit eksternal).
- Menanamkan CSS custom properties terisolasi (`--sec-*`) di dalam sub-layout (`layout.blade.php`) sehingga konsisten, ringan, dan kompatibel dengan tata letak apa pun.
- Dilengkapi dukungan **Dark Mode** otomatis via media query `@media (prefers-color-scheme: dark)` dan kelas `.dark`, plus tombol toggle manual (Auto → Terang → Gelap) yang tersimpan di `localStorage`.
- Sepenuhnya **responsif** dengan tabel scroll horizontal di layar sempit/mobile serta grid fleksibel.

---

### 5. Menambahkan Tautan di Sidebar

Untuk memunculkan tautan ke panel, tambahkan item berikut pada navigasi sidebar aplikasi Anda:

```blade
<flux:sidebar.item icon="shield-check" :href="route('pasak.dashboard.overview')" :current="request()->routeIs('pasak.dashboard.*')" wire:navigate>
    {{ __('Security Monitor') }}
</flux:sidebar.item>
```

Atau jika menggunakan link HTML standar:

```blade
<a href="{{ route('pasak.dashboard.overview') }}" wire:navigate class="nav-item">
    Security Monitor
</a>
```
