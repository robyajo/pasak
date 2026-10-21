<?php

namespace Internal\Pasak\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Internal\Pasak\Models\PasakAllowedIp;
use Internal\Pasak\Services\PasakService;

class PasakAllowedIpApiController extends Controller
{
    public function __construct(
        protected PasakService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = PasakAllowedIp::query()->latest('id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== null) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'expired') {
                $query->expired();
            } elseif ($status === 'permanent') {
                $query->whereNull('expires_at')->where('is_active', true);
            }
        }

        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));
        $allowedIps = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $allowedIps,
            'stats' => [
                'total' => PasakAllowedIp::query()->count(),
                'active' => PasakAllowedIp::query()->active()->count(),
                'permanent' => PasakAllowedIp::query()->whereNull('expires_at')->where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string', 'max:45'],
            'label' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
            'is_permanent' => ['nullable', 'boolean'],
        ]);

        $pattern = trim($validated['ip_address']);

        if (! PasakAllowedIp::isValidPattern($pattern)) {
            return response()->json([
                'success' => false,
                'message' => 'Format IP tidak valid. Gunakan IP tunggal (203.0.113.10), wildcard (192.168.1.*), atau CIDR (10.0.0.0/8).',
            ], 422);
        }

        $duration = (int) ($validated['duration_hours'] ?? 0);
        $isPermanent = (bool) ($validated['is_permanent'] ?? ($duration === 0));

        $allowed = PasakAllowedIp::query()->updateOrCreate(
            ['ip_address' => $pattern],
            [
                'label' => $validated['label'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'allowed_by' => $request->user()?->getKey(),
                'is_active' => true,
                'expires_at' => $isPermanent ? null : now()->addHours($duration),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "IP {$pattern} berhasil diizinkan (whitelist).",
            'data' => $allowed,
        ], 201);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $allowed = PasakAllowedIp::query()->find($id);

        if (! $allowed) {
            return response()->json([
                'success' => false,
                'message' => 'Data allowed IP tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $allowed,
        ]);
    }

    public function toggle(Request $request, int|string $id): JsonResponse
    {
        $allowed = PasakAllowedIp::query()->find($id);

        if (! $allowed) {
            return response()->json([
                'success' => false,
                'message' => 'Data allowed IP tidak ditemukan.',
            ], 404);
        }

        $allowed->is_active = ! $allowed->is_active;
        $allowed->save();

        $statusText = $allowed->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'success' => true,
            'message' => "Izin untuk IP {$allowed->ip_address} berhasil {$statusText}.",
            'data' => $allowed,
        ]);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $allowed = PasakAllowedIp::query()->find($id);

        if (! $allowed) {
            return response()->json([
                'success' => false,
                'message' => 'Data allowed IP tidak ditemukan.',
            ], 404);
        }

        $ip = $allowed->ip_address;
        $allowed->delete();

        return response()->json([
            'success' => true,
            'message' => "IP {$ip} berhasil dihapus dari daftar izin.",
        ]);
    }
}
