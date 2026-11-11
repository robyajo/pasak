@extends('pages.pasak.layout', ['heading' => __('Log entry #:id', ['id' => $log->id]), 'subheading' => $log->event_type])

@section('page_support_key', 'log-detail')

@section('content')
@php
    $DV = \Internal\Pasak\Support\DashboardView::class;
@endphp

<a href="{{ $urls['index'] }}" class="sec-back-link">← {{ __('Back to logs') }}</a>

<div class="sec-card">
    @php
        $loc = $location ?? $DV::resolveLocation($log->ip_address);
        $countryName = $loc['country'] ?? null;
        $countryCode = $loc['country_code'] ?? null;
        $regionName = $loc['region'] ?? null;
        $cityName = $loc['city'] ?? null;
        $locDetail = implode(', ', array_filter([$cityName, $regionName]));
        $attacker = $DV::attackerInfo($log->user_agent, $log->event_type);
    @endphp
    <div class="sec-detail-grid">
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Time') }}</div>
            <div class="sec-detail-value sec-font-mono">{{ $DV::formatDate($log->created_at, 'd M Y H:i:s') }} <span style="font-size: 12px; color: var(--sec-text-muted); font-weight: normal; margin-left: 6px;">({{ $DV::diffForHumans($log->created_at) }})</span></div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('IP address') }}</div>
            <div class="sec-detail-value sec-font-mono" style="font-weight: 600;">{{ $log->ip_address }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Negara Penyerang') }}</div>
            <div class="sec-detail-value" style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px; line-height: 1;">{{ $DV::countryFlag($countryCode) }}</span>
                <span style="font-weight: 600;">{{ $countryName ?? __('Tidak diketahui') }}</span>
                @if ($countryCode)
                    <span class="sec-badge sec-badge-low" style="font-size: 11px;">{{ strtoupper($countryCode) }}</span>
                @endif
            </div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Provinsi / Wilayah') }}</div>
            <div class="sec-detail-value">{{ ! empty($locDetail) ? $locDetail : '—' }}</div>
        </div>
        @if (! empty($loc['isp']))
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('ISP / Jaringan') }}</div>
                <div class="sec-detail-value">{{ $loc['isp'] }}</div>
            </div>
        @endif
        @if (! empty($loc['latitude']) && ! empty($loc['longitude']))
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Koordinat Geo') }}</div>
                <div class="sec-detail-value sec-font-mono" style="font-size: 12px;">{{ number_format((float) $loc['latitude'], 4) }}°, {{ number_format((float) $loc['longitude'], 4) }}°</div>
            </div>
        @endif
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Profil Penyerang') }}</div>
            <div class="sec-detail-value" style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px; line-height: 1;">{{ $attacker['icon'] }}</span>
                <span class="sec-badge {{ $attacker['badge_class'] }}" style="font-size: 12px; font-weight: 600;">
                    {{ $attacker['name'] }}
                </span>
            </div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Kategori Klien') }}</div>
            <div class="sec-detail-value" style="display: flex; align-items: center; gap: 6px;">
                <span>{{ $attacker['category_label'] }}</span>
                @if ($attacker['is_bot'])
                    <span class="sec-badge sec-badge-critical" style="font-size: 10.5px;">{{ __('BOT / OTOMASI') }}</span>
                @else
                    <span class="sec-badge sec-badge-low" style="font-size: 10.5px;">{{ __('HUMAN / BROWSER') }}</span>
                @endif
            </div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Threat level') }}</div>
            <div class="sec-detail-value"><span class="sec-badge {{ $DV::levelClass($log->threat_level) }}">{{ $DV::levelLabel($log->threat_level) }}</span></div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Blocked') }}</div>
            <div class="sec-detail-value">{{ $log->was_blocked ? __('Yes') : __('No') }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Method') }}</div>
            <div class="sec-detail-value sec-font-mono">{{ $log->method ?? '—' }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Event type') }}</div>
            <div class="sec-detail-value">{{ $log->event_type }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Rule') }}</div>
            <div class="sec-detail-value">{{ $log->rule_label ?? '—' }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Action taken') }}</div>
            <div class="sec-detail-value">{{ $log->action_taken ?? '—' }}</div>
        </div>
        @if ($log->device_id || $log->local_ip)
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Device') }}</div>
                <div class="sec-detail-value sec-font-mono">{{ $log->device_id ?? '—' }} / {{ $log->local_ip ?? '—' }}</div>
            </div>
        @endif
    </div>
</div>

<div class="sec-card">
    <div class="sec-card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="sec-card-title">{{ __('Request path') }}</h3>
    </div>
    <div class="sec-card-body" style="padding-top: 12px;">
        <pre class="sec-code-block">{{ $log->full_url ?? $log->path ?? '—' }}</pre>
    </div>
</div>

@if ($log->evidence)
    <div class="sec-card">
        <div class="sec-card-header" style="border-bottom: none; padding-bottom: 0;">
            <h3 class="sec-card-title">{{ __('Evidence') }}</h3>
        </div>
        <div class="sec-card-body" style="padding-top: 12px;">
            <pre class="sec-code-block">{{ $log->evidence }}</pre>
        </div>
    </div>
@endif

<div class="sec-card">
    <div class="sec-card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="sec-card-title" style="display: flex; align-items: center; gap: 8px;">
            <span>{{ $attacker['icon'] }}</span>
            <span>{{ __('Karakteristik & Profil Klien Penyerang') }}</span>
        </h3>
    </div>
    <div class="sec-card-body" style="padding-top: 12px;">
        <div class="sec-detail-grid">
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Nama Software / Client') }}</div>
                <div class="sec-detail-value" style="font-weight: 600;">{{ $attacker['name'] }}</div>
            </div>
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Klasifikasi Ancaman') }}</div>
                <div class="sec-detail-value">
                    <span class="sec-badge {{ $attacker['badge_class'] }}">{{ $attacker['category_label'] }}</span>
                </div>
            </div>
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Sistem Operasi Terdeteksi') }}</div>
                <div class="sec-detail-value">{{ $attacker['os'] ?? '—' }}</div>
            </div>
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Perangkat / Browser') }}</div>
                <div class="sec-detail-value">{{ $attacker['browser'] ?? '—' }} ({{ ucfirst($attacker['device_type'] ?? 'desktop') }})</div>
            </div>
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Status Otomasi') }}</div>
                <div class="sec-detail-value">
                    @if ($attacker['is_bot'])
                        <span class="sec-badge sec-badge-critical">{{ __('Terdeteksi Bot / Otomasi / Script') }}</span>
                    @else
                        <span class="sec-badge sec-badge-low">{{ __('Browser Standar') }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div style="margin-top: 14px;">
            <div class="sec-detail-label">{{ __('Raw User-Agent') }}</div>
            <pre class="sec-code-block" style="white-space: pre-wrap; word-break: break-all;">{{ $log->user_agent ?: '—' }}</pre>
        </div>
    </div>
</div>

<div class="sec-card">
    <div class="sec-card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="sec-card-title">{{ __('Request context') }}</h3>
    </div>
    <div class="sec-card-body" style="padding-top: 12px;">
        <div class="sec-detail-grid">
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('User agent') }}</div>
                <div class="sec-detail-value" style="font-size: 12.5px;">{{ $log->user_agent ?? '—' }}</div>
            </div>
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Referer') }}</div>
                <div class="sec-detail-value" style="font-size: 12.5px;">{{ $log->referer ?? '—' }}</div>
            </div>
        </div>
        @if (! empty($log->meta))
            <div style="margin-top: 14px;">
                <div class="sec-detail-label">{{ __('Metadata') }}</div>
                <pre class="sec-code-block">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endif
    </div>
</div>

@if ($block)
    <div class="sec-card">
        <div class="sec-card-header">
            <h3 class="sec-card-title">{{ __('Related block') }}</h3>
            <a href="{{ route('pasak.dashboard.blocked-ips.show', $block->id) }}" class="sec-btn sec-btn-secondary sec-btn-sm">{{ __('View block') }}</a>
        </div>
        <div class="sec-card-body">
            <div class="sec-detail-grid">
                <div class="sec-detail-item">
                    <div class="sec-detail-label">{{ __('Status') }}</div>
                    <div class="sec-detail-value">{{ $block->is_active ? __('Active') : __('Inactive') }}</div>
                </div>
                <div class="sec-detail-item">
                    <div class="sec-detail-label">{{ __('Reason') }}</div>
                    <div class="sec-detail-value">{{ $block->reason ?? '—' }}</div>
                </div>
                <div class="sec-detail-item">
                    <div class="sec-detail-label">{{ __('Expires') }}</div>
                    <div class="sec-detail-value">{{ $block->expires_at ?? __('Permanent') }}</div>
                </div>
            </div>
        </div>
    </div>
@endif

@if (($relatedLogs ?? collect())->isNotEmpty())
    <div class="sec-card sec-card-flush">
        <div class="sec-card-header">
            <h3 class="sec-card-title">{{ __('Other logs from this IP') }}</h3>
        </div>
        <div class="sec-table-wrap">
            <table class="sec-table">
                <thead>
                    <tr>
                        <th>{{ __('Time') }}</th>
                        <th>{{ __('Event') }}</th>
                        <th>{{ __('Level') }}</th>
                        <th>{{ __('Path') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($relatedLogs as $related)
                        <tr>
                            <td class="sec-font-mono" style="font-size: 12px; white-space: nowrap;" title="{{ $DV::diffForHumans($related->created_at) }}">{{ $DV::formatDate($related->created_at, 'Y-m-d H:i:s') }}</td>
                            <td>{{ $related->event_type }}</td>
                            <td><span class="sec-badge {{ $DV::levelClass($related->threat_level) }}">{{ $DV::levelLabel($related->threat_level) }}</span></td>
                            <td class="sec-font-mono" style="font-size: 12px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $related->path }}">{{ $related->path }}</td>
                            <td style="text-align: right;">
                                <a href="{{ route('pasak.dashboard.logs.show', $related->id) }}" class="sec-btn sec-btn-ghost sec-btn-sm">{{ __('Detail') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <form method="POST" action="{{ $urls['destroy'] }}" data-sec-confirm="{{ __('Hapus entri log ancaman ini secara permanen?') }}" data-confirm-title="{{ __('Konfirmasi Hapus Log') }}" data-confirm-type="danger" data-confirm-btn="{{ __('Ya, Hapus') }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="sec-btn sec-btn-danger sec-btn-sm">{{ __('Delete entry') }}</button>
    </form>
</div>
@endsection
