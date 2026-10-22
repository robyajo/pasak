@extends('pages.pasak.layout', ['heading' => __('Security Overview'), 'subheading' => __('Ringkasan ancaman, blokir, dan aktivitas keamanan terbaru')])

@section('content')
@php($DV = \Internal\Pasak\Support\DashboardView::class)
@php($maxTrend = max(1, collect($trend['points'] ?? [])->max('total') ?? 1))
@php($maxAttackerHits = max(1, collect($attackers ?? [])->max('hits') ?? 1))

<div class="sec-grid-4">
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Logs today') }}</div>
        <div class="sec-stat-value">{{ number_format($stats['logs_today'] ?? 0) }}</div>
        <div class="sec-stat-desc">{{ __(':count total recorded', ['count' => number_format($stats['total_logs'] ?? 0)]) }}</div>
    </div>
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Critical threats today') }}</div>
        <div class="sec-stat-value sec-text-danger">{{ number_format($stats['critical_today'] ?? 0) }}</div>
        <div class="sec-stat-desc">{{ __('Requires immediate review') }}</div>
    </div>
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Active blocks') }}</div>
        <div class="sec-stat-value">{{ number_format($stats['active_blocks'] ?? 0) }}</div>
        <div class="sec-stat-desc">{{ __(':count total blocks', ['count' => number_format($stats['total_blocks'] ?? 0)]) }}</div>
    </div>
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Unique IPs today') }}</div>
        <div class="sec-stat-value">{{ number_format($stats['unique_ips_today'] ?? 0) }}</div>
        <div class="sec-stat-desc">{{ __(':count blocked attempts', ['count' => number_format($stats['blocked_attempts_today'] ?? 0)]) }}</div>
    </div>
</div>

<div class="sec-grid-2">
    <div class="sec-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div>
                <h3 class="sec-card-title">{{ __('Attack trend') }}</h3>
                <p class="sec-card-subtitle">
                    {{ __('Total :total · Peak :peak (:count)', ['total' => number_format($trend['total'] ?? 0), 'peak' => ($trend['peak'] ?? [])['label'] ?? '-', 'count' => number_format(($trend['peak'] ?? [])['total'] ?? 0)]) }}
                </p>
            </div>
            <div style="display: flex; gap: 6px;">
                @foreach (['week' => __('7 days'), 'month' => __('30 days'), 'year' => __('12 months')] as $key => $label)
                    <a href="{{ route('pasak.dashboard.overview', ['range' => $key]) }}"
                       class="sec-btn sec-btn-sm {{ ($range ?? 'week') === $key ? 'sec-btn-primary' : 'sec-btn-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="sec-chart-box">
            @foreach (($trend['points'] ?? []) as $index => $point)
                <div class="sec-chart-col">
                    <div class="sec-chart-bar"
                         style="height: {{ $DV::trendBarHeight($point['total'] ?? 0, $maxTrend) }}; animation-delay: {{ $index * 0.05 }}s;"
                         title="{{ $point['tooltip'] ?? '' }} · {{ $point['total'] ?? 0 }}"></div>
                    <span class="sec-chart-label">{{ $point['label'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="sec-card">
        <h3 class="sec-card-title" style="margin-bottom: 16px;">{{ __('Threats by severity (24h)') }}</h3>
        @php($levelTotal = max(1, array_sum($levels ?? [])))
        <div style="display: flex; flex-direction: column; gap: 14px;">
            @foreach (\Internal\Pasak\Models\SecurityLog::LEVELS as $index => $level)
                @php($count = ($levels ?? [])[$level] ?? 0)
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span class="sec-badge {{ $DV::levelClass($level) }}">{{ $DV::levelLabel($level) }}</span>
                        <span class="sec-font-mono" style="font-size: 13px; font-weight: 600;">{{ number_format($count) }}</span>
                    </div>
                    <div style="height: 6px; width: 100%; background: var(--sec-border-light); border-radius: 9999px; overflow: hidden;">
                        <div class="sec-severity-meter"
                             style="height: 100%; border-radius: 9999px; width: {{ $DV::severityPercent($count, $levelTotal) }}; background: {{ $DV::severityColor($level) }}; animation-delay: {{ $index * 0.1 }}s;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="sec-grid-2">
    {{-- Card 1: Top Attackers --}}
    <div class="sec-card sec-card-flush">
        <div class="sec-card-header">
            <div>
                <h3 class="sec-card-title">{{ __('Top attackers (24h)') }}</h3>
                <p class="sec-card-subtitle">{{ __('Sumber serangan terbanyak dalam 24 jam terakhir') }}</p>
            </div>
            @if (!empty($attackers))
                <span class="sec-badge sec-badge-low">{{ __(':count IPs', ['count' => count($attackers)]) }}</span>
            @endif
        </div>
        <div class="sec-table-wrap">
            <table class="sec-table">
                <thead>
                    <tr>
                        <th>{{ __('IP address') }}</th>
                        <th style="width: 110px;">{{ __('Hits') }}</th>
                        <th style="width: 75px; text-align: center;">{{ __('Level') }}</th>
                        <th style="width: 80px; text-align: right;">{{ __('Last seen') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($attackers ?? []) as $index => $attacker)
                        @php($ip = data_get($attacker, 'ip_address', '-'))
                        @php($hits = (int) data_get($attacker, 'hits', 0))
                        @php($lvl = (string) data_get($attacker, 'level', 'low'))
                        @php($seen = data_get($attacker, 'last_seen'))
                        <tr class="sec-animated-row" style="animation-delay: {{ $index * 0.06 }}s;">
                            <td class="sec-font-mono" style="font-weight: 600;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="sec-threat-dot l-{{ $lvl }}" title="{{ __('Tingkat ancaman: :level', ['level' => $lvl]) }}"></span>
                                    <a href="{{ route('pasak.dashboard.logs', ['search' => $ip]) }}" class="sec-ip-interactive" title="{{ __('Lihat log untuk :ip', ['ip' => $ip]) }}">
                                        {{ $ip }}
                                    </a>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="sec-font-mono" style="min-width: 24px; font-weight: 600;">{{ number_format($hits) }}</span>
                                    <div style="flex: 1; min-width: 40px; max-width: 70px; height: 5px; background: var(--sec-border-light); border-radius: 9999px; overflow: hidden;">
                                        <div class="sec-attack-ratio"
                                             style="height: 100%; border-radius: 9999px; width: {{ round(($hits / $maxAttackerHits) * 100) }}%; background: {{ $DV::severityColor($lvl) }}; animation-delay: {{ $index * 0.08 }}s;"></div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align: center;"><span class="sec-badge {{ $DV::levelClass($lvl) }}">{{ $DV::levelLabel($lvl) }}</span></td>
                            <td style="color: var(--sec-text-muted); font-size: 11px; white-space: nowrap; text-align: right;">
                                {{ $seen ? \Illuminate\Support\Carbon::parse($seen)->diffForHumans(null, true) : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--sec-text-muted); padding: 32px;">
                                {{ __('No threats recorded in the last 24 hours.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Card 2: Recent Real Events from Security Logs --}}
    <div class="sec-card sec-card-flush">
        <div class="sec-card-header">
            <div>
                <h3 class="sec-card-title">{{ __('Recent security events') }}</h3>
                <p class="sec-card-subtitle">{{ __('Aktivitas serangan real-time dari log keamanan') }}</p>
            </div>
            <a href="{{ route('pasak.dashboard.logs') }}" class="sec-btn sec-btn-secondary sec-btn-sm">
                {{ __('View all logs') }} →
            </a>
        </div>
        <div class="sec-table-wrap">
            <table class="sec-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">{{ __('Time') }}</th>
                        <th>{{ __('Attacker / Path') }}</th>
                        <th style="width: 75px; text-align: center;">{{ __('Level') }}</th>
                        <th style="width: 70px; text-align: right;">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($recentLogs ?? []) as $index => $log)
                        <tr class="sec-animated-row" style="animation-delay: {{ $index * 0.06 }}s;">
                            <td style="color: var(--sec-text-muted); font-size: 11px; white-space: nowrap;">
                                {{ $log->created_at ? $log->created_at->diffForHumans(null, true) : '-' }}
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span class="sec-threat-dot l-{{ $log->threat_level }}"></span>
                                    <a href="{{ route('pasak.dashboard.logs.show', $log->id) }}" class="sec-ip-interactive" style="font-weight: 600; font-family: ui-monospace, monospace; font-size: 12px;">
                                        {{ $log->ip_address }}
                                    </a>
                                </div>
                                <div style="font-family: ui-monospace, monospace; font-size: 11px; color: var(--sec-text-muted); max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $log->method }} {{ $log->path }}">
                                    {{ $log->method }} {{ $log->path }}
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <span class="sec-badge {{ $DV::levelClass($log->threat_level) }}">{{ $DV::levelLabel($log->threat_level) }}</span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                @if ($log->was_blocked)
                                    <span class="sec-badge sec-badge-critical" style="font-size: 10px; padding: 2px 6px;">{{ __('BLOCKED') }}</span>
                                @else
                                    <span class="sec-badge sec-badge-low" style="font-size: 10px; padding: 2px 6px;">{{ __('LOGGED') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--sec-text-muted); padding: 32px;">
                                {{ __('No security logs recorded yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    /* Animasi Bar Trend Serangan */
    .sec-chart-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        height: 100%;
        justify-content: flex-end;
    }
    .sec-chart-bar {
        width: 100%;
        border-radius: 4px 4px 0 0;
        transform-origin: bottom;
        animation: sec-bar-rise 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
        transition: opacity 0.2s ease, filter 0.2s ease;
    }
    .sec-chart-bar:hover {
        filter: brightness(1.15);
    }
    @keyframes sec-bar-rise {
        from { transform: scaleY(0); }
        to { transform: scaleY(1); }
    }

    /* Animasi Bar Meter Keparahan (Severity) */
    .sec-severity-meter {
        height: 100%;
        border-radius: 9999px;
        transform-origin: left;
        animation: sec-meter-expand 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    @keyframes sec-meter-expand {
        from { transform: scaleX(0); }
        to { transform: scaleX(1); }
    }

    /* Animasi Bar Proporsi Serangan Top Attackers */
    .sec-attack-ratio {
        height: 100%;
        border-radius: 9999px;
        transform-origin: left;
        animation: sec-meter-expand 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    /* Animasi Muncul Entri Tabel / Baris */
    .sec-animated-row {
        animation: sec-row-fade-in 0.4s ease-out both;
    }
    @keyframes sec-row-fade-in {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Indikator Pulse Ancaman Aktif */
    .sec-threat-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
    }
    .sec-threat-dot.l-critical {
        background: #ef4444;
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
        animation: sec-pulse-critical 2s infinite;
    }
    .sec-threat-dot.l-high {
        background: #f97316;
        box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.7);
        animation: sec-pulse-high 2s infinite;
    }
    .sec-threat-dot.l-medium {
        background: #eab308;
    }
    .sec-threat-dot.l-low {
        background: #34d399;
    }

    @keyframes sec-pulse-critical {
        0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
        100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    @keyframes sec-pulse-high {
        0% { box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.7); }
        70% { box-shadow: 0 0 0 5px rgba(249, 115, 22, 0); }
        100% { box-shadow: 0 0 0 0 rgba(249, 115, 22, 0); }
    }

    /* Pranala IP Interaktif */
    .sec-ip-interactive {
        color: var(--sec-text);
        text-decoration: none;
        transition: color 0.15s ease;
    }
    .sec-ip-interactive:hover {
        color: var(--sec-primary);
        text-decoration: underline;
    }
</style>
@endsection
