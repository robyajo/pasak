<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Internal\Pasak\Models\BlockedIp;
use Internal\Pasak\Models\SecurityLog;

/**
 * Security log management page for the Blade monitoring dashboard.
 */
class LogController extends Controller
{
    use RespondsWithDashboard;

    public function index(Request $request): View
    {
        $query = SecurityLog::query()->latest('id');

        $search = (string) $request->query('search', '');
        $level = (string) $request->query('level', '');
        $eventType = (string) $request->query('event_type', '');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('path', 'like', "%{$search}%")
                    ->orWhere('evidence', 'like', "%{$search}%")
                    ->orWhere('rule_label', 'like', "%{$search}%")
                    ->orWhere('user_agent', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if (in_array($level, SecurityLog::LEVELS, true)) {
            $query->where('threat_level', $level);
        }

        if ($eventType !== '') {
            $query->where('event_type', $eventType);
        }

        return $this->dashboardResponse(
            'pages.pasak.logs',
            [
                'logs' => $query->paginate(25)->withQueryString(),
                'levels' => SecurityLog::LEVELS,
                'eventTypes' => SecurityLog::query()
                    ->select('event_type')
                    ->distinct()
                    ->orderBy('event_type')
                    ->pluck('event_type')
                    ->all(),
                'filters' => [
                    'search' => $search,
                    'level' => $level,
                    'event_type' => $eventType,
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
        $count = SecurityLog::query()->count();
        SecurityLog::query()->truncate();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __(':count log entries cleared.', ['count' => $count]),
        ]);
    }

    public function show(int $id): View
    {
        $log = SecurityLog::query()->find($id);

        if (! $log) {
            abort(404);
        }

        return $this->dashboardResponse(
            'pages.pasak.log-detail',
            [
                'log' => $log,
                'relatedLogs' => SecurityLog::query()
                    ->where('ip_address', $log->ip_address)
                    ->whereKeyNot($log->getKey())
                    ->latest('id')
                    ->limit(10)
                    ->get(),
                'block' => BlockedIp::query()
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

    public function destroy(int $id): RedirectResponse
    {
        SecurityLog::query()->whereKey($id)->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Log entry deleted.'),
        ]);
    }
}
