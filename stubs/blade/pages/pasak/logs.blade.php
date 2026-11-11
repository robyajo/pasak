@extends('pages.pasak.layout', ['heading' => __('Security Logs'), 'subheading' => __('Jejak audit ancaman, pola terdeteksi, dan aksi mitigasi')])

@section('page_support_key', 'logs')

@section('content')
@php
    $DV = \Internal\Pasak\Support\DashboardView::class;

    $trendRange = $trendRange ?? request()->query('trend_range', request()->query('range', 'week'));
    if (! in_array($trendRange, ['week', 'month', 'year'], true)) {
        $trendRange = 'week';
    }

    if (! isset($trend) || ! is_array($trend)) {
        try {
            $trend = app(\Internal\Pasak\Services\PasakService::class)->attackTrend($trendRange);
        } catch (\Throwable) {
            $trend = [
                'range' => $trendRange,
                'granularity' => $trendRange === 'year' ? 'month' : 'day',
                'total' => 0,
                'peak' => ['label' => '-', 'total' => 0],
                'points' => [],
            ];
        }
    }

    $trendPoints = $trend['points'] ?? [];
    $numPoints = count($trendPoints);
    $maxTrendValue = max(1, collect($trendPoints)->max('total') ?? 1);
    $yCeil = max(4, (int) ceil($maxTrendValue * 1.18));

    // Akumulasi total keparahan ancaman pada rentang terpilih
    $totalCritical = 0;
    $totalHigh = 0;
    $totalMedium = 0;
    $totalLow = 0;
    foreach ($trendPoints as $tp) {
        $totalCritical += (int) ($tp['levels']['critical'] ?? 0);
        $totalHigh += (int) ($tp['levels']['high'] ?? 0);
        $totalMedium += (int) ($tp['levels']['medium'] ?? 0);
        $totalLow += (int) ($tp['levels']['low'] ?? 0);
    }

    $avgTrend = $numPoints > 0 ? round(($trend['total'] ?? 0) / $numPoints, 1) : 0;

    // Dimensi SVG Line Chart Responsif
    $svgW = 840;
    $svgH = 210;
    $padL = 45;
    $padR = 25;
    $padT = 20;
    $padB = 35;
    $plotW = $svgW - $padL - $padR;
    $plotH = $svgH - $padT - $padB;
    $baselineY = $padT + $plotH;

    $coords = [];
    foreach ($trendPoints as $idx => $pt) {
        $tot = (int) ($pt['total'] ?? 0);
        if ($numPoints <= 1) {
            $cx = $padL + ($plotW / 2);
        } else {
            $cx = $padL + ($idx * ($plotW / ($numPoints - 1)));
        }
        $cy = $baselineY - (($tot / $yCeil) * $plotH);
        $coords[] = [
            'x' => round($cx, 1),
            'y' => round($cy, 1),
            'pt' => $pt,
            'idx' => $idx,
        ];
    }

    $linePointsStr = '';
    $areaPathStr = '';
    if (! empty($coords)) {
        $linePointsStr = implode(' ', array_map(fn($c) => "{$c['x']},{$c['y']}", $coords));
        $first = $coords[0];
        $last = $coords[count($coords) - 1];
        $areaPathStr = "M {$first['x']},{$baselineY} ";
        foreach ($coords as $c) {
            $areaPathStr .= "L {$c['x']},{$c['y']} ";
        }
        $areaPathStr .= "L {$last['x']},{$baselineY} Z";
    }
@endphp

{{-- Kartu Visual: Chart Tren Serangan (Line Chart) --}}
<div class="sec-card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <h3 class="sec-card-title" style="margin: 0;">{{ __('Attack trend') }}</h3>
                <span class="sec-badge sec-badge-critical" style="font-size: 11px; padding: 2px 7px;">
                    {{ $trendRange === 'week' ? __('Minggu (7 Hari)') : ($trendRange === 'month' ? __('Bulan (30 Hari)') : __('Tahun (12 Bulan)')) }}
                </span>
            </div>
            <p class="sec-card-subtitle" style="margin-top: 4px; font-size: 12px;">
                {{ __('Total :total ancaman · Puncak :peak (:count) · Rata-rata :avg / :gran', [
                    'total' => number_format($trend['total'] ?? 0),
                    'peak' => ($trend['peak'] ?? [])['label'] ?? '-',
                    'count' => number_format(($trend['peak'] ?? [])['total'] ?? 0),
                    'avg' => $avgTrend,
                    'gran' => $trend['granularity'] === 'month' ? __('bulan') : __('hari')
                ]) }}
            </p>
        </div>

        {{-- Filter Rentang Waktu: Minggu, Bulan, Tahun --}}
        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            @foreach (['week' => __('Minggu'), 'month' => __('Bulan'), 'year' => __('Tahun')] as $key => $label)
                <a href="{{ request()->fullUrlWithQuery(['trend_range' => $key]) }}"
                   class="sec-btn sec-btn-sm {{ $trendRange === $key ? 'sec-btn-primary' : 'sec-btn-secondary' }}"
                   style="font-size: 12px; padding: 5px 12px;">
                   {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Indikator Keparahan --}}
    <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 12px; font-size: 11px; color: var(--sec-text-muted); flex-wrap: wrap;">
        <span style="font-weight: 600; color: var(--sec-text);">{{ __('Rincian:') }}</span>
        <span style="display: inline-flex; align-items: center; gap: 4px;">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 9999px; background: var(--sec-danger);"></span>
            {{ __('Critical') }}: <strong style="color: var(--sec-text);">{{ number_format($totalCritical) }}</strong>
        </span>
        <span style="display: inline-flex; align-items: center; gap: 4px;">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 9999px; background: var(--sec-warning);"></span>
            {{ __('High') }}: <strong style="color: var(--sec-text);">{{ number_format($totalHigh) }}</strong>
        </span>
        <span style="display: inline-flex; align-items: center; gap: 4px;">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 9999px; background: #d97706;"></span>
            {{ __('Medium') }}: <strong style="color: var(--sec-text);">{{ number_format($totalMedium) }}</strong>
        </span>
        <span style="display: inline-flex; align-items: center; gap: 4px;">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 9999px; background: var(--sec-text-subtle);"></span>
            {{ __('Low') }}: <strong style="color: var(--sec-text);">{{ number_format($totalLow) }}</strong>
        </span>
    </div>

    {{-- Kanvas Line Chart Berbasis Vektor SVG (Zero-Dependency) --}}
    <div style="position: relative; width: 100%; overflow-x: auto;">
        <svg viewBox="0 0 {{ $svgW }} {{ $svgH }}" style="width: 100%; height: auto; min-width: 580px; display: block; overflow: visible;">
            <defs>
                {{-- Gradien Area di Bawah Garis Tren --}}
                <linearGradient id="pasakLineGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="var(--sec-danger)" stop-opacity="0.32" />
                    <stop offset="75%" stop-color="var(--sec-danger)" stop-opacity="0.06" />
                    <stop offset="100%" stop-color="var(--sec-danger)" stop-opacity="0.0" />
                </linearGradient>
                {{-- Gradien Garis Stroke --}}
                <linearGradient id="pasakStrokeGrad" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%" stop-color="#ef4444" />
                    <stop offset="60%" stop-color="#f59e0b" />
                    <stop offset="100%" stop-color="#dc2626" />
                </linearGradient>
            </defs>

            {{-- Garis Grid Horizontal & Label Sumbu Y --}}
            @php
                $yLevels = [
                    ['val' => $yCeil, 'y' => $padT],
                    ['val' => (int) round($yCeil * 0.66), 'y' => $padT + ($plotH * 0.33)],
                    ['val' => (int) round($yCeil * 0.33), 'y' => $padT + ($plotH * 0.66)],
                    ['val' => 0, 'y' => $baselineY],
                ];
            @endphp
            @foreach ($yLevels as $gl)
                <line x1="{{ $padL }}" y1="{{ $gl['y'] }}" x2="{{ $svgW - $padR }}" y2="{{ $gl['y'] }}"
                      stroke="var(--sec-border-light)" stroke-dasharray="{{ $gl['val'] === 0 ? 'none' : '4 4' }}" stroke-width="1" />
                <text x="{{ $padL - 8 }}" y="{{ $gl['y'] + 4 }}" fill="var(--sec-text-subtle)" font-size="10" font-family="var(--sec-font-mono)" text-anchor="end">
                    {{ $gl['val'] }}
                </text>
            @endforeach

            @if (! empty($coords))
                {{-- Area Berisi Gradien --}}
                <path d="{{ $areaPathStr }}" fill="url(#pasakLineGrad)" />

                {{-- Garis Utama Tren Serangan (Line Chart) --}}
                <polyline points="{{ $linePointsStr }}" fill="none" stroke="url(#pasakStrokeGrad)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

                {{-- Titik Data Interaktif (Nodes) & Label Sumbu X --}}
                @foreach ($coords as $c)
                    @php
                        $pt = $c['pt'];
                        $isPeak = ($pt['total'] ?? 0) > 0 && ($pt['total'] ?? 0) === (($trend['peak'] ?? [])['total'] ?? -1);
                        // Tentukan apakah label tanggal sumbu X perlu ditampilkan agar tidak bertumpukan
                        $showLabel = true;
                        if ($trendRange === 'month') {
                            $showLabel = ($c['idx'] % 4 === 0) || ($c['idx'] === $numPoints - 1);
                        }
                    @endphp

                    {{-- Lingkaran Titik Interaktif --}}
                    <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="{{ $isPeak ? '5.5' : '4' }}"
                            fill="var(--sec-card)" stroke="{{ $isPeak ? '#ef4444' : 'var(--sec-danger)' }}" stroke-width="{{ $isPeak ? '3' : '2.5' }}"
                            style="transition: all 0.2s; cursor: pointer;"
                            class="sec-chart-node">
                        <title>{{ $pt['tooltip'] ?? $pt['label'] }}: {{ $pt['total'] }} {{ __('serangan') }} (Critical: {{ $pt['levels']['critical'] ?? 0 }}, High: {{ $pt['levels']['high'] ?? 0 }}, Med: {{ $pt['levels']['medium'] ?? 0 }}, Low: {{ $pt['levels']['low'] ?? 0 }})</title>
                    </circle>

                    {{-- Label Tanggal / Bulan di Bawah Baseline --}}
                    @if ($showLabel)
                        <text x="{{ $c['x'] }}" y="{{ $baselineY + 18 }}" fill="var(--sec-text-subtle)" font-size="10" font-family="var(--sec-font)" text-anchor="middle">
                            {{ $pt['label'] ?? '' }}
                        </text>
                    @endif
                @endforeach
            @else
                {{-- Status Kosong --}}
                <text x="{{ $svgW / 2 }}" y="{{ $padT + ($plotH / 2) }}" fill="var(--sec-text-subtle)" font-size="12" font-family="var(--sec-font)" text-anchor="middle">
                    {{ __('Belum ada aktivitas ancaman pada rentang ini.') }}
                </text>
            @endif
        </svg>
    </div>
</div>

<div class="sec-card">
    <form method="GET" action="{{ route('pasak.dashboard.logs') }}" class="sec-toolbar" style="margin-bottom: 0;">
        {{-- Pertahankan pilihan rentang tren serangan saat filter log diterapkan --}}
        <input type="hidden" name="trend_range" value="{{ $trendRange }}">

        <div class="sec-toolbar-group" style="flex: 1;">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search path, evidence, IP...') }}" class="sec-input" style="min-width: 200px; flex: 1;">
            <select name="level" class="sec-select">
                <option value="">{{ __('All levels') }}</option>
                @foreach (($levels ?? []) as $lvl)
                    <option value="{{ $lvl }}" @selected(($filters['level'] ?? '') === $lvl)>{{ $DV::levelLabel($lvl) }}</option>
                @endforeach
            </select>
            <select name="event_type" class="sec-select">
                <option value="">{{ __('All events') }}</option>
                @foreach (($eventTypes ?? []) as $type)
                    <option value="{{ $type }}" @selected(($filters['event_type'] ?? '') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            <select name="bot_filter" class="sec-select">
                <option value="">{{ __('Semua Klien / Bot') }}</option>
                <option value="scanner" @selected(($filters['bot_filter'] ?? '') === 'scanner')>🚨 {{ __('Scanner / Exploit Tool') }}</option>
                <option value="bot" @selected(($filters['bot_filter'] ?? '') === 'bot')>🤖 {{ __('Bot Otomatis / Script') }}</option>
                <option value="crawler" @selected(($filters['bot_filter'] ?? '') === 'crawler')>🕷️ {{ __('Web Crawler / Indexer') }}</option>
                <option value="headless" @selected(($filters['bot_filter'] ?? '') === 'headless')>🕵️ {{ __('Tanpa UA / Anonymized') }}</option>
                <option value="browser" @selected(($filters['bot_filter'] ?? '') === 'browser')>💻 {{ __('Browser Standar') }}</option>
            </select>
            <div style="display: inline-flex; align-items: center; gap: 6px;">
                <label for="filter_date_from" style="font-size: 12px; color: var(--sec-text-muted); font-weight: 500;">{{ __('From') }}:</label>
                <input id="filter_date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="sec-input" style="font-size: 13px;">
            </div>
            <div style="display: inline-flex; align-items: center; gap: 6px;">
                <label for="filter_date_to" style="font-size: 12px; color: var(--sec-text-muted); font-weight: 500;">{{ __('To') }}:</label>
                <input id="filter_date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="sec-input" style="font-size: 13px;">
            </div>
            <div style="display: inline-flex; align-items: center; gap: 6px;">
                <label for="filter_per_page" style="font-size: 12px; color: var(--sec-text-muted); font-weight: 500;">{{ __('Show') }}:</label>
                <select id="filter_per_page" name="per_page" class="sec-select" onchange="this.form.submit()" style="font-size: 13px;">
                    @foreach ([10, 25, 50, 100] as $n)
                        <option value="{{ $n }}" @selected(($filters['per_page'] ?? 25) == $n)>{{ $n }} / {{ __('page') }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="sec-btn sec-btn-primary">{{ __('Filter') }}</button>
            <a href="{{ route('pasak.dashboard.logs', ['trend_range' => $trendRange]) }}" class="sec-btn sec-btn-secondary">{{ __('Reset') }}</a>
        </div>
    </form>
</div>

<div class="sec-card sec-card-flush">
    <div class="sec-card-header">
        <h3 class="sec-card-title">{{ __('Entries') }} ({{ number_format($logs->total()) }})</h3>
        <form method="POST" action="{{ route('pasak.dashboard.logs.clear') }}" data-sec-confirm="{{ __('Kosongkan seluruh riwayat log keamanan? Tindakan ini tidak dapat dibatalkan.') }}" data-confirm-title="{{ __('Konfirmasi Bersihkan Log') }}" data-confirm-type="danger" data-confirm-btn="{{ __('Ya, Kosongkan Semua') }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="sec-btn sec-btn-danger sec-btn-sm">{{ __('Clear all') }}</button>
        </form>
    </div>
    <div class="sec-table-wrap">
        <table class="sec-table">
            <thead>
                <tr>
                    <th>{{ __('Time') }}</th>
                    <th>{{ __('IP & Klien') }}</th>
                    <th>{{ __('Asal / Lokasi') }}</th>
                    <th>{{ __('Event') }}</th>
                    <th>{{ __('Level') }}</th>
                    <th>{{ __('Path') }}</th>
                    <th>{{ __('Blocked') }}</th>
                    <th style="text-align: right;">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $loc = $ipLocations[$log->ip_address] ?? $DV::resolveLocation($log->ip_address);
                        $flag = $DV::countryFlag($loc['country_code'] ?? null);
                        $countryName = $loc['country'] ?? null;
                        $regionName = $loc['region'] ?? null;
                        $cityName = $loc['city'] ?? null;
                        $locDetail = implode(", ", array_filter([$cityName, $regionName]));
                        $activeBlockedList = $activeBlockedIps ?? [];
                        $isBlocked = (bool) $log->was_blocked || ! empty($activeBlockedList[$log->ip_address]);
                        $blockedRecordId = $activeBlockedList[$log->ip_address] ?? null;
                    @endphp
                    <tr>
                        <td class="sec-font-mono" style="font-size: 12px; white-space: nowrap;" title="{{ $DV::diffForHumans($log->created_at) }}">{{ $DV::formatDate($log->created_at, 'Y-m-d H:i:s') }}</td>
                        @php
                            $attacker = $DV::attackerInfo($log->user_agent, $log->event_type);
                        @endphp
                        <td style="font-size: 12.5px;">
                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                <div style="display: inline-flex; align-items: center; gap: 6px;" class="sec-font-mono">
                                    <span style="font-weight: 600;">{{ $log->ip_address }}</span>
                                    @if (! $isBlocked)
                                        <button type="button" class="sec-btn sec-btn-ghost sec-btn-sm" style="padding: 2px 4px; font-size: 11px; line-height: 1;" title="{{ __('Blokir IP ini') }}" onclick="openBlockModal({
                                            ip: '{{ $log->ip_address }}',
                                            device_id: '{{ $log->device_id ?? "" }}',
                                            local_ip: '{{ $log->local_ip ?? "" }}',
                                            reason: '{{ addslashes($log->event_type . ($log->path ? " " . __("pada") . " " . $log->path : "")) }}'
                                        })">🚫</button>
                                    @endif
                                </div>
                                <div style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px;" title="{{ $attacker['raw_ua'] ?: __('Tanpa User-Agent') }}">
                                    <span>{{ $attacker['icon'] }}</span>
                                    <span class="sec-badge {{ $attacker['badge_class'] }}" style="padding: 1px 6px; font-size: 10px; font-weight: 600; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        {{ $attacker['name'] }}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px;">
                                <span style="font-size: 17px; line-height: 1;" title="{{ $countryName ?? __('Tidak diketahui') }}">{{ $flag }}</span>
                                <div style="display: flex; flex-direction: column; min-width: 0; line-height: 1.25;">
                                    <span style="font-weight: 600; color: var(--sec-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;" title="{{ $countryName ?? "-" }}">
                                        {{ $countryName ?? "-" }}
                                    </span>
                                    @if (! empty($locDetail))
                                        <span style="font-size: 11px; color: var(--sec-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;" title="{{ $locDetail }}">
                                            {{ $locDetail }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>{{ $log->event_type }}</td>
                        <td><span class="sec-badge {{ $DV::levelClass($log->threat_level) }}">{{ $DV::levelLabel($log->threat_level) }}</span></td>
                        <td class="sec-font-mono" style="font-size: 12px; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $log->path }}">{{ $log->path }}</td>
                        <td>
                            @if ($isBlocked)
                                <span class="sec-badge sec-badge-critical" style="font-size: 11px;">{{ __('Yes') }}</span>
                            @else
                                <span class="sec-badge sec-badge-low" style="font-size: 11px;">{{ __('No') }}</span>
                            @endif
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="{{ route('pasak.dashboard.logs.show', $log->id) }}" class="sec-btn sec-btn-ghost sec-btn-sm">{{ __('Detail') }}</a>
                            @if (! $isBlocked)
                                <button type="button" class="sec-btn sec-btn-danger sec-btn-sm" style="display: inline-flex; align-items: center; gap: 4px;" onclick="openBlockModal({
                                    ip: '{{ $log->ip_address }}',
                                    device_id: '{{ $log->device_id ?? "" }}',
                                    local_ip: '{{ $log->local_ip ?? "" }}',
                                    reason: '{{ addslashes($log->event_type . ($log->path ? " " . __("pada") . " " . $log->path : "")) }}'
                                })">
                                    <span>🚫</span>
                                    <span>{{ __('Blokir') }}</span>
                                </button>
                            @else
                                @if ($blockedRecordId)
                                    <a href="{{ route('pasak.dashboard.blocked-ips.show', $blockedRecordId) }}" class="sec-badge sec-badge-critical" style="text-decoration: none; display: inline-flex; align-items: center; gap: 3px; font-size: 11px; padding: 4px 8px; vertical-align: middle;" title="{{ __('IP ini sudah diblokir. Klik untuk melihat detail pemblokiran.') }}">
                                        <span>🛡️</span>
                                        <span>{{ __('Diblokir') }}</span>
                                    </a>
                                @else
                                    <span class="sec-badge sec-badge-critical" style="display: inline-flex; align-items: center; gap: 3px; font-size: 11px; padding: 4px 8px; vertical-align: middle;" title="{{ __('Aktivitas ancaman ini sudah diblokir oleh sistem.') }}">
                                        <span>🛡️</span>
                                        <span>{{ __('Diblokir') }}</span>
                                    </span>
                                @endif
                            @endif
                            <form method="POST" action="{{ route('pasak.dashboard.logs.destroy', $log->id) }}" style="display: inline;" data-sec-confirm="{{ __('Hapus entri log ancaman ini secara permanen?') }}" data-confirm-title="{{ __('Konfirmasi Hapus Log') }}" data-confirm-type="danger" data-confirm-btn="{{ __('Ya, Hapus') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sec-btn sec-btn-ghost sec-btn-sm" title="{{ __('Hapus log') }}">🗑️</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--sec-text-muted); padding: 32px;">{{ __('No logs found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="sec-datatable-footer">
        <div>
            @if ($logs->total() > 0)
                {{ __('Showing') }} <span style="font-weight: 600; color: var(--sec-text);">{{ $logs->firstItem() }}</span>
                {{ __('to') }} <span style="font-weight: 600; color: var(--sec-text);">{{ $logs->lastItem() }}</span>
                {{ __('of') }} <span style="font-weight: 600; color: var(--sec-text);">{{ number_format($logs->total()) }}</span> {{ __('entries') }}
            @else
                {{ __('Showing 0 entries') }}
            @endif
        </div>
        <div>
            {{ $logs->links('pages.pasak.pagination') }}
        </div>
    </div>
</div>

{{-- Modal Dialog: Blokir Alamat IP --}}
<div class="sec-modal-backdrop" id="blockIpModal" hidden style="display: none; align-items: center; justify-content: center; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 99999; padding: 16px;">
    <div class="sec-modal-box" role="dialog" aria-modal="true" aria-labelledby="blockModalTitle" style="max-width: 520px; width: 100%; border-radius: 12px; background: var(--sec-card); border: 1px solid var(--sec-border); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.2); overflow: hidden;">
        {{-- Header Modal --}}
        <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 20px 24px 0 24px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px; line-height: 1;">🚫</span>
                <h3 id="blockModalTitle" style="font-size: 18px; font-weight: 700; color: var(--sec-text); margin: 0;">
                    {{ __('Blokir Alamat IP') }}
                </h3>
            </div>
            <button type="button" onclick="closeBlockModal()" aria-label="Close" style="border: none; background: transparent; font-size: 18px; color: var(--sec-text-muted); cursor: pointer; padding: 4px; line-height: 1; border-radius: 6px;">
                ✕
            </button>
        </div>

        <p style="padding: 6px 24px 0 24px; margin: 0 0 16px 0; font-size: 13px; color: var(--sec-text-muted); line-height: 1.5;">
            {{ __('Semua permintaan dari IP ini akan ditolak (HTTP 403) sampai blokir dibuka atau kedaluwarsa.') }}
        </p>

        {{-- Form Blokir IP --}}
        <form method="POST" action="{{ route('pasak.dashboard.blocked-ips.store') }}" style="padding: 0 24px 20px 24px;">
            @csrf
            <input type="hidden" id="blockModalScope" name="block_scope" value="ip">
            <input type="hidden" id="blockModalDeviceId" name="device_id" value="">
            <input type="hidden" id="blockModalLocalIp" name="local_ip" value="">

            {{-- Alamat IP --}}
            <div style="margin-bottom: 14px;">
                <label class="sec-label" for="blockModalIp" style="font-weight: 600; font-size: 13px; color: var(--sec-text); margin-bottom: 6px; display: block;">
                    {{ __('Alamat IP') }}
                </label>
                <input type="text" id="blockModalIp" name="ip_address" required class="sec-input" style="width: 100%; font-family: var(--sec-font-mono); font-size: 13.5px; padding: 9px 12px; border-radius: 8px;" placeholder="203.0.113.50">
            </div>

            {{-- Cakupan Pemblokiran --}}
            <div style="margin-bottom: 14px;">
                <label class="sec-label" style="font-weight: 600; font-size: 13px; color: var(--sec-text); margin-bottom: 6px; display: block;">
                    {{ __('Cakupan Pemblokiran') }}
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    {{-- Card 1: Hanya Perangkat --}}
                    <div id="scopeCardDevice" onclick="selectBlockScope('device')" style="cursor: pointer; border: 1.5px solid var(--sec-border); border-radius: 10px; padding: 12px; background: var(--sec-card); transition: all 0.2s ease;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="20" x2="22" y2="20"></line></svg>
                            <span style="font-size: 13px; font-weight: 700; color: #2563eb;">{{ __('Hanya Perangkat') }}</span>
                        </div>
                        <div style="font-size: 11px; color: var(--sec-text-muted); line-height: 1.4;">
                            {{ __('Aman untuk Wi-Fi / NAT. Pengguna lain di IP yang sama tidak terblokir.') }}
                        </div>
                    </div>

                    {{-- Card 2: Seluruh IP Router --}}
                    <div id="scopeCardIp" onclick="selectBlockScope('ip')" style="cursor: pointer; border: 2px solid #ef4444; border-radius: 10px; padding: 12px; background: rgba(239, 68, 68, 0.05); transition: all 0.2s ease;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><circle cx="12" cy="5" r="3"></circle><circle cx="6" cy="19" r="3"></circle><circle cx="18" cy="19" r="3"></circle><path d="M12 8v4m0 0l-6 4m6-4l6 4"></path></svg>
                            <span style="font-size: 13px; font-weight: 700; color: #dc2626;">{{ __('Seluruh IP Router') }}</span>
                        </div>
                        <div style="font-size: 11px; color: var(--sec-text-muted); line-height: 1.4;">
                            {{ __('Memblokir semua perangkat dari alamat IP publik ini.') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Durasi Blokir --}}
            <div style="margin-bottom: 14px;">
                <label class="sec-label" for="blockModalDuration" style="font-weight: 600; font-size: 13px; color: var(--sec-text); margin-bottom: 6px; display: block;">
                    {{ __('Durasi Blokir') }}
                </label>
                <select id="blockModalDuration" name="duration_hours" class="sec-select" style="width: 100%; padding: 9px 12px; font-size: 13.5px; border-radius: 8px;">
                    <option value="1">{{ __('1 Jam') }}</option>
                    <option value="6">{{ __('6 Jam') }}</option>
                    <option value="12">{{ __('12 Jam') }}</option>
                    <option value="24" selected>{{ __('24 Jam') }}</option>
                    <option value="72">{{ __('3 Hari (72 Jam)') }}</option>
                    <option value="168">{{ __('7 Hari (1 Minggu)') }}</option>
                    <option value="720">{{ __('30 Hari (1 Bulan)') }}</option>
                    <option value="0">{{ __('Permanen') }}</option>
                </select>
            </div>

            {{-- Alasan --}}
            <div style="margin-bottom: 14px;">
                <label class="sec-label" for="blockModalReason" style="font-weight: 600; font-size: 13px; color: var(--sec-text); margin-bottom: 6px; display: block;">
                    {{ __('Alasan') }}
                </label>
                <input type="text" id="blockModalReason" name="reason" class="sec-input" style="width: 100%; padding: 9px 12px; font-size: 13.5px; border-radius: 8px;" placeholder="{{ __('Alasan pemblokiran...') }}">
            </div>

            {{-- Catatan Internal --}}
            <div style="margin-bottom: 18px;">
                <label class="sec-label" for="blockModalNotes" style="font-weight: 600; font-size: 13px; color: var(--sec-text); margin-bottom: 6px; display: block;">
                    {{ __('Catatan Internal') }}
                </label>
                <textarea id="blockModalNotes" name="notes" rows="2" class="sec-input" style="width: 100%; padding: 9px 12px; font-size: 13px; border-radius: 8px; resize: vertical;" placeholder="{{ __('Opsional, misalnya nomor tiket atau hasil verifikasi.') }}"></textarea>
            </div>

            {{-- Footer Tombol --}}
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; padding-top: 14px; border-top: 1px solid var(--sec-border-light);">
                <button type="button" class="sec-btn sec-btn-secondary" onclick="closeBlockModal()" style="border-radius: 8px; padding: 8px 18px; font-size: 13.5px;">
                    {{ __('Batal') }}
                </button>
                <button type="submit" id="blockModalSubmitBtn" style="border: 1px solid #fca5a5; background: #fee2e2; color: #dc2626; border-radius: 8px; font-size: 13.5px; font-weight: 600; padding: 8px 18px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
                    <span style="font-size: 14px;">🚫</span>
                    <span id="blockModalSubmitLabel">{{ __('Blokir Seluruh IP') }}</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function selectBlockScope(scope) {
        var hiddenScope = document.getElementById('blockModalScope');
        var cardDevice = document.getElementById('scopeCardDevice');
        var cardIp = document.getElementById('scopeCardIp');
        var submitLabel = document.getElementById('blockModalSubmitLabel');
        var submitBtn = document.getElementById('blockModalSubmitBtn');

        if (hiddenScope) hiddenScope.value = scope;

        if (scope === 'device') {
            if (cardDevice) {
                cardDevice.style.border = '2px solid #2563eb';
                cardDevice.style.background = 'rgba(37, 99, 235, 0.08)';
            }
            if (cardIp) {
                cardIp.style.border = '1.5px solid var(--sec-border)';
                cardIp.style.background = 'var(--sec-card)';
            }
            if (submitLabel) submitLabel.textContent = '{{ __('Blokir Perangkat') }}';
            if (submitBtn) {
                submitBtn.style.background = '#dbeafe';
                submitBtn.style.borderColor = '#93c5fd';
                submitBtn.style.color = '#1d4ed8';
            }
        } else {
            if (cardIp) {
                cardIp.style.border = '2px solid #ef4444';
                cardIp.style.background = 'rgba(239, 68, 68, 0.05)';
            }
            if (cardDevice) {
                cardDevice.style.border = '1.5px solid var(--sec-border)';
                cardDevice.style.background = 'var(--sec-card)';
            }
            if (submitLabel) submitLabel.textContent = '{{ __('Blokir Seluruh IP') }}';
            if (submitBtn) {
                submitBtn.style.background = '#fee2e2';
                submitBtn.style.borderColor = '#fca5a5';
                submitBtn.style.color = '#dc2626';
            }
        }
    }

    function openBlockModal(data) {
        var modal = document.getElementById('blockIpModal');
        if (!modal) return;

        var inputIp = document.getElementById('blockModalIp');
        var inputReason = document.getElementById('blockModalReason');
        var inputNotes = document.getElementById('blockModalNotes');
        var inputDevice = document.getElementById('blockModalDeviceId');
        var inputLocalIp = document.getElementById('blockModalLocalIp');

        if (inputIp) inputIp.value = data.ip || '';
        if (inputReason) inputReason.value = data.reason || '';
        if (inputNotes) inputNotes.value = '';
        if (inputDevice) inputDevice.value = data.device_id || '';
        if (inputLocalIp) inputLocalIp.value = data.local_ip || '';

        // Default ke Seluruh IP Router kecuali terdeteksi ID perangkat
        selectBlockScope(data.device_id ? 'device' : 'ip');

        modal.hidden = false;
        modal.style.display = 'flex';
    }

    function closeBlockModal() {
        var modal = document.getElementById('blockIpModal');
        if (modal) {
            modal.hidden = true;
            modal.style.display = 'none';
        }
    }

    // Tutup saat klik di luar kotak modal
    var blockModalBackdrop = document.getElementById('blockIpModal');
    if (blockModalBackdrop) {
        blockModalBackdrop.addEventListener('click', function(e) {
            if (e.target === blockModalBackdrop) closeBlockModal();
        });
    }

    // Tutup saat menekan tombol Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeBlockModal();
    });
</script>
@endsection
