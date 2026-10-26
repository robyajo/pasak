---
title: "Panduan Kustomisasi Aman (Blade & REST API) Tanpa Mengubah Vendor"
description: "Paket robyajo/pasak dirancang mengikuti filosofi Spatie-Standard & Headless Architecture. Artinya, setelah paket dipasang, Anda memiliki kendali 100%..."
sidebar:
  order: 4
---

Paket **`robyajo/pasak`** dirancang mengikuti filosofi **Spatie-Standard & Headless Architecture**. Artinya, setelah paket dipasang, Anda memiliki kendali 100% penuh untuk membuat tampilan monitoring kustom, alur kerja keamanan sendiri, maupun antarmuka REST API JSON tanpa pernah menyentuh atau merusak berkas inti paket.

Dokumen ini menjelaskan batas arsitektur antara **`vendor/`** dan **Aplikasi Host Anda**, serta panduan langkah-demi-langkah membangun dashboard Blade dan endpoint API kustom secara aman.

---

## 1. Aturan Emas: Kebijakan Dilarang Mengubah Vendor (*Zero-Vendor-Edit Policy*)

> ⚠️ **PERINGATAN KRUSIAL**:
> **JANGAN PERNAH** memodifikasi, mengedit, atau menghapus berkas apapun di dalam folder `vendor/robyajo/pasak/`.
> Segala perubahan di dalam folder `vendor/` bersifat **sementara dan berbahaya**!

### Mengapa Dilarang Mengubah Folder `vendor/`?
1. **Pembaruan Composer Menimpa Perubahan**: Setiap kali Anda menjalankan `composer update` atau men-deploy proyek ke server produksi via CI/CD, Composer akan menghapus dan mengunduh ulang folder `vendor/`. Seluruh modifikasi kustom yang Anda tulis di sana akan **hilang permanen**.
2. **Integritas Deteksi & ReDoS Immunity**: Berkas regex WAF di `src/Services/PasakService.php` telah dirancang secara matematis untuk kebal terhadap serangan *Regular Expression Denial of Service* (ReDoS). Mengubah pola regex langsung di vendor dapat membuat server Anda lumpuh akibat *catastrophic backtracking*.
3. **Integritas Signature & Baseline**: Modul server audit Pasak memeriksa SHA-256 dan integritas pustaka. Modifikasi internal akan memicu peringatan manipulasi berkas sistem.

---

## 2. Memahami Batas Wilayah: Apa yang Ada di `vendor/` vs Aplikasi Anda

Ketika Anda menjalankan:
```bash
composer require robyajo/pasak
php artisan pasak:install
```

Berikut adalah pemisahan peran yang tegas antara pustaka inti dan aplikasi Anda:

```text
PROYEK LARAVEL ANDA
├── app/
│   └── Http/Controllers/
│       ├── KeamananController.php          <-- [ZONA ANDA] Bebas buat controller Blade kustom
│       └── Api/KeamananController.php      <-- [ZONA ANDA] Bebas buat REST API JSON kustom
├── config/
│   └── pasak.php                           <-- [ZONA ANDA] Konfigurasi aman diubah
├── resources/views/
│   ├── layouts/app.blade.php               <-- [ZONA ANDA] Template induk aplikasi Anda
│   ├── keamanan/index.blade.php            <-- [ZONA ANDA] Tampilan kustom buatan Anda
│   └── pages/pasak/                        <-- [ZONA ANDA] Template bawaan (bisa diubah atau diabaikan)
├── routes/
│   ├── web.php                             <-- [ZONA ANDA] Rute web kustom
│   ├── api.php                             <-- [ZONA ANDA] Rute API kustom
│   ├── pasak.php                           <-- [ZONA ANDA] Rute bawaan paket (bisa disesuaikan)
│   └── pasak-api.php                       <-- [ZONA ANDA] Rute API bawaan paket
│
└── vendor/robyajo/pasak/                   <-- [ZONA DILINDUNGI / READ-ONLY]
    ├── src/                                    (Engine WAF, Service, Middleware, Model)
    │   ├── Facades/Pasak.php                   (Facade resmi untuk dipanggil aplikasi Anda)
    │   ├── Services/PasakService.php           (Mesin deteksi ancaman & inspeksi payload)
    │   ├── Http/Middleware/                    (Middleware pemblokir & penganalisis ancaman)
    │   └── Models/                             (Model Eloquent: PasakLog, PasakBlockedIp)
    ├── database/migrations/                    (Definisi skema tabel migrasi)
    └── stubs/                                  (Template publikasi)
```

- **`vendor/robyajo/pasak` (Zona Mesin / Engine)**: Layak dilihat hanya untuk referensi audit (*read-only*). Berisi logika inti WAF, parser header reverse-proxy, dan filter keamanan tingkat rendah.
- **Aplikasi Host Anda (Zona Pengembang)**: Semua penyesuaian tampilan, rute, otorisasi, dan integrasi UI dilakukan di sini.

---

## 3. Cara Membangun Tampilan Custom (Blade Dashboard Kustom)

Jika Anda tidak menyukai tampilan bawaan di `resources/views/pages/pasak/` dan ingin monitoring WAF tampil selaras dengan template/tema aplikasi Anda (misal `@extends('layouts.app')`):

### Langkah 1: Buat Controller Kustom di Host App
Jalankan perintah Artisan:
```bash
php artisan make:controller KeamananController
```

Isi berkas `app/Http/Controllers/KeamananController.php` dengan memanfaatkan Facade `Pasak` dan Model resmi paket:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Internal\Pasak\Facades\Pasak;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakLog;

class KeamananController extends Controller
{
    /**
     * Tampilkan halaman dasbor keamanan kustom.
     */
    public function index(Request $request): View
    {
        // 1. Ambil statistik ringkas dari Facade Pasak
        $stats = Pasak::stats();

        // 2. Ambil ringkasan tingkat keparahan ancaman 24 jam terakhir
        $levelBreakdown = Pasak::levelBreakdown(24);

        // 3. Ambil daftar IP paling sering menyerang
        $topAttackers = Pasak::topAttackers(5);

        // 4. Ambil daftar IP yang sedang diblokir aktif
        $blockedIps = PasakBlockedIp::query()
            ->active()
            ->latest('id')
            ->get();

        // 5. Ambil riwayat log ancaman dengan filter pencarian dan level
        $logs = PasakLog::query()
            ->when($request->filled('level'), function ($query) use ($request) {
                $query->where('threat_level', $request->query('level'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->query('search');
                $query->where(function ($sub) use ($search) {
                    $sub->where('ip_address', 'like', "%{$search}%")
                        ->orWhere('path', 'like', "%{$search}%")
                        ->orWhere('rule_label', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('keamanan.index', [
            'stats' => $stats,
            'levelBreakdown' => $levelBreakdown,
            'topAttackers' => $topAttackers,
            'blockedIps' => $blockedIps,
            'logs' => $logs,
            'wafEnabled' => Pasak::isEnabled(),
            'enforcementEnabled' => Pasak::isBlockEnforcementEnabled(),
            'filterLevel' => $request->query('level', ''),
            'filterSearch' => $request->query('search', ''),
        ]);
    }

    /**
     * Blokir IP address secara manual via Facade Pasak.
     */
    public function blockIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip' => ['required', 'ip'],
            'reason' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        Pasak::blockIp(
            ip: $validated['ip'],
            reason: $validated['reason'] ?: 'Diblokir manual dari Dashboard Keamanan Kustom',
            durationHours: ! empty($validated['hours']) ? (int) $validated['hours'] : null,
            source: 'manual',
            userId: $request->user()?->id ? (int) $request->user()->id : null
        );

        return redirect()
            ->route('keamanan.index')
            ->with('status', "IP {$validated['ip']} berhasil diblokir.");
    }

    /**
     * Buka blokir IP address via Facade Pasak.
     */
    public function unblockIp(string $ip): RedirectResponse
    {
        Pasak::unblockIp($ip);

        return redirect()
            ->route('keamanan.index')
            ->with('status', "Blokir untuk IP {$ip} berhasil dibuka.");
    }

    /**
     * Bersihkan riwayat log ancaman.
     */
    public function clearLogs(): RedirectResponse
    {
        PasakLog::truncate();

        return redirect()
            ->route('keamanan.index')
            ->with('status', 'Semua riwayat log ancaman WAF berhasil dibersihkan.');
    }
}
```

### Langkah 2: Buat View Blade Kustom
Buat berkas `resources/views/keamanan/index.blade.php`:

```blade
@extends('layouts.app')
@section('title', 'Pusat Keamanan')

@section('content')
<div class="container">
    <h1>🛡️ Pusat Keamanan Sistem</h1>

    {{-- Kartu Ringkasan --}}
    <div class="grid">
        <div class="card">
            <h3>{{ number_format($stats['total_logs'] ?? 0) }}</h3>
            <p>Total Serangan Terdeteksi</p>
        </div>
        <div class="card">
            <h3>{{ number_format($stats['logs_today'] ?? 0) }}</h3>
            <p>Serangan Hari Ini</p>
        </div>
        <div class="card">
            <h3>{{ number_format($stats['active_blocks'] ?? 0) }}</h3>
            <p>IP Diblokir Aktif</p>
        </div>
    </div>

    {{-- Form Blokir Cepat --}}
    <div class="card">
        <h3>🚫 Blokir IP Penyerang</h3>
        <form method="POST" action="{{ route('keamanan.block') }}">
            @csrf
            <input type="text" name="ip" placeholder="Masukkan IP (mis. 198.51.100.1)" required>
            <input type="text" name="reason" placeholder="Alasan pemblokiran">
            <input type="number" name="hours" placeholder="Durasi jam (kosong = permanen)">
            <button type="submit" class="btn danger">+ Blokir IP</button>
        </form>
    </div>

    {{-- Tabel Log Ancaman --}}
    <div class="card">
        <h3>📜 Riwayat Deteksi WAF</h3>
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Tingkat</th>
                    <th>IP</th>
                    <th>Path / URL</th>
                    <th>Aturan</th>
                    <th>Respon</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->diffForHumans() }}</td>
                    <td><span class="badge {{ $log->threat_level }}">{{ $log->threat_level }}</span></td>
                    <td><code>{{ $log->ip_address }}</code></td>
                    <td>{{ $log->path }}</td>
                    <td><b>{{ $log->rule_label }}</b></td>
                    <td>{{ $log->was_blocked ? '403 Blocked' : 'Logged' }}</td>
                </tr>
                @empty
                <tr><td colspan="6">Tidak ada serangan terdeteksi.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $logs->links() }}
    </div>
</div>
@endsection
```

### Langkah 3: Daftarkan Rute di `routes/web.php`
```php
use App\Http\Controllers\KeamananController;

Route::middleware('auth')->prefix('keamanan')->name('keamanan.')->group(function () {
    Route::get('/', [KeamananController::class, 'index'])->name('index');
    Route::post('/block', [KeamananController::class, 'blockIp'])->name('block');
    Route::post('/unblock/{ip}', [KeamananController::class, 'unblockIp'])->name('unblock');
    Route::delete('/logs', [KeamananController::class, 'clearLogs'])->name('logs.clear');
});
```

---

## 4. Cara Membangun REST API JSON Kustom (Headless)

Jika Anda ingin mengonsumsi data keamanan via API JSON (untuk mobile app, SPA Next.js/Vue, atau microservice audit):

### Langkah 1: Buat Controller API
```bash
php artisan make:controller Api/KeamananController
```

Isi berkas `app/Http/Controllers/Api/KeamananController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Internal\Pasak\Facades\Pasak;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakLog;

class KeamananController extends Controller
{
    /**
     * Ringkasan status WAF & statistik ancaman (JSON).
     * GET /api/keamanan
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Ringkasan status keamanan berhasil diambil.',
            'data' => [
                'waf' => [
                    'enabled' => Pasak::isEnabled(),
                    'block_enforcement' => Pasak::isBlockEnforcementEnabled(),
                ],
                'stats' => Pasak::stats(),
                'level_breakdown' => Pasak::levelBreakdown(24),
                'top_attackers' => Pasak::topAttackers(5),
            ],
        ]);
    }

    /**
     * Riwayat log ancaman terpaginasi dengan filter (JSON).
     * GET /api/keamanan/logs?level=critical&search=union
     */
    public function logs(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $logs = PasakLog::query()
            ->when($request->filled('level'), fn ($q) => $q->where('threat_level', $request->query('level')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->where(fn ($sub) => $sub->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('rule_label', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Daftar IP yang sedang diblokir aktif (JSON).
     * GET /api/keamanan/blocked-ips
     */
    public function blockedIps(): JsonResponse
    {
        $items = PasakBlockedIp::query()->active()->latest('id')->get();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    /**
     * Periksa apakah suatu IP sedang diblokir (JSON).
     * GET /api/keamanan/check/{ip}
     */
    public function checkIp(string $ip): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'ip_address' => $ip,
                'is_blocked' => Pasak::isBlocked($ip),
            ],
        ]);
    }

    /**
     * Blokir IP address secara manual (JSON).
     * POST /api/keamanan/block
     */
    public function blockIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => ['required', 'ip'],
            'reason' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:1'],
        ]);

        Pasak::blockIp(
            ip: $validated['ip'],
            reason: $validated['reason'] ?? 'Diblokir manual via API Keamanan',
            durationHours: ! empty($validated['hours']) ? (int) $validated['hours'] : null,
            source: 'api',
            userId: $request->user()?->id ? (int) $request->user()->id : null
        );

        return response()->json([
            'success' => true,
            'message' => "IP {$validated['ip']} berhasil diblokir.",
        ], 201);
    }

    /**
     * Buka blokir IP address (JSON).
     * POST /api/keamanan/unblock/{ip}
     */
    public function unblockIp(string $ip): JsonResponse
    {
        $unblocked = Pasak::unblockIp($ip);

        return response()->json([
            'success' => true,
            'message' => $unblocked ? "Blokir untuk IP {$ip} berhasil dibuka." : "IP tidak ditemukan.",
            'data' => [
                'is_blocked' => Pasak::isBlocked($ip),
            ],
        ]);
    }
}
```

### Langkah 2: Daftarkan Rute di `routes/api.php`
```php
use App\Http\Controllers\Api\KeamananController;

Route::prefix('keamanan')->name('api.keamanan.')->group(function () {
    Route::get('/', [KeamananController::class, 'index'])->name('index');
    Route::get('/logs', [KeamananController::class, 'logs'])->name('logs');
    Route::get('/blocked-ips', [KeamananController::class, 'blockedIps'])->name('blocked-ips');
    Route::get('/check/{ip}', [KeamananController::class, 'checkIp'])->name('check');
    Route::post('/block', [KeamananController::class, 'blockIp'])->name('block');
    Route::post('/unblock/{ip}', [KeamananController::class, 'unblockIp'])->name('unblock');
});
```

---

## 5. Panduan Praktik Keamanan untuk Kode Kustom

Saat Anda membuat controller dan view kustom di aplikasi host, terapkan kaidah keamanan berikut:

1. **Gunakan Selalu Validasi Form Input**:
   Validasi IP address target menggunakan aturan `'ip'` bawaan Laravel (`'ip' => ['required', 'ip']`) agar input liar tidak masuk ke basis data.
2. **Gunakan Facade Resmi `Pasak::blockIp()` & `Pasak::unblockIp()`**:
   Hindari melakukan `PasakBlockedIp::create()` secara manual tanpa melalui Facade, karena Facade menangani pembersihan cache memoize in-memory dan sinkronisasi status middleware WAF secara otomatis.
3. **Lindungi Rute dengan Otorisasi Ketat**:
   Pastikan seluruh rute kustom Anda diproteksi oleh middleware `auth` dan hak akses admin:
   ```php
   // Di Controller Anda
   $this->authorize('manage-pasak');
   ```
4. **Hindari Menghapus Log Tanpa Backup di Produksi**:
   Pada lingkungan produksi (*production*), log ancaman di tabel `pasak_logs` berguna untuk investigasi forensik. Gunakan perintah bawaan `php artisan pasak:prune-logs --days=30` untuk rotasi berkala daripada melakukan truncate instan.
