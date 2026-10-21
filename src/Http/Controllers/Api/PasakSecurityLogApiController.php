<?php

namespace Internal\Pasak\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Services\PasakService;

class PasakSecurityLogApiController extends Controller
{
    public function __construct(
        protected PasakService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = PasakLog::query()->latest('id');

        if ($level = $request->input('level')) {
            $query->where('threat_level', $level);
        }

        if ($type = $request->input('event_type')) {
            $query->where('event_type', $type);
        }

        if ($ip = $request->input('ip')) {
            $query->where('ip_address', 'like', "%{$ip}%");
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('path', 'like', "%{$search}%")
                    ->orWhere('evidence', 'like', "%{$search}%")
                    ->orWhere('rule_label', 'like', "%{$search}%")
                    ->orWhere('user_agent', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($request->has('was_blocked') && $request->input('was_blocked') !== null) {
            $query->where('was_blocked', filter_var($request->input('was_blocked'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));
        $logs = $query->paginate($perPage);

        $range = $request->input('range', 'week');
        if (! in_array($range, ['week', 'month', 'year'], true)) {
            $range = 'week';
        }

        return response()->json([
            'success' => true,
            'data' => $logs,
            'stats' => $this->service->stats(),
            'level_breakdown' => $this->service->levelBreakdown(),
            'top_attackers' => $this->service->topAttackers(5),
            'trend' => $this->service->attackTrend($range),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $count = PasakLog::query()->count();
        PasakLog::query()->truncate();

        return response()->json([
            'success' => true,
            'message' => "{$count} log keamanan berhasil dibersihkan.",
            'cleared_count' => $count,
        ]);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $log = PasakLog::query()->find($id);

        if (! $log) {
            return response()->json([
                'success' => false,
                'message' => 'Log keamanan tidak ditemukan.',
            ], 404);
        }

        $log->delete();

        return response()->json([
            'success' => true,
            'message' => 'Log keamanan berhasil dihapus.',
        ]);
    }
}
