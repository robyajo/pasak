---
title: "Panduan Kustomisasi Penuh (Full Custom Suite: Blade & REST API) Tanpa Mengubah Vendor"
description: "Paket robyajo/pasak dirancang mengikuti filosofi Spatie-Standard & Headless Architecture. Artinya, setelah paket dipasang melalui composer require..."
sidebar:
  order: 4
---

Paket **`robyajo/pasak`** dirancang mengikuti filosofi **Spatie-Standard & Headless Architecture**. Artinya, setelah paket dipasang melalui `composer require robyajo/pasak`, Anda memiliki kebebasan dan kendali 100% penuh untuk membuat tampilan monitoring kustom, alur kerja keamanan sendiri, maupun antarmuka REST API JSON tanpa pernah menyentuh atau merusak berkas inti paket di `vendor/`.

Dokumen ini adalah cetak biru (*blueprint*) dan implementasi referensi resmi percontohan menyeluruh bagi pengembang yang ingin membangun dashboard Blade dan REST API kustom mencakup **seluruh 8 modul keamanan Pasak**.

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
│       ├── KeamananController.php          <-- [ZONA ANDA] Controller Blade kustom (8 Modul)
│       └── Api/KeamananController.php      <-- [ZONA ANDA] REST API JSON kustom (8 Modul)
├── config/
│   └── pasak.php                           <-- [ZONA ANDA] Konfigurasi aman diubah
├── resources/views/
│   ├── layouts/app.blade.php               <-- [ZONA ANDA] Template induk aplikasi Anda
│   ├── keamanan/                           <-- [ZONA ANDA] Suite Tampilan Kustom Penuh:
│   │   ├── _nav.blade.php                      (Komponen tab navigasi bersama)
│   │   ├── index.blade.php                     (1. Ringkasan & Kartu Statistik)
│   │   ├── logs.blade.php                      (2. Riwayat Log Ancaman & Filter)
│   │   ├── blocked-ips.blade.php               (3. Isolasi IP & Device Quarantine)
│   │   ├── allowed-ips.blade.php               (4. Whitelist IP & Jaringan CIDR)
│   │   ├── server.blade.php                    (5. Audit Server & Integritas SHA-256)
│   │   ├── sessions.blade.php                  (6. Sesi Pengguna & IP Terpercaya)
│   │   ├── tickets.blade.php                   (7. Tiket Banding Permohonan Unblock)
│   │   └── settings.blade.php                  (8. Pengaturan WAF Runtime Dinamis)
├── routes/
│   ├── web.php                             <-- [ZONA ANDA] Rute web kustom (/keamanan/*)
│   ├── api.php                             <-- [ZONA ANDA] Rute API kustom (/api/keamanan/*)
│   ├── pasak.php                           <-- [ZONA ANDA] Rute bawaan paket (bisa disesuaikan)
│   └── pasak-api.php                       <-- [ZONA ANDA] Rute API bawaan paket
│
└── vendor/robyajo/pasak/                   <-- [ZONA DILINDUNGI / READ-ONLY]
    ├── src/                                    (Engine WAF, Service, Middleware, Model)
    │   ├── Facades/Pasak.php                   (Facade resmi untuk dipanggil aplikasi Anda)
    │   ├── Services/PasakService.php           (Mesin deteksi ancaman & inspeksi payload)
    │   ├── Services/PasakServerSecurityService.php (Engine audit server & baseline SHA-256)
    │   ├── Http/Middleware/                    (Middleware pemblokir & penganalisis ancaman)
    │   └── Models/                             (Model Eloquent: PasakLog, PasakBlockedIp, dll.)
    ├── database/migrations/                    (Definisi skema tabel migrasi)
    └── stubs/                                  (Template publikasi)
```

- **`vendor/robyajo/pasak` (Zona Mesin / Engine)**: Layak dilihat hanya untuk referensi audit (*read-only*). Berisi logika inti WAF, parser header reverse-proxy, dan filter keamanan tingkat rendah.
- **Aplikasi Host Anda (Zona Pengembang)**: Semua penyesuaian tampilan, rute, otorisasi, dan integrasi UI dilakukan di sini.

---

## 3. Komponen Inti yang Disediakan Paket untuk Kode Kustom Anda

Aplikasi Anda cukup memanggil komponen resmi berikut:

| Komponen | Namespace / FQCN | Peran & Fungsi |
|---|---|---|
| **Facade Pasak** | `Internal\Pasak\Facades\Pasak` | Akses statistik (`stats()`), cek blokir (`isBlocked()`), blokir IP (`blockIp()`), unblock (`unblockIp()`), runtime settings (`getSettings()`, `updateSettings()`). |
| **Server Security Service** | `Internal\Pasak\Services\PasakServerSecurityService` | Audit konfigurasi PHP/DB/Storage (`scan()`), buat baseline SHA-256 (`createBaseline()`), hapus berkas mencurigakan defensif (`deleteSuspiciousFile()`). |
| **Model Log Ancaman** | `Internal\Pasak\Models\PasakLog` | Query Eloquent ke riwayat ancaman WAF (`threat_level`, `ip_address`, `path`, `payload`). |
| **Model IP Terblokir** | `Internal\Pasak\Models\PasakBlockedIp` | Manajemen IP & device terisolasi (`ip_address`, `device_id`, `block_scope`, `is_active`). |
| **Model Whitelist** | `Internal\Pasak\Models\PasakAllowedIp` | Daftar pengecualian IP/CIDR yang kebal deteksi WAF. |
| **Model Percobaan Login** | `Internal\Pasak\Models\PasakLoginAttempt` | Pelacakan mitigasi brute-force bertingkat (*stepped throttling*). |
| **Model Sesi Pengguna** | `Internal\Pasak\Models\PasakUserLogin` | Riwayat sesi aktif pengguna, heartbeat, dan data perangkat login. |
| **Model IP Terpercaya** | `Internal\Pasak\Models\PasakTrustedIp` | Alamat IP admin/staf yang diverifikasi sah. |
| **Model Tiket Banding** | `Internal\Pasak\Models\PasakIpUnblockRequest` | Alur *self-service appeal* bagi pengguna yang terblokir tidak sengaja. |

---

## 4. Implementasi Penuh Web Controller Host (`app/Http/Controllers/KeamananController.php`)

Buat berkas kustom di dalam aplikasi host Anda:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Internal\Pasak\Facades\Pasak;
use Internal\Pasak\Models\PasakAllowedIp;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakIpUnblockRequest;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Models\PasakLoginAttempt;
use Internal\Pasak\Models\PasakTrustedIp;
use Internal\Pasak\Models\PasakUserLogin;
use Internal\Pasak\Services\PasakServerSecurityService;

class KeamananController extends Controller
{
    // 1. OVERVIEW / RINGKASAN
    public function index(Request $request): View
    {
        return view('keamanan.index', [
            'stats' => Pasak::stats(),
            'levelBreakdown' => Pasak::levelBreakdown(24),
            'topAttackers' => Pasak::topAttackers(5),
            'recentLogs' => PasakLog::query()->latest('id')->limit(8)->get(),
            'recentBlocks' => PasakBlockedIp::query()->active()->latest('id')->limit(5)->get(),
            'wafEnabled' => Pasak::isEnabled(),
            'enforcementEnabled' => Pasak::isBlockEnforcementEnabled(),
        ]);
    }

    // 2. LOG ANCAMAN WAF
    public function logs(Request $request): View
    {
        $logs = PasakLog::query()
            ->when($request->filled('level'), fn ($q) => $q->where('threat_level', $request->query('level')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->query('search');
                $query->where(fn ($sub) => $sub->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('rule_label', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('keamanan.logs', [
            'logs' => $logs,
            'filterLevel' => $request->query('level', ''),
            'filterSearch' => $request->query('search', ''),
        ]);
    }

    public function destroyLog(int $id): RedirectResponse
    {
        PasakLog::destroy($id);
        return back()->with('status', "Log ancaman #{$id} berhasil dihapus.");
    }

    public function clearLogs(): RedirectResponse
    {
        PasakLog::truncate();
        return back()->with('status', 'Semua riwayat log ancaman WAF berhasil dibersihkan.');
    }

    // 3. IP DIBLOKIR & DEVICE QUARANTINE
    public function blockedIps(Request $request): View
    {
        $blockedIps = PasakBlockedIp::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->where(fn ($sub) => $sub->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('keamanan.blocked-ips', [
            'blockedIps' => $blockedIps,
            'filterSearch' => $request->query('search', ''),
        ]);
    }

    public function blockIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip' => ['required', 'ip'],
            'reason' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'scope' => ['nullable', 'string', 'in:ip,device'],
        ]);

        Pasak::blockIp(
            ip: $validated['ip'],
            reason: $validated['reason'] ?: 'Diblokir manual dari Dashboard Keamanan Kustom',
            durationHours: ! empty($validated['hours']) ? (int) $validated['hours'] : null,
            source: 'manual',
            userId: $request->user()?->id ? (int) $request->user()->id : null,
            scope: $validated['scope'] ?? 'ip'
        );

        return back()->with('status', "IP {$validated['ip']} berhasil diblokir.");
    }

    public function unblockIp(string $ip): RedirectResponse
    {
        Pasak::unblockIp($ip);
        return back()->with('status', "Blokir untuk IP {$ip} berhasil dibuka.");
    }

    public function toggleBlockedIp(int $id): RedirectResponse
    {
        $item = PasakBlockedIp::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        $status = $item->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

        return back()->with('status', "Status blokir untuk IP {$item->ip_address} berhasil {$status}.");
    }

    public function destroyBlockedIp(int $id): RedirectResponse
    {
        $item = PasakBlockedIp::findOrFail($id);
        $ip = $item->ip_address;
        $item->delete();

        return back()->with('status', "Rekaman blokir IP {$ip} berhasil dihapus.");
    }

    // 4. WHITELIST IP (ALLOWED IPS)
    public function allowedIps(Request $request): View
    {
        $allowedIps = PasakAllowedIp::query()->latest('id')->paginate(15);
        return view('keamanan.allowed-ips', ['allowedIps' => $allowedIps]);
    }

    public function storeAllowedIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        PasakAllowedIp::create([
            'ip_address' => $validated['ip_address'],
            'label' => $validated['label'] ?: 'Whitelist Manual',
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
            'allowed_by' => $request->user()?->id,
        ]);

        return back()->with('status', "IP / CIDR {$validated['ip_address']} berhasil ditambahkan ke whitelist.");
    }

    public function toggleAllowedIp(int $id): RedirectResponse
    {
        $item = PasakAllowedIp::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        $status = $item->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('status', "Whitelist {$item->ip_address} berhasil {$status}.");
    }

    public function destroyAllowedIp(int $id): RedirectResponse
    {
        $item = PasakAllowedIp::findOrFail($id);
        $item->delete();
        return back()->with('status', 'Alamat whitelist berhasil dihapus.');
    }

    // 5. AUDIT SERVER & WEBSHELL SCANNER
    public function server(Request $request, PasakServerSecurityService $service): View
    {
        $force = $request->boolean('refresh', false);
        return view('keamanan.server', [
            'report' => $service->scan($force),
            'baseline' => $service->baselineSummary(),
            'lockouts' => PasakLoginAttempt::query()->where('is_locked', true)->latest('id')->get(),
        ]);
    }

    public function serverBaseline(PasakServerSecurityService $service): RedirectResponse
    {
        $result = $service->createBaseline();
        $total = count($result['files'] ?? []);
        return back()->with('status', "Baseline SHA-256 berhasil dibuat ({$total} berkas dipindai).");
    }

    public function serverBaselineDestroy(PasakServerSecurityService $service): RedirectResponse
    {
        $service->deleteBaseline();
        return back()->with('status', 'Baseline SHA-256 berhasil dihapus.');
    }

    public function deleteSuspiciousFile(Request $request, PasakServerSecurityService $service): RedirectResponse
    {
        $request->validate(['path' => ['required', 'string']]);
        $res = $service->deleteSuspiciousFile($request->input('path'));
        if ($res['success']) {
            return back()->with('status', "Berkas mencurigakan '{$request->input('path')}' berhasil dimusnahkan.");
        }
        return back()->withErrors(['err' => $res['message'] ?? 'Gagal menghapus berkas.']);
    }

    public function releaseLockout(int $id): RedirectResponse
    {
        PasakLoginAttempt::where('id', $id)->delete();
        return back()->with('status', 'Kunci akun login berhasil dibuka kembali.');
    }

    // 6. SESI LOGIN PENGGUNA & IP TERPERCAYA
    public function sessions(Request $request): View
    {
        return view('keamanan.sessions', [
            'sessions' => PasakUserLogin::query()->latest('id')->paginate(15),
            'trustedIps' => PasakTrustedIp::query()->latest('id')->get(),
        ]);
    }

    public function destroySession(int $id): RedirectResponse
    {
        $session = PasakUserLogin::findOrFail($id);
        $session->update(['logout_at' => now()]);
        return back()->with('status', "Sesi pengguna #{$id} berhasil diputus.");
    }

    public function storeTrustedIp(Request $request): RedirectResponse
    {
        $ip = $request->ip() ?: '127.0.0.1';
        PasakTrustedIp::firstOrCreate([
            'user_id' => $request->user()?->id,
            'ip_address' => $ip,
        ], [
            'device_name' => substr((string) $request->userAgent(), 0, 100),
        ]);

        return back()->with('status', "Alamat IP Anda ({$ip}) berhasil ditandai sebagai IP Terpercaya.");
    }

    public function destroyTrustedIp(int $id): RedirectResponse
    {
        PasakTrustedIp::destroy($id);
        return back()->with('status', 'IP Terpercaya berhasil dihapus.');
    }

    // 7. TIKET PERMOHONAN BANDING UNBLOCK
    public function tickets(Request $request): View
    {
        $tickets = PasakIpUnblockRequest::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('keamanan.tickets', [
            'tickets' => $tickets,
            'filterStatus' => $request->query('status', ''),
        ]);
    }

    public function respondTicket(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $ticket = PasakIpUnblockRequest::findOrFail($id);
        $ticket->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
            'resolved_by' => $request->user()?->id,
            'resolved_at' => now(),
        ]);

        if ($validated['status'] === 'approved') {
            Pasak::unblockIp($ticket->ip_address, $ticket->device_id);
        }

        $action = $validated['status'] === 'approved' ? 'disetujui dan blokir telah dibuka' : 'ditolak';
        return back()->with('status', "Tiket permohonan #{$ticket->ticket_number} berhasil {$action}.");
    }

    public function destroyTicket(int $id): RedirectResponse
    {
        PasakIpUnblockRequest::destroy($id);
        return back()->with('status', 'Tiket permohonan berhasil dihapus.');
    }

    // 8. PENGATURAN WAF DINAMIS
    public function settings(Request $request): View
    {
        return view('keamanan.settings', ['settings' => Pasak::getSettings()]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'auto_block_scope' => ['nullable', 'string', 'in:device,ip,auto'],
            'auto_block_enabled' => ['nullable', 'boolean'],
            'auto_block_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'auto_block_duration' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'instant_block_enabled' => ['nullable', 'boolean'],
            'block_enforcement' => ['nullable', 'boolean'],
        ]);

        Pasak::updateSettings($validated);
        return back()->with('status', 'Pengaturan WAF berhasil diperbarui.');
    }
}
```

---

## 5. Implementasi Penuh REST API Controller Host (`app/Http/Controllers/Api/KeamananController.php`)

Untuk integrasi ke aplikasi mobile (Flutter/React Native), antarmuka Next.js/Nuxt.js, atau sistem pemantauan eksternal:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Internal\Pasak\Facades\Pasak;
use Internal\Pasak\Models\PasakAllowedIp;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakIpUnblockRequest;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Models\PasakLoginAttempt;
use Internal\Pasak\Models\PasakTrustedIp;
use Internal\Pasak\Models\PasakUserLogin;
use Internal\Pasak\Services\PasakServerSecurityService;

class KeamananController extends Controller
{
    // 1. Overview
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Status dan statistik operasional WAF.',
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

    // 2. Logs
    public function logs(Request $request): JsonResponse
    {
        $logs = PasakLog::query()
            ->when($request->query('level'), fn ($q, $l) => $q->where('threat_level', $l))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json(['success' => true, 'data' => $logs]);
    }

    public function clearLogs(): JsonResponse
    {
        PasakLog::truncate();
        return response()->json(['success' => true, 'message' => 'Semua riwayat log ancaman WAF berhasil dibersihkan.']);
    }

    // 3. Blocked IPs
    public function blockedIps(): JsonResponse
    {
        $items = PasakBlockedIp::query()->active()->latest('id')->get();
        return response()->json(['success' => true, 'count' => $items->count(), 'data' => $items]);
    }

    public function checkIp(string $ip): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['ip_address' => $ip, 'is_blocked' => Pasak::isBlocked($ip)],
        ]);
    }

    public function blockIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => ['required', 'ip'],
            'reason' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:1'],
            'scope' => ['nullable', 'string', 'in:ip,device'],
        ]);

        Pasak::blockIp(
            ip: $validated['ip'],
            reason: $validated['reason'] ?? 'Diblokir manual via REST API',
            durationHours: ! empty($validated['hours']) ? (int) $validated['hours'] : null,
            source: 'api',
            userId: $request->user()?->id ? (int) $request->user()->id : null,
            scope: $validated['scope'] ?? 'ip'
        );

        return response()->json(['success' => true, 'message' => "IP {$validated['ip']} berhasil diblokir."], 201);
    }

    public function unblockIp(string $ip): JsonResponse
    {
        $unblocked = Pasak::unblockIp($ip);
        return response()->json([
            'success' => true,
            'message' => $unblocked ? "Blokir untuk IP {$ip} berhasil dibuka." : 'IP tidak ditemukan.',
            'data' => ['is_blocked' => Pasak::isBlocked($ip)],
        ]);
    }

    // 4. Allowed IPs (Whitelist)
    public function allowedIps(): JsonResponse
    {
        $items = PasakAllowedIp::query()->latest('id')->get();
        return response()->json(['success' => true, 'count' => $items->count(), 'data' => $items]);
    }

    public function storeAllowedIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $item = PasakAllowedIp::create([
            'ip_address' => $validated['ip_address'],
            'label' => $validated['label'] ?: 'Whitelist API',
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
            'allowed_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'message' => "IP {$item->ip_address} ditambahkan ke whitelist.", 'data' => $item], 201);
    }

    public function destroyAllowedIp(int $id): JsonResponse
    {
        PasakAllowedIp::destroy($id);
        return response()->json(['success' => true, 'message' => "Whitelist #{$id} berhasil dihapus."]);
    }

    // 5. Server Security
    public function serverReport(Request $request, PasakServerSecurityService $service): JsonResponse
    {
        $force = $request->boolean('refresh', false);
        return response()->json([
            'success' => true,
            'data' => [
                'report' => $service->scan($force),
                'baseline' => $service->baselineSummary(),
            ],
        ]);
    }

    public function createBaseline(PasakServerSecurityService $service): JsonResponse
    {
        $result = $service->createBaseline();
        return response()->json([
            'success' => true,
            'message' => 'Baseline integritas SHA-256 berhasil dibuat.',
            'data' => [
                'total_files' => count($result['files'] ?? []),
                'created_at' => $result['created_at'] ?? now()->toIso8601String(),
                'files' => $result['files'] ?? [],
            ],
        ], 201);
    }

    // 6. User Sessions & Trusted IPs
    public function sessions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'sessions' => PasakUserLogin::query()->latest('id')->limit(50)->get(),
                'trusted_ips' => PasakTrustedIp::query()->latest('id')->get(),
            ],
        ]);
    }

    public function storeTrustedIp(Request $request): JsonResponse
    {
        $ip = $request->ip() ?: '127.0.0.1';
        $trusted = PasakTrustedIp::firstOrCreate([
            'user_id' => $request->user()?->id,
            'ip_address' => $ip,
        ], [
            'device_name' => substr((string) $request->userAgent(), 0, 100),
        ]);

        return response()->json(['success' => true, 'message' => "IP {$ip} disimpan sebagai IP terpercaya.", 'data' => $trusted], 201);
    }

    // 7. Tickets
    public function tickets(Request $request): JsonResponse
    {
        $tickets = PasakIpUnblockRequest::query()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    public function respondTicket(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $ticket = PasakIpUnblockRequest::findOrFail($id);
        $ticket->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
            'resolved_by' => $request->user()?->id,
            'resolved_at' => now(),
        ]);

        if ($validated['status'] === 'approved') {
            Pasak::unblockIp($ticket->ip_address, $ticket->device_id);
        }

        return response()->json(['success' => true, 'message' => "Tiket #{$ticket->ticket_number} diperbarui.", 'data' => $ticket]);
    }

    // 8. Settings
    public function settings(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Pasak::getSettings()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auto_block_scope' => ['nullable', 'string', 'in:device,ip,auto'],
            'auto_block_enabled' => ['nullable', 'boolean'],
            'auto_block_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'auto_block_duration' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'instant_block_enabled' => ['nullable', 'boolean'],
            'block_enforcement' => ['nullable', 'boolean'],
        ]);

        $updated = Pasak::updateSettings($validated);
        return response()->json(['success' => true, 'message' => 'Pengaturan WAF berhasil diperbarui.', 'data' => $updated]);
    }
}
```

---

## 6. Mendaftarkan Rute di Aplikasi Host

### Rute Web (`routes/web.php`)
```php
use App\Http\Controllers\KeamananController;

Route::middleware('auth')->prefix('keamanan')->name('keamanan.')->group(function () {
    // 1. Overview
    Route::get('/', [KeamananController::class, 'index'])->name('index');

    // 2. Logs
    Route::get('logs', [KeamananController::class, 'logs'])->name('logs');
    Route::delete('logs', [KeamananController::class, 'clearLogs'])->name('logs.clear');
    Route::delete('logs/{id}', [KeamananController::class, 'destroyLog'])->name('logs.destroy');

    // 3. Blocked IPs & Quarantine
    Route::get('blocked-ips', [KeamananController::class, 'blockedIps'])->name('blocked-ips');
    Route::post('block', [KeamananController::class, 'blockIp'])->name('block');
    Route::post('unblock/{ip}', [KeamananController::class, 'unblockIp'])->name('unblock');
    Route::patch('blocked-ips/{id}/toggle', [KeamananController::class, 'toggleBlockedIp'])->name('blocked-ips.toggle');
    Route::delete('blocked-ips/{id}', [KeamananController::class, 'destroyBlockedIp'])->name('blocked-ips.destroy');

    // 4. Allowed IPs (Whitelist)
    Route::get('allowed-ips', [KeamananController::class, 'allowedIps'])->name('allowed-ips');
    Route::post('allowed-ips', [KeamananController::class, 'storeAllowedIp'])->name('allowed-ips.store');
    Route::patch('allowed-ips/{id}/toggle', [KeamananController::class, 'toggleAllowedIp'])->name('allowed-ips.toggle');
    Route::delete('allowed-ips/{id}', [KeamananController::class, 'destroyAllowedIp'])->name('allowed-ips.destroy');

    // 5. Server Security & Audit
    Route::get('server', [KeamananController::class, 'server'])->name('server');
    Route::post('server/baseline', [KeamananController::class, 'serverBaseline'])->name('server.baseline');
    Route::delete('server/baseline', [KeamananController::class, 'serverBaselineDestroy'])->name('server.baseline.destroy');
    Route::delete('server/suspicious-files', [KeamananController::class, 'deleteSuspiciousFile'])->name('server.suspicious-files.destroy');
    Route::delete('lockouts/{id}', [KeamananController::class, 'releaseLockout'])->name('lockouts.destroy');

    // 6. User Sessions & Trusted IPs
    Route::get('sessions', [KeamananController::class, 'sessions'])->name('sessions');
    Route::delete('sessions/{id}', [KeamananController::class, 'destroySession'])->name('sessions.destroy');
    Route::post('trusted-ips/my-ip', [KeamananController::class, 'storeTrustedIp'])->name('trusted-ips.store');
    Route::delete('trusted-ips/{id}', [KeamananController::class, 'destroyTrustedIp'])->name('trusted-ips.destroy');

    // 7. Appeal Tickets
    Route::get('tickets', [KeamananController::class, 'tickets'])->name('tickets');
    Route::post('tickets/{id}/respond', [KeamananController::class, 'respondTicket'])->name('tickets.respond');
    Route::delete('tickets/{id}', [KeamananController::class, 'destroyTicket'])->name('tickets.destroy');

    // 8. Runtime Settings
    Route::get('settings', [KeamananController::class, 'settings'])->name('settings');
    Route::post('settings', [KeamananController::class, 'updateSettings'])->name('settings.update');
});
```

### Rute REST API (`routes/api.php`)
```php
use App\Http\Controllers\Api\KeamananController;

Route::prefix('keamanan')->name('api.keamanan.')->group(function () {
    Route::get('/', [KeamananController::class, 'index'])->name('index');
    Route::get('logs', [KeamananController::class, 'logs'])->name('logs');
    Route::delete('logs', [KeamananController::class, 'clearLogs'])->name('logs.clear');
    Route::get('blocked-ips', [KeamananController::class, 'blockedIps'])->name('blocked-ips');
    Route::get('check/{ip}', [KeamananController::class, 'checkIp'])->name('check');
    Route::post('block', [KeamananController::class, 'blockIp'])->name('block');
    Route::post('unblock/{ip}', [KeamananController::class, 'unblockIp'])->name('unblock');
    Route::get('allowed-ips', [KeamananController::class, 'allowedIps'])->name('allowed-ips');
    Route::post('allowed-ips', [KeamananController::class, 'storeAllowedIp'])->name('allowed-ips.store');
    Route::delete('allowed-ips/{id}', [KeamananController::class, 'destroyAllowedIp'])->name('allowed-ips.destroy');
    Route::get('server', [KeamananController::class, 'serverReport'])->name('server.report');
    Route::post('server/baseline', [KeamananController::class, 'createBaseline'])->name('server.baseline');
    Route::get('sessions', [KeamananController::class, 'sessions'])->name('sessions');
    Route::post('trusted-ips', [KeamananController::class, 'storeTrustedIp'])->name('trusted-ips.store');
    Route::get('tickets', [KeamananController::class, 'tickets'])->name('tickets');
    Route::post('tickets/{id}/respond', [KeamananController::class, 'respondTicket'])->name('tickets.respond');
    Route::get('settings', [KeamananController::class, 'settings'])->name('settings');
    Route::match(['post', 'put'], 'settings', [KeamananController::class, 'updateSettings'])->name('settings.update');
});
```

---

## 7. Pengujian Otomatis (Pest Testing)

Pastikan implementasi kustom Anda terverifikasi dengan menambahkan pengujian di `tests/Feature/KeamananCustomTest.php`:

```php
test('authenticated user can access all 8 modular custom views', function () {
    $user = User::factory()->create();

    $routes = [
        '/keamanan',
        '/keamanan/logs',
        '/keamanan/blocked-ips',
        '/keamanan/allowed-ips',
        '/keamanan/server',
        '/keamanan/sessions',
        '/keamanan/tickets',
        '/keamanan/settings',
    ];

    foreach ($routes as $route) {
        $this->actingAs($user)->get($route)->assertOk();
    }
});
```

Dan jalankan pengujian:
```bash
./vendor/bin/pest
```

Hasil: **100% Pass** dengan integritas penuh tanpa pernah menyentuh satu baris kode pun di dalam `vendor/`!
