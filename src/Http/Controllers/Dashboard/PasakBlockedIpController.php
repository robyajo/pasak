<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakIpUnblockRequest;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Services\PasakService;

/**
 * Blocked IP management page for the Blade monitoring dashboard.
 */
class PasakBlockedIpController extends Controller
{
    use RespondsWithPasakDashboard;

    public function index(Request $request): View
    {
        $query = PasakBlockedIp::query()->latest('id');

        $search = (string) $request->query('search', '');
        $status = (string) $request->query('status', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');
        $perPage = (int) $request->query('per_page', 25);

        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('device_id', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'expired') {
            $query->expired();
        } elseif ($status === 'permanent') {
            $query->whereNull('expires_at')->where('is_active', true);
        }

        if ($dateFrom !== '') {
            try {
                $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            } catch (\Throwable) {
            }
        }

        if ($dateTo !== '') {
            try {
                $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
            } catch (\Throwable) {
            }
        }

        return $this->dashboardResponse(
            'pages.pasak.blocked-ips',
            [
                'blocks' => $query->paginate($perPage)->withQueryString(),
                'stats' => [
                    'total' => PasakBlockedIp::query()->count(),
                    'active' => PasakBlockedIp::query()->active()->count(),
                    'permanent' => PasakBlockedIp::query()->whereNull('expires_at')->where('is_active', true)->count(),
                    'hits' => (int) PasakBlockedIp::query()->sum('hit_count'),
                ],
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'per_page' => $perPage,
                ],
                'urls' => [
                    'index' => route('pasak.dashboard.blocked-ips'),
                    'show' => route('pasak.dashboard.blocked-ips.show', ['id' => '__ID__']),
                    'store' => route('pasak.dashboard.blocked-ips.store'),
                    'toggle' => route('pasak.dashboard.blocked-ips.toggle', ['id' => '__ID__']),
                    'destroy' => route('pasak.dashboard.blocked-ips.destroy', ['id' => '__ID__']),
                ],
            ]
        );
    }

    public function show(int $id): View
    {
        $block = PasakBlockedIp::query()->find($id);

        if (! $block) {
            abort(404);
        }

        return $this->dashboardResponse(
            'pages.pasak.blocked-ip-detail',
            [
                'block' => $block,
                'relatedLogs' => PasakLog::query()
                    ->where('ip_address', $block->ip_address)
                    ->latest('id')
                    ->limit(20)
                    ->get(),
                'relatedTickets' => PasakIpUnblockRequest::query()
                    ->where('ip_address', $block->ip_address)
                    ->latest('id')
                    ->limit(10)
                    ->get(),
                'urls' => [
                    'index' => route('pasak.dashboard.blocked-ips'),
                    'show' => route('pasak.dashboard.blocked-ips.show', ['id' => '__ID__']),
                    'logShow' => route('pasak.dashboard.logs.show', ['id' => '__ID__']),
                    'ticketShow' => route('pasak.dashboard.tickets.show', ['id' => '__ID__']),
                    'toggle' => route('pasak.dashboard.blocked-ips.toggle', ['id' => $block->getKey()]),
                    'destroy' => route('pasak.dashboard.blocked-ips.destroy', ['id' => $block->getKey()]),
                ],
            ]
        );
    }

    public function store(Request $request, PasakService $security): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string', 'max:45'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
            'is_permanent' => ['nullable', 'boolean'],
        ]);

        $ip = trim($validated['ip_address']);

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages([
                'ip_address' => __('Invalid IP address.'),
            ]);
        }

        if ($ip === $security->resolveClientIp($request)) {
            throw ValidationException::withMessages([
                'ip_address' => __('You cannot block your own IP address.'),
            ]);
        }

        if ($security->isWhitelisted($ip)) {
            throw ValidationException::withMessages([
                'ip_address' => __('This IP is whitelisted and cannot be blocked.'),
            ]);
        }

        $duration = (int) ($validated['duration_hours'] ?? 24);
        $isPermanent = (bool) ($validated['is_permanent'] ?? ($duration === 0));

        PasakBlockedIp::query()->updateOrCreate(
            ['ip_address' => $ip],
            [
                'block_scope' => 'ip',
                'reason' => $validated['reason'] ?? __('Blocked manually by administrator'),
                'notes' => $validated['notes'] ?? null,
                'source' => 'manual',
                'blocked_by' => $request->user()?->getKey(),
                'blocked_at' => now(),
                'expires_at' => $isPermanent ? null : now()->addHours($duration),
                'is_active' => true,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('IP :ip has been blocked.', ['ip' => $ip]),
        ]);
    }

    public function toggle(int $id): RedirectResponse
    {
        $block = PasakBlockedIp::query()->find($id);

        if (! $block) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('Blocked IP not found.'),
            ]);
        }

        $block->is_active = ! $block->is_active;
        $block->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $block->is_active
                ? __('Block for :ip re-enabled.', ['ip' => $block->ip_address])
                : __('Block for :ip disabled.', ['ip' => $block->ip_address]),
        ]);
    }

    public function destroy(int $id): RedirectResponse
    {
        $block = PasakBlockedIp::query()->find($id);

        if (! $block) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('Blocked IP not found.'),
            ]);
        }

        $ip = $block->ip_address;
        $block->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('IP :ip has been unblocked.', ['ip' => $ip]),
        ]);
    }
}
