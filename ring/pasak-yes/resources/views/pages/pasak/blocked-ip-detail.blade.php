@extends('pages.pasak.layout', ['heading' => __('Block detail'), 'subheading' => $block->ip_address])

@section('page_support_key', 'blocked-ip-detail')

@section('content')
@php
    $DV = \Internal\Pasak\Support\DashboardView::class;
@endphp
<a href="{{ $urls['index'] }}" class="sec-back-link">← {{ __('Back to blocked IPs') }}</a>

<div class="sec-card">
    @php
        $loc = $location ?? $DV::resolveLocation($block->ip_address);
        $countryName = $loc['country'] ?? null;
        $countryCode = $loc['country_code'] ?? null;
        $regionName = $loc['region'] ?? null;
        $cityName = $loc['city'] ?? null;
        $locDetail = implode(', ', array_filter([$cityName, $regionName]));
    @endphp
    <div class="sec-detail-grid">
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('IP address') }}</div>
            <div class="sec-detail-value sec-font-mono" style="font-weight: 600;">{{ $block->ip_address }}</div>
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
                <div class="sec-detail-label">{{ __('ISP / Operator') }}</div>
                <div class="sec-detail-value">{{ $loc['isp'] }}</div>
            </div>
        @endif
        @if (! empty($loc['latitude']) && ! empty($loc['longitude']))
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Koordinat Geo') }}</div>
                <div class="sec-detail-value sec-font-mono" style="font-size: 12px;">{{ number_format((float) $loc['latitude'], 4) }}°, {{ number_format((float) $loc['longitude'], 4) }}°</div>
            </div>
        @endif
        @php
            $leadLogsList = $logsLeadingToBlock ?? $relatedLogs ?? collect();
            $firstLead = $leadLogsList->first();
            $leadAttacker = $firstLead ? $DV::attackerInfo($firstLead->user_agent, $firstLead->event_type) : null;
        @endphp
        @if ($leadAttacker)
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Profil Penyerang') }}</div>
                <div class="sec-detail-value" style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px; line-height: 1;">{{ $leadAttacker['icon'] }}</span>
                    <span class="sec-badge {{ $leadAttacker['badge_class'] }}" style="font-size: 12px; font-weight: 600;">{{ $leadAttacker['name'] }}</span>
                    <span style="font-size: 12px; color: var(--sec-text-muted);">({{ $leadAttacker['category_label'] }})</span>
                </div>
            </div>
        @endif
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Status') }}</div>
            <div class="sec-detail-value">
                @if ($block->is_active)
                    <span class="sec-badge sec-badge-critical">{{ __('Active') }}</span>
                @else
                    <span class="sec-badge sec-badge-zinc">{{ __('Inactive') }}</span>
                @endif
            </div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Scope') }}</div>
            <div class="sec-detail-value">{{ $block->block_scope }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Source') }}</div>
            <div class="sec-detail-value">{{ $block->source }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Blocked at') }}</div>
            <div class="sec-detail-value">{{ $DV::formatDate($block->blocked_at ?? $block->created_at, 'd M Y H:i:s') }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Expires') }}</div>
            <div class="sec-detail-value">{{ $block->expires_at ? $DV::formatDate($block->expires_at, 'd M Y H:i:s') : __('Permanent') }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Hits since block') }}</div>
            <div class="sec-detail-value">{{ number_format((int) $block->hit_count) }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Last hit') }}</div>
            <div class="sec-detail-value">{{ $block->last_hit_at ? $DV::formatDate($block->last_hit_at, 'd M Y H:i:s') : '—' }}</div>
        </div>
        @if ($block->device_id || $block->local_ip)
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Device') }}</div>
                <div class="sec-detail-value sec-font-mono">{{ $block->device_id ?? '—' }} / {{ $block->local_ip ?? '—' }}</div>
            </div>
        @endif
    </div>
</div>

<div class="sec-card">
    <div class="sec-card-body">
        <div class="sec-detail-label">{{ __('Reason') }}</div>
        <p style="margin: 0 0 14px 0; font-size: 14px;">{{ $block->reason ?? '—' }}</p>
        @if ($block->notes)
            <div class="sec-detail-label">{{ __('Notes') }}</div>
            <pre class="sec-code-block">{{ $block->notes }}</pre>
        @endif
    </div>
</div>

@php($leadLogs = $logsLeadingToBlock ?? $relatedLogs ?? collect())
@if ($leadLogs->isNotEmpty())
    {{-- Kronologi Aktivitas Serangan Lengkap Sampai IP Diblokir --}}
    <div class="sec-card sec-card-flush">
        <div class="sec-card-header">
            <div>
                <h3 class="sec-card-title" style="display: flex; align-items: center; gap: 8px;">
                    <span>🚨</span>
                    <span>{{ __('Triggering logs') }} · {{ __('Kronologi Aktivitas IP Menuju Pemblokiran') }}</span>
                    <span class="sec-badge sec-badge-critical" style="font-size: 11px;">{{ $leadLogs->count() }} {{ __('kejadian tercatat') }}</span>
                </h3>
                <p class="sec-card-subtitle">{{ __('Seluruh jejak serangan, request, dan anomali yang dilakukan oleh IP ini secara berurutan hingga WAF menerapkan karantina.') }}</p>
            </div>
        </div>
        <div class="sec-table-wrap">
            <table class="sec-table">
                <thead>
                    <tr>
                        <th style="width: 44px; text-align: center;">#</th>
                        <th>{{ __('Waktu') }}</th>
                        <th>{{ __('Metode & Path Target') }}</th>
                        <th>{{ __('Level & Jenis Event') }}</th>
                        <th>{{ __('Aturan / Deskripsi') }}</th>
                        <th>{{ __('Bukti Cuplikan (Evidence / Payload)') }}</th>
                        <th>{{ __('Tindakan') }}</th>
                        <th style="text-align: right;">{{ __('Detail') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($leadLogs as $idx => $log)
                        @php($lvl = strtolower($log->threat_level ?? 'low'))
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--sec-text-muted); font-size: 11px;" class="sec-font-mono">
                                {{ $idx + 1 }}
                            </td>
                            <td class="sec-font-mono" style="font-size: 12px; white-space: nowrap;">
                                <div style="font-weight: 600;">{{ $DV::formatDate($log->created_at, 'd M Y H:i:s') }}</div>
                                <div style="font-size: 11px; color: var(--sec-text-muted);">{{ $log->created_at ? $DV::diffForHumans($log->created_at) : '' }}</div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span class="sec-badge sec-badge-zinc sec-font-mono" style="font-size: 10.5px; font-weight: 800;">{{ $log->method ?? 'GET' }}</span>
                                        <span class="sec-font-mono" style="font-size: 12px; word-break: break-all; max-width: 240px;" title="{{ $log->path }}">{{ $log->path ?? '/' }}</span>
                                    </div>
                                    @php($rowAttacker = $DV::attackerInfo($log->user_agent, $log->event_type))
                                    <div style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px;" title="{{ $rowAttacker['raw_ua'] ?: __('Tanpa User-Agent') }}">
                                        <span>{{ $rowAttacker['icon'] }}</span>
                                        <span class="sec-badge {{ $rowAttacker['badge_class'] }}" style="padding: 1px 5px; font-size: 10px;">{{ $rowAttacker['name'] }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                    <span class="sec-badge {{ $lvl === 'critical' ? 'sec-badge-critical' : ($lvl === 'high' ? 'sec-badge-high' : ($lvl === 'medium' ? 'sec-badge-medium' : 'sec-badge-low')) }}">
                                        {{ $lvl }}
                                    </span>
                                    <span style="font-size: 11.5px; font-weight: 600; color: var(--sec-text);">{{ $log->event_type ?? '-' }}</span>
                                </div>
                            </td>
                            <td style="font-size: 12px; color: var(--sec-text-muted); max-width: 200px;" title="{{ $log->rule_label }}">
                                {{ $log->rule_label ?? '-' }}
                            </td>
                            <td style="max-width: 260px;">
                                @if (! empty($log->evidence))
                                    <pre class="sec-font-mono" style="margin: 0; padding: 4px 8px; font-size: 11px; background: rgba(0,0,0,0.25); border-radius: 4px; color: var(--sec-danger); max-height: 60px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;" title="{{ $log->evidence }}">{{ \Illuminate\Support\Str::limit((string) $log->evidence, 140) }}</pre>
                                @else
                                    <span style="color: var(--sec-text-subtle); font-size: 11px;">—</span>
                                @endif
                            </td>
                            <td>
                                @if (($log->action_taken ?? '') === 'blocked' || $log->blocked)
                                    <span class="sec-badge sec-badge-critical">BLOCKED (403)</span>
                                @elseif (($log->action_taken ?? '') === 'quarantined')
                                    <span class="sec-badge sec-badge-critical">QUARANTINED</span>
                                @else
                                    <span class="sec-badge sec-badge-warning">DETECTED</span>
                                @endif
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="{{ route('pasak.dashboard.logs.show', $log->id) }}" class="sec-btn sec-btn-ghost sec-btn-sm" style="font-size: 11px;">🔍 {{ __('Detail') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Titik Pemutusan Blokir WAF --}}
        @if (!empty($block))
            <div style="padding: 14px 18px; background: rgba(239, 68, 68, 0.1); border-top: 1px solid rgba(239, 68, 68, 0.25); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 22px;">🛑</span>
                    <div>
                        <strong style="color: var(--sec-danger); font-size: 13.5px;">{{ __('TITIK PEMBLOKIRAN DITERAPKAN OLEH SISTEM') }}</strong>
                        <div style="margin-top: 2px; font-size: 12.5px; color: var(--sec-text);">
                            {{ __('IP ini resmi dimasukkan ke daftar karantina pada') }}
                            <strong class="sec-font-mono" style="color: var(--sec-danger);">{{ $DV::formatDate($block->blocked_at ?? $block->created_at, 'd M Y H:i:s') }}</strong>
                            @if ($block->reason)
                                · {{ __('Alasan:') }} <em>{{ $block->reason }}</em>
                            @endif
                        </div>
                    </div>
                </div>
                <span class="sec-badge sec-badge-critical" style="font-size: 11px; padding: 4px 10px;">⛔ {{ __('STATUS: DIBLOKIR') }}</span>
            </div>
        @endif
    </div>
@endif

{{-- Aktivitas Pasca-Pemblokiran (Request lanjutan yang langsung ditolak 403) --}}
@if (isset($logsAfterBlock) && $logsAfterBlock->isNotEmpty())
    <div class="sec-card sec-card-flush">
        <div class="sec-card-header">
            <div>
                <h3 class="sec-card-title" style="display: flex; align-items: center; gap: 8px;">
                    <span>🛡️</span>
                    <span>{{ __('Aktivitas Pasca-Pemblokiran (Percobaan Ditolak HTTP 403)') }}</span>
                    <span class="sec-badge sec-badge-zinc" style="font-size: 11px;">{{ $logsAfterBlock->count() }} hits</span>
                </h3>
                <p class="sec-card-subtitle">{{ __('Request yang tetap dikirimkan oleh IP ini setelah diblokir, dan langsung ditolak oleh pertahanan WAF.') }}</p>
            </div>
        </div>
        <div class="sec-table-wrap">
            <table class="sec-table">
                <thead>
                    <tr>
                        <th>{{ __('Waktu') }}</th>
                        <th>{{ __('Metode & Path') }}</th>
                        <th>{{ __('Jenis Event') }}</th>
                        <th>{{ __('Respon WAF') }}</th>
                        <th style="text-align: right;">{{ __('Detail') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logsAfterBlock as $pLog)
                        <tr>
                            <td class="sec-font-mono" style="font-size: 12px; white-space: nowrap;" title="{{ $DV::diffForHumans($pLog->created_at) }}">{{ $DV::formatDate($pLog->created_at, 'Y-m-d H:i:s') }}</td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span class="sec-badge sec-badge-zinc sec-font-mono" style="font-size: 10px;">{{ $pLog->method ?? 'GET' }}</span>
                                    <span class="sec-font-mono" style="font-size: 12px;" title="{{ $pLog->path }}">{{ $pLog->path ?? '/' }}</span>
                                </div>
                            </td>
                            <td style="font-size: 12px;">{{ $pLog->event_type ?? '-' }}</td>
                            <td><span class="sec-badge sec-badge-critical">BLOCKED (HTTP 403)</span></td>
                            <td style="text-align: right;">
                                <a href="{{ route('pasak.dashboard.logs.show', $pLog->id) }}" class="sec-btn sec-btn-ghost sec-btn-sm" style="font-size: 11px;">🔍 {{ __('Detail') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if (($relatedTickets ?? collect())->isNotEmpty())
    <div class="sec-card sec-card-flush">
        <div class="sec-card-header">
            <h3 class="sec-card-title">{{ __('Appeals for this IP') }}</h3>
        </div>
        <div class="sec-table-wrap">
            <table class="sec-table">
                <thead>
                    <tr>
                        <th>{{ __('Ticket') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($relatedTickets as $ticket)
                        <tr>
                            <td class="sec-font-mono">{{ $ticket->ticket_number }}</td>
                            <td>{{ $ticket->name }}</td>
                            <td>{{ ucfirst($ticket->status) }}</td>
                            <td style="text-align: right;">
                                <a href="{{ route('pasak.dashboard.tickets.show', $ticket->id) }}" class="sec-btn sec-btn-ghost sec-btn-sm">{{ __('Detail') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <form method="POST" action="{{ $urls['toggle'] }}" data-sec-confirm="{{ $block->is_active ? __('Nonaktifkan status blokir untuk IP ini?') : __('Aktifkan kembali pemblokiran untuk IP ini?') }}" data-confirm-title="{{ __('Konfirmasi Ubah Status') }}" data-confirm-type="warning" data-confirm-btn="{{ $block->is_active ? __('Ya, Nonaktifkan') : __('Ya, Aktifkan') }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="sec-btn sec-btn-secondary sec-btn-sm">{{ $block->is_active ? __('Disable block') : __('Re-enable block') }}</button>
    </form>
    <form method="POST" action="{{ $urls['destroy'] }}" data-sec-confirm="{{ __('Buka blokir dan hapus status karantina untuk IP ini?') }}" data-confirm-title="{{ __('Konfirmasi Buka Blokir') }}" data-confirm-type="danger" data-confirm-btn="{{ __('Ya, Buka Blokir') }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="sec-btn sec-btn-danger sec-btn-sm">{{ __('Unblock') }}</button>
    </form>
</div>
@endsection
