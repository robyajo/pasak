<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakLog;

/**
 * Security log management page for the Blade monitoring dashboard.
 */
class PasakLogController extends Controller
{
    use RespondsWithPasakDashboard;

    public function index(Request $request): View
    {
        $query = PasakLog::query()->latest('id');

        $search = (string) $request->query('search', '');
        $level = (string) $request->query('level', '');
        $eventType = (string) $request->query('event_type', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');
        $perPage = (int) $request->query('per_page', 25);

        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('path', 'like', "%{$search}%")
                    ->orWhere('evidence', 'like', "%{$search}%")
                    ->orWhere('rule_label', 'like', "%{$search}%")
                    ->orWhere('user_agent', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if (in_array($level, PasakLog::LEVELS, true)) {
            $query->where('threat_level', $level);
        }

        if ($eventType !== '') {
            $query->where('event_type', $eventType);
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
            'pages.pasak.logs',
            [
                'logs' => $query->paginate($perPage)->withQueryString(),
                'levels' => PasakLog::LEVELS,
                'eventTypes' => PasakLog::query()
                    ->select('event_type')
                    ->distinct()
                    ->orderBy('event_type')
                    ->pluck('event_type')
                    ->all(),
                'filters' => [
                    'search' => $search,
                    'level' => $level,
                    'event_type' => $eventType,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'per_page' => $perPage,
                ],
                'urls' => [
                    'index' => route('pasak.dashboard.logs'),
                    'show' => route('pasak.dashboard.logs.show', ['id' => '__ID__']),
                    'clear' => route('pasak.dashboard.logs.clear'),
                    'destroy' => route('pasak.dashboard.logs.destroy', ['id' => '__ID__']),
                ],
            ]
        );
    }

    public function clear(): RedirectResponse
    {
        $count = PasakLog::query()->count();
        PasakLog::query()->truncate();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __(':count log entries cleared.', ['count' => $count]),
        ]);
    }

    public function show(int|string $id): View
    {
        $log = PasakLog::query()->find($id);

        if (! $log) {
            abort(404);
        }

        return $this->dashboardResponse(
            'pages.pasak.log-detail',
            [
                'log' => $log,
                'relatedLogs' => PasakLog::query()
                    ->where('ip_address', $log->ip_address)
                    ->whereKeyNot($log->getKey())
                    ->latest('id')
                    ->limit(10)
                    ->get(),
                'block' => PasakBlockedIp::query()
                    ->where('ip_address', $log->ip_address)
                    ->latest('id')
                    ->first(),
                'urls' => [
                    'index' => route('pasak.dashboard.logs'),
                    'show' => route('pasak.dashboard.logs.show', ['id' => '__ID__']),
                    'blockShow' => route('pasak.dashboard.blocked-ips.show', ['id' => '__ID__']),
                    'destroy' => route('pasak.dashboard.logs.destroy', ['id' => $log->getKey()]),
                ],
            ]
        );
    }

    public function destroy(int|string $id): RedirectResponse
    {
        PasakLog::query()->whereKey($id)->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Log entry deleted.'),
        ]);
    }
}
