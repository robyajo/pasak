<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Services\PasakUserLoginService;

/**
 * Security log management page for the Blade monitoring dashboard.
 */
class PasakLogController extends Controller
{
    use RespondsWithPasakDashboard;

    public function index(
        Request $request,
        ?PasakService $security = null,
    ): View {
        $query = PasakLog::query()->latest('id');

        $search = (string) $request->query('search', '');
        $level = (string) $request->query('level', '');
        $eventType = (string) $request->query('event_type', '');
        $botFilter = (string) $request->query('bot_filter', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');
        $perPage = (int) $request->query('per_page', 25);
        $trendRange = (string) $request->query(
            'trend_range',
            $request->query('range', 'week'),
        );

        if (! in_array($trendRange, ['week', 'month', 'year'], true)) {
            $trendRange = 'week';
        }

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

        if ($botFilter === 'scanner') {
            $query->where(function ($q): void {
                $q->where('user_agent', 'like', '%sqlmap%')
                    ->orWhere('user_agent', 'like', '%nuclei%')
                    ->orWhere('user_agent', 'like', '%nikto%')
                    ->orWhere('user_agent', 'like', '%acunetix%')
                    ->orWhere('user_agent', 'like', '%nessus%')
                    ->orWhere('user_agent', 'like', '%burp%')
                    ->orWhere('user_agent', 'like', '%zaproxy%')
                    ->orWhere('user_agent', 'like', '%dirbuster%')
                    ->orWhere('user_agent', 'like', '%gobuster%')
                    ->orWhere('user_agent', 'like', '%ffuf%')
                    ->orWhere('user_agent', 'like', '%wpscan%')
                    ->orWhere('user_agent', 'like', '%masscan%')
                    ->orWhere('user_agent', 'like', '%nmap%')
                    ->orWhere('user_agent', 'like', '%zgrab%')
                    ->orWhere('user_agent', 'like', '%censys%')
                    ->orWhere('user_agent', 'like', '%shodan%');
            });
        } elseif ($botFilter === 'bot') {
            $query->where(function ($q): void {
                $q->where('user_agent', 'like', '%curl%')
                    ->orWhere('user_agent', 'like', '%python%')
                    ->orWhere('user_agent', 'like', '%guzzle%')
                    ->orWhere('user_agent', 'like', '%go-http%')
                    ->orWhere('user_agent', 'like', '%postman%')
                    ->orWhere('user_agent', 'like', '%wget%')
                    ->orWhere('user_agent', 'like', '%bot%')
                    ->orWhere('user_agent', 'like', '%crawler%')
                    ->orWhere('user_agent', 'like', '%spider%')
                    ->orWhere('user_agent', 'like', '%headless%');
            });
        } elseif ($botFilter === 'crawler') {
            $query->where(function ($q): void {
                $q->where('user_agent', 'like', '%googlebot%')
                    ->orWhere('user_agent', 'like', '%bingbot%')
                    ->orWhere('user_agent', 'like', '%yandex%')
                    ->orWhere('user_agent', 'like', '%baiduspider%')
                    ->orWhere('user_agent', 'like', '%duckduckbot%')
                    ->orWhere('user_agent', 'like', '%semrushbot%')
                    ->orWhere('user_agent', 'like', '%ahrefsbot%')
                    ->orWhere('user_agent', 'like', '%dotbot%')
                    ->orWhere('user_agent', 'like', '%mj12bot%')
                    ->orWhere('user_agent', 'like', '%bytespider%')
                    ->orWhere('user_agent', 'like', '%petalbot%');
            });
        } elseif ($botFilter === 'headless') {
            $query->where(function ($q): void {
                $q->whereNull('user_agent')
                    ->orWhere('user_agent', '')
                    ->orWhere('user_agent', '-')
                    ->orWhere('user_agent', '—');
            });
        } elseif ($botFilter === 'browser') {
            $query->whereNotNull('user_agent')
                ->where('user_agent', '!=', '')
                ->where('user_agent', '!=', '-')
                ->where('user_agent', '!=', '—')
                ->where('user_agent', 'not like', '%bot%')
                ->where('user_agent', 'not like', '%crawler%')
                ->where('user_agent', 'not like', '%spider%')
                ->where('user_agent', 'not like', '%curl%')
                ->where('user_agent', 'not like', '%python%')
                ->where('user_agent', 'not like', '%sqlmap%');
        }

        if ($dateFrom !== '') {
            try {
                $query->where(
                    'created_at',
                    '>=',
                    Carbon::parse($dateFrom)->startOfDay(),
                );
            } catch (\Throwable) {
            }
        }

        if ($dateTo !== '') {
            try {
                $query->where(
                    'created_at',
                    '<=',
                    Carbon::parse($dateTo)->endOfDay(),
                );
            } catch (\Throwable) {
            }
        }

        try {
            $security = $security ?? app(PasakService::class);
            $trend = $security->attackTrend($trendRange);
        } catch (\Throwable) {
            $trend = [
                'range' => $trendRange,
                'granularity' => $trendRange === 'year' ? 'month' : 'day',
                'total' => 0,
                'peak' => ['label' => '-', 'total' => 0],
                'points' => [],
            ];
        }

        $logs = $query->paginate($perPage)->withQueryString();

        $ipLocations = [];
        $activeBlockedIps = [];
        try {
            $loginService = app(PasakUserLoginService::class);
            $uniqueIps = $logs->pluck('ip_address')->unique()->filter()->all();
            foreach ($uniqueIps as $ip) {
                $ipLocations[$ip] = $loginService->resolveLocation($ip);
            }
            if (! empty($uniqueIps)) {
                $activeBlockedIps = PasakBlockedIp::query()
                    ->active()
                    ->whereIn('ip_address', $uniqueIps)
                    ->pluck('id', 'ip_address')
                    ->all();
            }
        } catch (\Throwable) {
        }

        return $this->dashboardResponse('pages.pasak.logs', [
            'logs' => $logs,
            'ipLocations' => $ipLocations,
            'activeBlockedIps' => $activeBlockedIps,
            'levels' => PasakLog::LEVELS,
            'trend' => $trend,
            'trendRange' => $trendRange,
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
                'bot_filter' => $botFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
                'trend_range' => $trendRange,
            ],
            'urls' => [
                'index' => route('pasak.dashboard.logs'),
                'show' => route('pasak.dashboard.logs.show', [
                    'id' => '__ID__',
                ]),
                'clear' => route('pasak.dashboard.logs.clear'),
                'destroy' => route('pasak.dashboard.logs.destroy', [
                    'id' => '__ID__',
                ]),
            ],
        ]);
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

        $location = null;
        try {
            $location = app(PasakUserLoginService::class)->resolveLocation($log->ip_address);
        } catch (\Throwable) {
        }

        return $this->dashboardResponse('pages.pasak.log-detail', [
            'log' => $log,
            'location' => $location,
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
                'show' => route('pasak.dashboard.logs.show', [
                    'id' => '__ID__',
                ]),
                'blockShow' => route('pasak.dashboard.blocked-ips.show', [
                    'id' => '__ID__',
                ]),
                'destroy' => route('pasak.dashboard.logs.destroy', [
                    'id' => $log->getKey(),
                ]),
            ],
        ]);
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
