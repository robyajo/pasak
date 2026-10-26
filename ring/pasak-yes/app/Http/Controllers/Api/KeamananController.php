<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Internal\Pasak\Facades\Pasak;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakLog;

/**
 * REST API Kustom Keamanan (JSON):
 * Contoh implementasi API bagi developer yang ingin mengonsumsi data dan aksi
 * keamanan Pasak secara headless (React/Vue/Next.js/Mobile App/CLI).
 */
class KeamananController extends Controller
{
    /**
     * Ringkasan statistik dan status WAF (JSON).
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
     * Daftar riwayat log ancaman terpaginasi dengan filter (JSON).
     * GET /api/keamanan/logs?level=critical&search=union&per_page=15
     */
    public function logs(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

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
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Daftar IP yang sedang diblokir (JSON).
     * GET /api/keamanan/blocked-ips
     */
    public function blockedIps(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', true);

        $query = PasakBlockedIp::query();
        if ($activeOnly) {
            $query->active();
        }

        $items = $query->latest('id')->get();

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
        $isBlocked = Pasak::isBlocked($ip);
        $record = $isBlocked ? PasakBlockedIp::query()->active()->where('ip_address', $ip)->first() : null;

        return response()->json([
            'success' => true,
            'data' => [
                'ip_address' => $ip,
                'is_blocked' => $isBlocked,
                'details' => $record,
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
            'hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        $ip = $validated['ip'];
        $reason = $validated['reason'] ?? 'Diblokir manual via API Keamanan Kustom';
        $durationHours = ! empty($validated['hours']) ? (int) $validated['hours'] : null;

        $userId = $request->user()?->id ? (int) $request->user()->id : null;

        Pasak::blockIp(
            ip: $ip,
            reason: $reason,
            durationHours: $durationHours,
            source: 'api',
            userId: $userId
        );

        $record = PasakBlockedIp::query()->where('ip_address', $ip)->first();

        return response()->json([
            'success' => true,
            'message' => "IP {$ip} berhasil diblokir.",
            'data' => $record,
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
            'message' => $unblocked
                ? "Blokir untuk IP {$ip} berhasil dibuka."
                : "IP {$ip} tidak ditemukan dalam daftar blokir aktif.",
            'data' => [
                'ip_address' => $ip,
                'is_blocked' => Pasak::isBlocked($ip),
            ],
        ]);
    }

    /**
     * Bersihkan riwayat log ancaman (JSON).
     * DELETE /api/keamanan/logs
     */
    public function clearLogs(): JsonResponse
    {
        PasakLog::truncate();

        return response()->json([
            'success' => true,
            'message' => 'Semua riwayat log ancaman WAF berhasil dibersihkan.',
        ]);
    }
}
