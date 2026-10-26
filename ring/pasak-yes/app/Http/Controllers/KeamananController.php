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

/**
 * Controller Keamanan Kustom Lengkap (Full Custom Suite):
 * Menyediakan implementasi percontohan menyeluruh bagi pengembang yang ingin
 * mengontrol penuh antarmuka WAF dan 8 modul keamanan tanpa menyentuh vendor.
 */
class KeamananController extends Controller
{
    // =========================================================================
    // 1. OVERVIEW / RINGKASAN
    // =========================================================================

    public function index(Request $request): View
    {
        $stats = Pasak::stats();
        $levelBreakdown = Pasak::levelBreakdown(24);
        $topAttackers = Pasak::topAttackers(5);
        $recentLogs = PasakLog::query()->latest('id')->limit(8)->get();
        $recentBlocks = PasakBlockedIp::query()->active()->latest('id')->limit(5)->get();

        return view('keamanan.index', [
            'stats' => $stats,
            'levelBreakdown' => $levelBreakdown,
            'topAttackers' => $topAttackers,
            'recentLogs' => $recentLogs,
            'recentBlocks' => $recentBlocks,
            'wafEnabled' => Pasak::isEnabled(),
            'enforcementEnabled' => Pasak::isBlockEnforcementEnabled(),
        ]);
    }

    // =========================================================================
    // 2. LOG ANCAMAN WAF
    // =========================================================================

    public function logs(Request $request): View
    {
        $logs = PasakLog::query()
            ->when($request->filled('level'), fn ($q) => $q->where('threat_level', $request->query('level')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->query('search');
                $query->where(function ($sub) use ($search) {
                    $sub->where('ip_address', 'like', "%{$search}%")
                        ->orWhere('path', 'like', "%{$search}%")
                        ->orWhere('rule_label', 'like', "%{$search}%");
                });
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

    // =========================================================================
    // 3. IP DIBLOKIR & QUARANTINE
    // =========================================================================

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

        $ip = $validated['ip'];
        $reason = $validated['reason'] ?: 'Diblokir manual dari Dashboard Keamanan Kustom';
        $durationHours = ! empty($validated['hours']) ? (int) $validated['hours'] : null;
        $scope = $validated['scope'] ?? 'ip';

        Pasak::blockIp(
            ip: $ip,
            reason: $reason,
            durationHours: $durationHours,
            source: 'manual',
            userId: $request->user()?->id ? (int) $request->user()->id : null,
            scope: $scope
        );

        return back()->with('status', "IP {$ip} berhasil diblokir.");
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

    // =========================================================================
    // 4. WHITELIST IP / ALLOWED IPS
    // =========================================================================

    public function allowedIps(Request $request): View
    {
        $allowedIps = PasakAllowedIp::query()->latest('id')->paginate(15);

        return view('keamanan.allowed-ips', [
            'allowedIps' => $allowedIps,
        ]);
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

    // =========================================================================
    // 5. AUDIT KEAMANAN SERVER & WEBSHELL SCANNER
    // =========================================================================

    public function server(Request $request, PasakServerSecurityService $service): View
    {
        $force = $request->boolean('refresh', false);
        $report = $service->scan($force);
        $baseline = $service->baselineSummary();
        $lockouts = PasakLoginAttempt::query()->where('is_locked', true)->latest('id')->get();

        return view('keamanan.server', [
            'report' => $report,
            'baseline' => $baseline,
            'lockouts' => $lockouts,
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

    // =========================================================================
    // 6. SESI LOGIN PENGGUNA & IP TERPERCAYA
    // =========================================================================

    public function sessions(Request $request): View
    {
        $sessions = PasakUserLogin::query()->latest('id')->paginate(15);
        $trustedIps = PasakTrustedIp::query()->latest('id')->get();

        return view('keamanan.sessions', [
            'sessions' => $sessions,
            'trustedIps' => $trustedIps,
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

    // =========================================================================
    // 7. TIKET PERMOHONAN BANDING UNBLOCK
    // =========================================================================

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

        // Jika disetujui, buka blokir otomatis via Facade Pasak
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

    // =========================================================================
    // 8. PENGATURAN WAF DINAMIS
    // =========================================================================

    public function settings(Request $request): View
    {
        $settings = Pasak::getSettings();

        return view('keamanan.settings', [
            'settings' => $settings,
        ]);
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
