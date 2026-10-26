<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Internal\Pasak\Facades\Pasak;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakLog;

/**
 * Contoh Controller Kustom di Host Application:
 * Developer bebas membuat UI dan alur keamanan versi sendiri dengan memanfaatkan
 * Facade dan Model yang disediakan oleh paket robyajo/pasak.
 */
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

        // 6. Status engine WAF
        $wafEnabled = Pasak::isEnabled();
        $enforcementEnabled = Pasak::isBlockEnforcementEnabled();

        return view('keamanan.index', [
            'stats' => $stats,
            'levelBreakdown' => $levelBreakdown,
            'topAttackers' => $topAttackers,
            'blockedIps' => $blockedIps,
            'logs' => $logs,
            'wafEnabled' => $wafEnabled,
            'enforcementEnabled' => $enforcementEnabled,
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

        $ip = $validated['ip'];
        $reason = $validated['reason'] ?: 'Diblokir manual dari Dashboard Keamanan Kustom';
        $durationHours = ! empty($validated['hours']) ? (int) $validated['hours'] : null;

        // Gunakan Facade Pasak untuk memblokir IP
        Pasak::blockIp(
            ip: $ip,
            reason: $reason,
            durationHours: $durationHours,
            source: 'manual',
            userId: $request->user()?->id ? (int) $request->user()->id : null
        );

        return redirect()
            ->route('keamanan.index')
            ->with('status', "IP {$ip} berhasil diblokir.");
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
