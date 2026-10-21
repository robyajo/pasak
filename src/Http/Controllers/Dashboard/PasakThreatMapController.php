<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Services\PasakUserLoginService;

/**
 * Threat map page for the Blade monitoring dashboard.
 *
 * Menggambar lintasan serangan dari lokasi geografis penyerang menuju
 * lokasi server yang dipantau. Lokasi IP memakai resolver geoIP berikut
 * cache 30 hari yang sama dengan pelacak sesi.
 */
class PasakThreatMapController extends Controller
{
    use RespondsWithPasakDashboard;

    public function __construct(
        protected PasakUserLoginService $loginService,
    ) {}

    public function index(Request $request, PasakService $security): View
    {
        $window = $this->windowHours();
        $start = now()->subHours($window);

        $origins = PasakLog::query()
            ->selectRaw(
                'ip_address, COUNT(*) as hits, MAX(created_at) as last_seen, '
                ."MAX(case threat_level when 'critical' then 4 when 'high' then 3 when 'medium' then 2 else 1 end) as weight"
            )
            ->where('created_at', '>=', $start)
            ->groupBy('ip_address')
            ->orderByDesc('hits')
            ->limit($this->maxOrigins())
            ->get()
            ->map(function ($row) use ($start): array {
                $geo = $this->locationFor((string) $row->ip_address);
                $sample = PasakLog::query()
                    ->where('ip_address', $row->ip_address)
                    ->where('created_at', '>=', $start)
                    ->orderByDesc('created_at')
                    ->limit(6)
                    ->get();

                return [
                    'ip' => (string) $row->ip_address,
                    'lat' => $geo['latitude'],
                    'lon' => $geo['longitude'],
                    'place' => $geo['city'],
                    'country' => $geo['country'],
                    'hits' => (int) $row->hits,
                    'weight' => (int) $row->weight,
                    'last_seen' => (string) $row->last_seen,
                    'samples' => $sample
                        ->map(fn (PasakLog $log): array => $this->logToArray($log))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return $this->dashboardResponse('pages.pasak.threat-map', [
            'payload' => [
                'target' => $this->target(),
                'window' => $window,
                'refresh' => $this->refreshSeconds(),
                'enforcement' => $security->enforcementEnabled(),
                'origins' => $origins,
                'feedUrl' => route('pasak.dashboard.threat-map.feed'),
            ],
            'stats' => $security->stats(),
        ]);
    }

    public function feed(Request $request, PasakService $security): JsonResponse
    {
        $start = now()->subHours($this->windowHours());

        $query = PasakLog::query()
            ->where('created_at', '>=', $start)
            ->orderByDesc('id');

        $after = (int) $request->query('after', 0);

        if ($after > 0) {
            $query->where('id', '>', $after);
        } else {
            $query->limit(20);
        }

        $logs = $query->limit(20)->get();

        $locations = [];

        foreach ($logs->unique('ip_address') as $log) {
            $ip = (string) $log->ip_address;
            $geo = $this->locationFor($ip);
            $locations[$ip] = [
                'lat' => $geo['latitude'],
                'lon' => $geo['longitude'],
                'place' => $geo['city'],
                'country' => $geo['country'],
            ];
        }

        return response()->json([
            'last_id' => (int) ($logs->first()->id ?? $after),
            'stats' => $security->stats(),
            'locations' => $locations,
            'logs' => $logs
                ->map(fn (PasakLog $log): array => $this->logToArray($log, true))
                ->values()
                ->all(),
        ]);
    }

    protected function logToArray(PasakLog $log, bool $withTime = false): array
    {
        return [
            'id' => $log->id,
            'ip' => (string) $log->ip_address,
            'level' => (string) $log->threat_level,
            'event' => (string) $log->event_type,
            'desc' => (string) ($log->rule_label ?: $log->event_type),
            'method' => $log->method ? (string) $log->method : null,
            'path' => mb_substr((string) $log->path, 0, 140),
            'blocked' => (bool) $log->was_blocked,
            'time' => $withTime
                ? $log->created_at?->toIso8601String()
                : null,
        ];
    }

    /**
     * @return array{name: string, lat: float, lon: float}
     */
    protected function target(): array
    {
        return [
            'name' => (string) config('pasak.dashboard.threat_map.target_name', 'Pekanbaru, Riau (ID)'),
            'lat' => (float) config('pasak.dashboard.threat_map.target_lat', 0.5071),
            'lon' => (float) config('pasak.dashboard.threat_map.target_lon', 101.4478),
        ];
    }

    /**
     * @return array{city: string|null, country: string|null, latitude: float|null, longitude: float|null}
     */
    protected function locationFor(string $ip): array
    {
        $geo = $this->loginService->resolveLocation($ip);

        return [
            'city' => $geo['city'] ?? null,
            'country' => $geo['country'] ?? null,
            'latitude' => $geo['latitude'] ?? null,
            'longitude' => $geo['longitude'] ?? null,
        ];
    }

    protected function windowHours(): int
    {
        return max(1, (int) config('pasak.dashboard.threat_map.window_hours', 24));
    }

    protected function maxOrigins(): int
    {
        return max(1, (int) config('pasak.dashboard.threat_map.max_origins', 10));
    }

    protected function refreshSeconds(): int
    {
        return max(3, (int) config('pasak.dashboard.threat_map.refresh_seconds', 8));
    }
}
