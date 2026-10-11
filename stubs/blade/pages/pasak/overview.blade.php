@extends('pages.pasak.layout', ['heading' => __('Security Overview'), 'subheading' => __('Ringkasan ancaman, blokir, dan aktivitas keamanan terbaru')])

@section('content')
@php($DV = \Internal\Pasak\Support\DashboardView::class)
@php($maxTrend = max(1, collect($trend['points'] ?? [])->max('total') ?? 1))

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
            @foreach (($trend['points'] ?? []) as $point)
                <div class="sec-chart-col">
                    <div class="sec-chart-bar" style="height: {{ $DV::trendBarHeight($point['total'] ?? 0, $maxTrend) }};" title="{{ $point['tooltip'] ?? '' }} · {{ $point['total'] ?? 0 }}"></div>
                    <span class="sec-chart-label">{{ $point['label'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="sec-card">
        <h3 class="sec-card-title" style="margin-bottom: 16px;">{{ __('Threats by severity (24h)') }}</h3>
        @php($levelTotal = max(1, array_sum($levels ?? [])))
        <div style="display: flex; flex-direction: column; gap: 14px;">
            @foreach (\Internal\Pasak\Models\SecurityLog::LEVELS as $level)
                @php($count = ($levels ?? [])[$level] ?? 0)
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span class="sec-badge {{ $DV::levelClass($level) }}">{{ $DV::levelLabel($level) }}</span>
                        <span class="sec-font-mono" style="font-size: 13px; font-weight: 600;">{{ number_format($count) }}</span>
                    </div>
                    <div style="height: 6px; width: 100%; background: var(--sec-border-light); border-radius: 9999px; overflow: hidden;">
                        <div style="height: 100%; border-radius: 9999px; width: {{ $DV::severityPercent($count, $levelTotal) }}; background: {{ $DV::severityColor($level) }};"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="sec-card sec-card-flush">
    <div class="sec-card-header">
        <h3 class="sec-card-title">{{ __('Top attackers (24h)') }}</h3>
    </div>
    <div class="sec-table-wrap">
        <table class="sec-table">
            <thead>
                <tr>
                    <th>{{ __('IP address') }}</th>
                    <th>{{ __('Hits') }}</th>
                    <th>{{ __('Highest level') }}</th>
                    <th>{{ __('Last seen') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($attackers ?? []) as $attacker)
                    <tr>
                        <td class="sec-font-mono" style="font-weight: 600;">{{ $attacker['ip_address'] ?? '-' }}</td>
                        <td class="sec-font-mono">{{ number_format((int) ($attacker['hits'] ?? 0)) }}</td>
                        <td><span class="sec-badge {{ $DV::levelClass((string) ($attacker['level'] ?? 'low')) }}">{{ $DV::levelLabel((string) ($attacker['level'] ?? 'low')) }}</span></td>
                        <td style="color: var(--sec-text-muted);">{{ isset($attacker['last_seen']) ? \Illuminate\Support\Carbon::parse($attacker['last_seen'])->diffForHumans() : '-' }}</td>
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
@endsection
