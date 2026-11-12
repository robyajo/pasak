@extends('pages.pasak.layout', ['heading' => __('Server Audit'), 'subheading' => __('Integrity, webshell scanner, and environment hygiene')])

@section('page_support_key', 'server')

@section('content')
@php
    $DV = \Internal\Pasak\Support\DashboardView::class;
    $summary = $report['summary'] ?? [];
@endphp

<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 20px; flex-wrap: wrap;">
    <div class="sec-grid-4" style="flex: 1; margin-bottom: 0;">
        <div class="sec-stat-card">
            <div class="sec-stat-label">{{ __('Health score') }}</div>
            <div class="sec-stat-value">{{ $summary['score'] ?? 0 }}<span style="font-size: 14px; color: var(--sec-text-subtle);">/100</span></div>
        </div>
        <div class="sec-stat-card">
            <div class="sec-stat-label">{{ __('Critical') }}</div>
            <div class="sec-stat-value sec-text-danger">{{ $summary['critical'] ?? 0 }}</div>
        </div>
        <div class="sec-stat-card">
            <div class="sec-stat-label">{{ __('Warnings') }}</div>
            <div class="sec-stat-value" style="color: var(--sec-warning);">{{ $summary['warning'] ?? 0 }}</div>
        </div>
        <div class="sec-stat-card">
            <div class="sec-stat-label">{{ __('Passed checks') }}</div>
            <div class="sec-stat-value" style="color: var(--sec-success);">{{ $summary['ok'] ?? 0 }}</div>
        </div>
    </div>
    <div>
        <a href="{{ route('pasak.dashboard.server', ['refresh' => 1]) }}" class="sec-btn sec-btn-primary">🔄 {{ __('Refresh') }}</a>
    </div>
</div>

<div class="sec-grid-2">
    <div class="sec-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h3 class="sec-card-title">{{ __('Integrity baseline') }}</h3>
                <p class="sec-card-subtitle">
                    @if ($baseline['exists'] ?? false)
                        {{ __(':files files tracked · created :when', ['files' => number_format($baseline['files'] ?? 0), 'when' => !empty($baseline['created_at']) ? $DV::formatDate($baseline['created_at'], 'd M Y, H:i:s') : '-']) }}
                    @else
                        {{ __('No baseline created yet (:watched files watched).', ['watched' => number_format($baseline['watched'] ?? 0)]) }}
                    @endif
                </p>
            </div>
            <div style="display: flex; gap: 8px;">
                <form method="POST" action="{{ route('pasak.dashboard.server.baseline') }}" data-sec-confirm="{{ __('Buat atau perbarui SHA-256 integrity baseline untuk seluruh berkas aplikasi sekarang?') }}" data-confirm-title="{{ __('Konfirmasi Buat Baseline') }}" data-confirm-type="primary" data-confirm-btn="{{ __('Ya, Buat Baseline') }}">
                    @csrf
                    <button type="submit" class="sec-btn sec-btn-secondary sec-btn-sm">{{ __('Create') }}</button>
                </form>
                @if ($baseline['exists'] ?? false)
                    <form method="POST" action="{{ route('pasak.dashboard.server.baseline.destroy') }}" data-sec-confirm="{{ __('Hapus berkas SHA-256 integrity baseline yang tersimpan?') }}" data-confirm-title="{{ __('Konfirmasi Hapus Baseline') }}" data-confirm-type="danger" data-confirm-btn="{{ __('Ya, Hapus') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sec-btn sec-btn-danger sec-btn-sm">{{ __('Delete') }}</button>
                    </form>
                @endif
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center; margin-bottom: 16px;">
            <div style="padding: 12px; background: var(--sec-warning-bg); border: 1px solid var(--sec-warning-border); border-radius: var(--sec-radius-sm);">
                <div style="font-size: 20px; font-weight: 700; color: var(--sec-warning);">{{ count($integrity['modified'] ?? []) }}</div>
                <div style="font-size: 11px; font-weight: 600; color: var(--sec-text-muted);">{{ __('Modified') }}</div>
            </div>
            <div style="padding: 12px; background: var(--sec-danger-bg); border: 1px solid var(--sec-danger-border); border-radius: var(--sec-radius-sm);">
                <div style="font-size: 20px; font-weight: 700; color: var(--sec-danger);">{{ count($integrity['missing'] ?? []) }}</div>
                <div style="font-size: 11px; font-weight: 600; color: var(--sec-text-muted);">{{ __('Missing') }}</div>
            </div>
            <div style="padding: 12px; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: var(--sec-radius-sm);">
                <div style="font-size: 20px; font-weight: 700; color: var(--sec-primary);">{{ count($integrity['added'] ?? []) }}</div>
                <div style="font-size: 11px; font-weight: 600; color: var(--sec-text-muted);">{{ __('New') }}</div>
            </div>
        </div>

        @php
            $integrityIssues = array_slice(array_merge(
                array_map(fn ($f) => ['type' => 'modified', 'file' => $f], $integrity['modified'] ?? []),
                array_map(fn ($f) => ['type' => 'missing', 'file' => $f], $integrity['missing'] ?? []),
                array_map(fn ($f) => ['type' => 'added', 'file' => $f], $integrity['added'] ?? []),
            ), 0, 8);
        @endphp

        @if (! empty($integrityIssues))
            <div style="display: flex; flex-direction: column; gap: 6px;">
                @foreach ($integrityIssues as $issue)
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding: 6px 8px; background: var(--sec-card-hover); border-radius: var(--sec-radius-sm);">
                        <span class="sec-font-mono" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 80%;" title="{{ $issue['file'] }}">{{ $issue['file'] }}</span>
                        <span class="sec-badge {{ $issue['type'] === 'missing' ? 'sec-badge-critical' : ($issue['type'] === 'modified' ? 'sec-badge-high' : 'sec-badge-low') }}">{{ $issue['type'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="sec-card">
        <h3 class="sec-card-title" style="margin-bottom: 14px;">{{ __('Environment') }}</h3>
        <div style="display: flex; flex-direction: column;">
            @foreach (($environment ?? []) as $key => $value)
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--sec-border-light); font-size: 13px;">
                    <span style="color: var(--sec-text-muted);">{{ \Illuminate\Support\Str::headline($key) }}</span>
                    <span class="sec-font-mono" style="font-weight: 600; max-width: 60%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $value }}">{{ $value }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Pengaturan Lokasi Fisik & Zona Waktu Server (Live Threat Map & System Clock) --}}
@php
    $effectiveTz = $serverLocation['effective_timezone'] ?? ($serverLocation['timezone'] ?? \Internal\Pasak\Support\DashboardView::timezone());
    $customTz = $serverLocation['timezone'] ?? null;
    $indonesianTzs = [
        'Asia/Jakarta' => 'Asia/Jakarta (WIB — UTC+07:00: Jakarta, Sumatra, Jawa)',
        'Asia/Pontianak' => 'Asia/Pontianak (WIB — UTC+07:00: Kalimantan Barat)',
        'Asia/Makassar' => 'Asia/Makassar (WITA — UTC+08:00: Makassar, Bali, NTB, NTT, Kalsel, Kaltim)',
        'Asia/Jayapura' => 'Asia/Jayapura (WIT — UTC+09:00: Maluku, Papua)',
    ];
    $globalTzs = [
        'UTC' => 'UTC (Universal Coordinated Time — UTC+00:00)',
        'Asia/Singapore' => 'Asia/Singapore (SGT — UTC+08:00: Singapura, Malaysia)',
        'Asia/Bangkok' => 'Asia/Bangkok (ICT — UTC+07:00: Bangkok, Hanoi)',
        'Asia/Tokyo' => 'Asia/Tokyo (JST — UTC+09:00: Tokyo)',
        'Asia/Seoul' => 'Asia/Seoul (KST — UTC+09:00: Seoul)',
        'Asia/Hong_Kong' => 'Asia/Hong_Kong (HKT — UTC+08:00: Hong Kong)',
        'Asia/Dubai' => 'Asia/Dubai (GST — UTC+04:00: Dubai)',
        'Europe/London' => 'Europe/London (GMT/BST — UTC+00:00 / +01:00: London)',
        'Europe/Amsterdam' => 'Europe/Amsterdam (CET/CEST — UTC+01:00 / +02:00: Amsterdam, Berlin, Paris)',
        'America/New_York' => 'America/New_York (EST/EDT — UTC-05:00 / -04:00: New York)',
        'America/Chicago' => 'America/Chicago (CST/CDT — UTC-06:00 / -05:00: Chicago)',
        'America/Los_Angeles' => 'America/Los_Angeles (PST/PDT — UTC-08:00 / -07:00: Los Angeles)',
        'Australia/Sydney' => 'Australia/Sydney (AEST/AEDT — UTC+10:00 / +11:00: Sydney)',
    ];
    $allTzs = \DateTimeZone::listIdentifiers();
@endphp
<div class="sec-card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 16px; flex-wrap: wrap;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <h3 class="sec-card-title" style="margin: 0; font-size: 16px;">📍 {{ __('Lokasi & Waktu Operasional Server') }}</h3>
                @if ($serverLocation['is_custom'] ?? false)
                    <span class="sec-badge sec-badge-success" style="font-size: 11px;">{{ __('Kustom Aktif') }}</span>
                @else
                    <span class="sec-badge sec-badge-low" style="font-size: 11px;">{{ __('Deteksi Otomatis / Bawaan') }}</span>
                @endif
                <span class="sec-badge sec-badge-low sec-font-mono" style="font-size: 11px;">🕒 {{ $effectiveTz }}</span>
            </div>
            <p class="sec-card-subtitle" style="margin-top: 4px;">
                {{ __('Konfigurasikan label lokasi, titik koordinat GPS (target radar Threat Map), serta zona waktu resmi server yang berlaku untuk seluruh log, audit, dan telemetri.') }}
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            @if (!empty($urls['threatMap']) && $urls['threatMap'] !== '#')
                <a href="{{ $urls['threatMap'] }}" class="sec-btn sec-btn-secondary sec-btn-sm" style="display: inline-flex; align-items: center; gap: 5px; text-decoration: none;">
                    <span>🗺️</span>
                    <span>{{ __('Lihat Radar Threat Map') }}</span>
                </a>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ $urls['updateLocation'] ?? (Route::has('pasak.dashboard.server.location') ? route('pasak.dashboard.server.location') : (Route::has('pasak.dashboard.server') ? route('pasak.dashboard.server') : '#')) }}" id="serverLocationForm">
        @csrf
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 16px;">
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--sec-text-muted);">
                    {{ __('Nama Lokasi / Label Node Server') }}
                </label>
                <input type="text"
                    name="server_location_name"
                    id="locNameInput"
                    class="sec-input"
                    style="width: 100%; box-sizing: border-box;"
                    placeholder="contoh: Data Center Kominfo, Jakarta (ID)"
                    value="{{ old('server_location_name', $serverLocation['name'] ?? '') }}"
                    maxlength="255">
                <span style="display: block; font-size: 11px; color: var(--sec-text-subtle); margin-top: 4px;">
                    {{ __('Label node server yang muncul pada bar status atas Live Threat Map.') }}
                </span>
            </div>

            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label style="font-size: 12px; font-weight: 600; color: var(--sec-text-muted); margin: 0;">
                        🕒 {{ __('Zona Waktu Server (Timezone)') }}
                    </label>
                    <span style="font-size: 11px; font-weight: 600; color: var(--sec-primary);" class="sec-font-mono" id="currentClockTicker">--:--:--</span>
                </div>
                <select name="server_timezone" id="locTimezoneSelect" class="sec-select sec-font-mono" style="width: 100%; box-sizing: border-box; font-size: 12.5px;">
                    <option value="" @selected(empty($customTz))>-- {{ __('Bawaan Host / Auto-Detect (:tz)', ['tz' => config('app.timezone', 'UTC')]) }} --</option>
                    <optgroup label="🇮🇩 Indonesia (WIB / WITA / WIT)">
                        @foreach ($indonesianTzs as $tzKey => $tzLabel)
                            <option value="{{ $tzKey }}" @selected(old('server_timezone', $customTz) === $tzKey)>{{ $tzLabel }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="🌐 Global Populer & UTC">
                        @foreach ($globalTzs as $tzKey => $tzLabel)
                            <option value="{{ $tzKey }}" @selected(old('server_timezone', $customTz) === $tzKey)>{{ $tzLabel }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="🌍 Semua Zona Waktu Lainnya">
                        @foreach ($allTzs as $tzKey)
                            @if (!isset($indonesianTzs[$tzKey]) && !isset($globalTzs[$tzKey]))
                                <option value="{{ $tzKey }}" @selected(old('server_timezone', $customTz) === $tzKey)>{{ $tzKey }}</option>
                            @endif
                        @endforeach
                    </optgroup>
                </select>
                <span style="display: block; font-size: 11px; color: var(--sec-text-subtle); margin-top: 4px;">
                    {{ __('Waktu rujukan untuk seluruh tampilan tanggal, jam log, grafik, audit, dan radar.') }}
                </span>
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--sec-text-muted);">
                    {{ __('Latitude (Lintang: -90 s/d 90)') }}
                </label>
                <input type="number"
                    step="any"
                    name="server_latitude"
                    id="locLatInput"
                    class="sec-input sec-font-mono"
                    style="width: 100%; box-sizing: border-box;"
                    placeholder="-6.2088"
                    value="{{ old('server_latitude', $serverLocation['latitude'] ?? '') }}">
                <span style="display: block; font-size: 11px; color: var(--sec-text-subtle); margin-top: 4px;">
                    {{ __('Titik koordinat lintang untuk Live Threat Map.') }}
                </span>
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--sec-text-muted);">
                    {{ __('Longitude (Bujur: -180 s/d 180)') }}
                </label>
                <input type="number"
                    step="any"
                    name="server_longitude"
                    id="locLonInput"
                    class="sec-input sec-font-mono"
                    style="width: 100%; box-sizing: border-box;"
                    placeholder="106.8456"
                    value="{{ old('server_longitude', $serverLocation['longitude'] ?? '') }}">
                <span style="display: block; font-size: 11px; color: var(--sec-text-subtle); margin-top: 4px;">
                    {{ __('Titik koordinat bujur untuk Live Threat Map.') }}
                </span>
            </div>
        </div>

        {{-- Action Bar & Interactive Helpers --}}
        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid var(--sec-border-light); flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="sec-btn sec-btn-secondary sec-btn-sm" id="btnDetectGps" style="display: inline-flex; align-items: center; gap: 5px;">
                    <span>🎯</span>
                    <span>{{ __('Gunakan GPS Peramban') }}</span>
                </button>
                <button type="button" class="sec-btn sec-btn-secondary sec-btn-sm" id="btnDetectTimezone" style="display: inline-flex; align-items: center; gap: 5px;">
                    <span>🕒</span>
                    <span>{{ __('Deteksi Timezone Peramban') }}</span>
                </button>
                <button type="button" class="sec-btn sec-btn-secondary sec-btn-sm" id="btnClearLocation" style="display: inline-flex; align-items: center; gap: 5px;">
                    <span>🔄</span>
                    <span>{{ __('Kosongkan / Reset Bawaan') }}</span>
                </button>
                @if (!empty($serverLocation['latitude']) && !empty($serverLocation['longitude']))
                    <a href="https://www.google.com/maps?q={{ $serverLocation['latitude'] }},{{ $serverLocation['longitude'] }}" target="_blank" rel="noopener noreferrer" class="sec-btn sec-btn-secondary sec-btn-sm" style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none;" title="{{ __('Lihat titik koordinat di Google Maps') }}">
                        <span>🌐</span>
                        <span>{{ __('Buka di Maps ↗') }}</span>
                    </a>
                @endif
            </div>

            <div style="display: flex; gap: 8px; align-items: center;">
                <span id="locStatusMsg" style="font-size: 12px; color: var(--sec-text-muted); display: none;"></span>
                <button type="submit" class="sec-btn sec-btn-primary sec-btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span>💾</span>
                    <span>{{ __('Simpan Pengaturan Server') }}</span>
                </button>
            </div>
        </div>
    </form>
</div>


<div class="sec-card sec-card-flush">
    <div class="sec-card-header">
        <h3 class="sec-card-title">{{ __('Suspicious files & webshells') }}</h3>
    </div>
    <div class="sec-table-wrap">
        <table class="sec-table">
            <thead>
                <tr>
                    <th>{{ __('File') }}</th>
                    <th>{{ __('Threat') }}</th>
                    <th>{{ __('Reason') }}</th>
                    <th>{{ __('Modified') }}</th>
                    <th style="text-align: right;">{{ __('Size') }}</th>
                    <th style="text-align: right;">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($suspicious ?? []) as $file)
                    <tr>
                        <td class="sec-font-mono" style="font-size: 12px; font-weight: 600;">
                            <a href="javascript:void(0)" class="btn-inspect-file" style="color: var(--sec-primary); text-decoration: none;"
                                data-path="{{ $file['path'] ?? '' }}"
                                data-filename="{{ $file['filename'] ?? '' }}"
                                data-location="{{ $file['location'] ?? '' }}"
                                data-threat="{{ $file['threat_level'] ?? 'info' }}"
                                data-category="{{ $file['category'] ?? '' }}"
                                data-reason="{{ $file['reason'] ?? '' }}"
                                data-snippet="{{ $file['snippet'] ?? '' }}"
                                data-size="{{ $file['size'] ?? '' }}"
                                data-download-url="{{ route('pasak.dashboard.server.suspicious-files.download', ['file_path' => $file['path'] ?? '']) }}"
                                data-modified="{{ !empty($file['modified_at']) ? $DV::formatDate($file['modified_at'], 'd M Y, H:i:s') : '-' }}"
                                data-modified-human="{{ $file['modified_at_human'] ?? '' }}">
                                {{ $file['path'] ?? '-' }}
                            </a>
                        </td>
                        <td><span class="sec-badge {{ $DV::statusBadgeClass($file['threat_level'] ?? 'info') }}">{{ $file['threat_level'] ?? 'info' }}</span></td>
                        <td style="max-width: 280px; font-size: 12px; color: var(--sec-text-muted);" title="{{ $file['reason'] ?? '' }}">{{ \Illuminate\Support\Str::limit((string) ($file['reason'] ?? ''), 60) }}</td>
                        <td style="font-size: 12px; white-space: nowrap;">
                            <div style="font-weight: 600; color: var(--sec-text);" class="sec-font-mono">
                                {{ !empty($file['modified_at']) ? $DV::formatDate($file['modified_at'], 'd M Y, H:i:s') : '-' }}
                            </div>
                            <div style="font-size: 11px; color: var(--sec-text-muted);" title="{{ $file['modified_at'] ?? '' }}">
                                {{ $file['modified_at_human'] ?? (!empty($file['modified_at']) ? $DV::diffForHumans($file['modified_at']) : '-') }}
                            </div>
                        </td>
                        <td style="text-align: right; font-size: 12px;" class="sec-font-mono">{{ $file['size'] ?? '-' }}</td>
                        <td style="text-align: right; white-space: nowrap;">
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <button type="button" class="sec-btn sec-btn-secondary sec-btn-sm btn-inspect-file"
                                    data-path="{{ $file['path'] ?? '' }}"
                                    data-filename="{{ $file['filename'] ?? '' }}"
                                    data-location="{{ $file['location'] ?? '' }}"
                                    data-threat="{{ $file['threat_level'] ?? 'info' }}"
                                    data-category="{{ $file['category'] ?? '' }}"
                                    data-reason="{{ $file['reason'] ?? '' }}"
                                    data-snippet="{{ $file['snippet'] ?? '' }}"
                                    data-size="{{ $file['size'] ?? '' }}"
                                    data-download-url="{{ route('pasak.dashboard.server.suspicious-files.download', ['file_path' => $file['path'] ?? '']) }}"
                                    data-modified="{{ !empty($file['modified_at']) ? $DV::formatDate($file['modified_at'], 'd M Y, H:i:s') : '-' }}"
                                    data-modified-human="{{ $file['modified_at_human'] ?? '' }}"
                                    title="{{ __('Lihat Bukti & Detail') }}">
                                    🔍 {{ __('Detail') }}
                                </button>
                                <a href="{{ route('pasak.dashboard.server.suspicious-files.download', ['file_path' => $file['path'] ?? '']) }}"
                                    class="sec-btn sec-btn-secondary sec-btn-sm"
                                    title="{{ __('Unduh berkas untuk analisis offline') }}"
                                    style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                                    <span>⬇️</span>
                                    <span>{{ __('Unduh') }}</span>
                                </a>
                                <button type="button" class="sec-btn sec-btn-secondary sec-btn-sm btn-verify-file"
                                    data-path="{{ $file['path'] ?? '' }}"
                                    data-filename="{{ $file['filename'] ?? '' }}"
                                    title="{{ __('Tandai Aman & Verifikasi') }}"
                                    style="display: inline-flex; align-items: center; gap: 4px; color: #16a34a; border-color: rgba(34, 197, 94, 0.4); background: rgba(34, 197, 94, 0.05);">
                                    <span>🛡️</span>
                                    <span>{{ __('Verifikasi') }}</span>
                                </button>
                                <form method="POST" action="{{ route('pasak.dashboard.server.suspicious-files.destroy') }}" style="display: inline;" data-sec-confirm="{{ __('PERINGATAN: Berkas mencurigakan ini akan dihapus secara permanen dari server. Lanjutkan?') }}" data-confirm-title="{{ __('Konfirmasi Hapus Berkas') }}" data-confirm-type="danger" data-confirm-btn="{{ __('Ya, Hapus Berkas') }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="file_path" value="{{ $file['path'] ?? '' }}">
                                    <button type="submit" class="sec-btn sec-btn-ghost sec-btn-sm" title="{{ __('Delete file') }}">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--sec-text-muted); padding: 32px;">{{ __('No suspicious files detected.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Tabel 2: Berkas Terverifikasi Aman oleh Admin --}}
<div class="sec-card sec-card-flush">
    <div class="sec-card-header">
        <div>
            <h3 class="sec-card-title" style="display: flex; align-items: center; gap: 8px;">
                <span>🛡️</span>
                <span>{{ __('Berkas Terverifikasi Aman oleh Admin') }}</span>
                <span class="sec-badge sec-badge-success" style="font-size: 11px;">{{ count($verified ?? []) }}</span>
            </h3>
            <p class="sec-card-subtitle">{{ __('Berkas yang telah ditinjau dan ditandai aman (false positive) oleh administrator, serta dikecualikan dari pemindaian audit.') }}</p>
        </div>
    </div>
    <div class="sec-table-wrap">
        <table class="sec-table">
            <thead>
                <tr>
                    <th>{{ __('Berkas') }}</th>
                    <th>{{ __('SHA-256 Hash') }}</th>
                    <th>{{ __('Ukuran') }}</th>
                    <th>{{ __('Waktu Verifikasi') }}</th>
                    <th>{{ __('Catatan Verifikasi') }}</th>
                    <th style="text-align: right;">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($verified ?? []) as $vFile)
                    <tr>
                        <td class="sec-font-mono" style="font-size: 12px; font-weight: 600;">
                            <span title="{{ $vFile['file'] ?? '-' }}">{{ $vFile['file'] ?? '-' }}</span>
                        </td>
                        <td class="sec-font-mono" style="font-size: 11px; color: var(--sec-text-muted); max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $vFile['hash'] ?? '-' }}">
                            {{ $vFile['hash'] ?? '-' }}
                        </td>
                        <td class="sec-font-mono" style="font-size: 12px;">{{ $vFile['size_human'] ?? '-' }}</td>
                        <td style="font-size: 12px; white-space: nowrap;">
                            <div style="font-weight: 600; color: var(--sec-text);" class="sec-font-mono">{{ $vFile['verified_at'] ?? '-' }}</div>
                            <div style="font-size: 11px; color: var(--sec-text-muted);">{{ $vFile['verified_at_human'] ?? '' }}</div>
                        </td>
                        <td style="font-size: 12px; color: var(--sec-text-muted); max-width: 260px;">
                            {{ $vFile['notes'] ?? '-' }}
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <form method="POST" action="{{ $urls['suspiciousUnverify'] ?? (\Route::has('pasak.dashboard.server.suspicious-files.unverify') ? route('pasak.dashboard.server.suspicious-files.unverify') : '#') }}" style="display: inline;" data-sec-confirm="{{ __('Batalkan verifikasi aman berkas ini? Berkas akan kembali diperiksa pada audit berikutnya.') }}" data-confirm-title="{{ __('Konfirmasi Pembatalan Verifikasi') }}" data-confirm-type="warning" data-confirm-btn="{{ __('Ya, Batalkan Verifikasi') }}">
                                @csrf
                                <input type="hidden" name="file_path" value="{{ $vFile['file'] ?? '' }}">
                                <button type="submit" class="sec-btn sec-btn-secondary sec-btn-sm" style="font-size: 11.5px; padding: 4px 10px;" title="{{ __('Batalkan status aman berkas ini') }}">
                                    <span>↩️</span>
                                    <span>{{ __('Batal Verifikasi') }}</span>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--sec-text-muted); padding: 32px;">{{ __('Belum ada berkas yang diverifikasi aman oleh admin.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Tabel 3: Riwayat Berkas Dihapus oleh Admin --}}
<div class="sec-card sec-card-flush">
    <div class="sec-card-header">
        <div>
            <h3 class="sec-card-title" style="display: flex; align-items: center; gap: 8px;">
                <span>🗑️</span>
                <span>{{ __('Riwayat Berkas Dihapus oleh Admin') }}</span>
                <span class="sec-badge sec-badge-critical" style="font-size: 11px;">{{ count($deleted ?? []) }}</span>
            </h3>
            <p class="sec-card-subtitle">{{ __('Jejak audit berkas webshell atau berkas berbahaya yang telah dihapus secara permanen dari server oleh administrator.') }}</p>
        </div>
    </div>
    <div class="sec-table-wrap">
        <table class="sec-table">
            <thead>
                <tr>
                    <th>{{ __('Berkas') }}</th>
                    <th>{{ __('SHA-256 Hash') }}</th>
                    <th>{{ __('Ukuran Terakhir') }}</th>
                    <th>{{ __('Waktu Penghapusan') }}</th>
                    <th>{{ __('Keterangan') }}</th>
                    <th style="text-align: right;">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($deleted ?? []) as $dFile)
                    <tr>
                        <td class="sec-font-mono" style="font-size: 12px; font-weight: 600; color: var(--sec-danger);">
                            <span title="{{ $dFile['file'] ?? '-' }}">{{ $dFile['file'] ?? '-' }}</span>
                        </td>
                        <td class="sec-font-mono" style="font-size: 11px; color: var(--sec-text-muted); max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $dFile['hash'] ?? '-' }}">
                            {{ $dFile['hash'] ?? '-' }}
                        </td>
                        <td class="sec-font-mono" style="font-size: 12px;">{{ $dFile['size_human'] ?? '-' }}</td>
                        <td style="font-size: 12px; white-space: nowrap;">
                            <div style="font-weight: 600; color: var(--sec-text);" class="sec-font-mono">{{ $dFile['deleted_at'] ?? '-' }}</div>
                            <div style="font-size: 11px; color: var(--sec-text-muted);">{{ $dFile['deleted_at_human'] ?? '' }}</div>
                        </td>
                        <td style="font-size: 12px; color: var(--sec-text-muted); max-width: 260px;">
                            {{ $dFile['reason'] ?? __('Dihapus permanen oleh admin') }}
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <span class="sec-badge sec-badge-critical">{{ __('Dihapus Permanen') }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--sec-text-muted); padding: 32px;">{{ __('Belum ada riwayat berkas yang dihapus oleh admin.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if (! empty($lockouts))
    <div class="sec-card sec-card-flush">
        <div class="sec-card-header">
            <h3 class="sec-card-title">{{ __('Active login lockouts') }}</h3>
        </div>
        <div class="sec-table-wrap">
            <table class="sec-table">
                <thead>
                    <tr>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('IP') }}</th>
                        <th>{{ __('Level') }}</th>
                        <th>{{ __('Remaining') }}</th>
                        <th style="text-align: right;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lockouts as $lockout)
                        <tr>
                            <td style="font-size: 12px;">{{ $lockout['email'] ?? '-' }}</td>
                            <td class="sec-font-mono" style="font-size: 12px;">{{ $lockout['ip_address'] ?? '-' }}</td>
                            <td><span class="sec-badge sec-badge-high">{{ $lockout['lockout_level'] ?? '-' }}</span></td>
                            <td style="font-size: 12px; color: var(--sec-text-muted);">{{ $lockout['remaining'] ?? '-' }}s</td>
                            <td style="text-align: right;">
                                <form method="POST" action="{{ route('pasak.dashboard.lockouts.destroy', $lockout['id']) }}" data-sec-confirm="{{ __('Buka kunci lockout login untuk akun/IP ini?') }}" data-confirm-title="{{ __('Konfirmasi Buka Lockout') }}" data-confirm-type="primary" data-confirm-btn="{{ __('Ya, Buka Lockout') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="sec-btn sec-btn-secondary sec-btn-sm">{{ __('Release') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="sec-card">
    <h3 class="sec-card-title" style="margin-bottom: 16px;">{{ __('Security checks') }}</h3>
    <div style="display: flex; flex-direction: column; gap: 20px;">
        @foreach (($report['categories'] ?? []) as $category)
            <div>
                <h4 style="font-size: 14px; font-weight: 600; color: var(--sec-text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin: 0 0 10px 0;">{{ $category['label'] ?? '' }}</h4>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    @foreach (($category['checks'] ?? []) as $check)
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 12px 14px; background: var(--sec-card-hover); border-radius: var(--sec-radius-sm); gap: 12px;">
                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="sec-badge {{ $DV::statusBadgeClass($check['status'] ?? 'info') }}">{{ $check['status'] ?? 'info' }}</span>
                                    <span style="font-size: 14px; font-weight: 600;">{{ $check['label'] ?? '' }}</span>
                                </div>
                                @if (! empty($check['detail']))
                                    <div style="font-size: 12px; color: var(--sec-text-muted);">{{ $check['detail'] }}</div>
                                @endif
                                @if (! empty($check['recommendation']))
                                    <div style="font-size: 12px; color: var(--sec-warning); font-weight: 500;">→ {{ $check['recommendation'] }}</div>
                                @endif
                            </div>
                            @if (($check['value'] ?? null) !== null)
                                <span class="sec-badge sec-badge-low sec-font-mono" style="font-size: 11px;">{{ $check['value'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Modal Detail & Bukti Berkas Mencurigakan -->
<div class="sec-modal-backdrop" id="suspiciousDetailModal" hidden style="display: none; align-items: center; justify-content: center; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 99999; padding: 16px;">
    <div class="sec-modal-box" role="dialog" aria-modal="true" style="max-width: 680px; width: 100%; border-radius: 12px; background: var(--sec-card); border: 1px solid var(--sec-border); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4); overflow: hidden;">
        <div class="sec-modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--sec-border-light);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span id="modalThreatBadge" class="sec-badge sec-badge-critical">critical</span>
                <h3 class="sec-card-title" style="font-size: 16px; margin: 0;">{{ __('Detail Temuan & Bukti Berkas') }}</h3>
            </div>
            <button type="button" class="sec-btn sec-btn-ghost sec-btn-sm" id="closeSuspiciousModalX" style="font-size: 20px; line-height: 1; padding: 4px 8px; cursor: pointer;">&times;</button>
        </div>
        <div class="sec-modal-body" style="padding: 20px; max-height: 70vh; overflow-y: auto; display: flex; flex-direction: column; gap: 16px;">
            <div class="sec-detail-grid">
                <div class="sec-detail-item">
                    <div class="sec-detail-label">{{ __('Nama Berkas') }}</div>
                    <div class="sec-detail-value sec-font-mono" id="modalFileName" style="font-weight: 600;">—</div>
                </div>
                <div class="sec-detail-item">
                    <div class="sec-detail-label">{{ __('Kategori Ancaman') }}</div>
                    <div class="sec-detail-value" id="modalCategory">—</div>
                </div>
                <div class="sec-detail-item">
                    <div class="sec-detail-label">{{ __('Ukuran Berkas') }}</div>
                    <div class="sec-detail-value sec-font-mono" id="modalSize">—</div>
                </div>
                <div class="sec-detail-item">
                    <div class="sec-detail-label">{{ __('Waktu Modifikasi') }}</div>
                    <div class="sec-detail-value sec-font-mono" id="modalModified">—</div>
                </div>
            </div>

            <div>
                <div class="sec-detail-label">{{ __('Jalur Lengkap Berkas') }}</div>
                <pre class="sec-code-block sec-font-mono" id="modalFilePath" style="margin-top: 6px; padding: 10px 12px; font-size: 12px; background: var(--sec-card-hover); border-radius: var(--sec-radius-sm); border: 1px solid var(--sec-border-light); white-space: pre-wrap; word-break: break-all;"></pre>
            </div>

            <div>
                <div class="sec-detail-label">{{ __('Alasan Deteksi Keamanan') }}</div>
                <div id="modalReason" style="margin-top: 6px; font-size: 13px; color: var(--sec-text); line-height: 1.5; padding: 10px 12px; background: rgba(239, 68, 68, 0.08); border: 1px solid var(--sec-danger-border); border-radius: var(--sec-radius-sm);">—</div>
            </div>

            <div id="modalSnippetSection">
                <div class="sec-detail-label" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>🚨 {{ __('Bukti Cuplikan Kode / Byte Berbahaya (Snippet Evidence)') }}</span>
                </div>
                <pre class="sec-code-block sec-font-mono" id="modalSnippet" style="margin-top: 6px; padding: 12px; font-size: 12.5px; background: #0f172a; color: #f87171; border-radius: var(--sec-radius-sm); border: 1px solid rgba(239, 68, 68, 0.3); overflow-x: auto; white-space: pre-wrap; word-break: break-all;"></pre>
            </div>
        </div>
        <div class="sec-modal-footer" style="padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--sec-border-light); background: var(--sec-card-hover); gap: 10px; flex-wrap: wrap;">
            <form id="modalDeleteForm" method="POST" action="{{ route('pasak.dashboard.server.suspicious-files.destroy') }}" data-sec-confirm="{{ __('PERINGATAN: Berkas mencurigakan ini akan dihapus secara permanen dari server. Lanjutkan?') }}" data-confirm-title="{{ __('Konfirmasi Hapus Berkas') }}" data-confirm-type="danger" data-confirm-btn="{{ __('Ya, Hapus Berkas') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="file_path" id="modalDeleteInput" value="">
                <button type="submit" class="sec-btn sec-btn-danger sec-btn-sm">🗑️ {{ __('Hapus Berkas Ini') }}</button>
            </form>
            <div style="display: flex; align-items: center; gap: 8px;">
                <a id="modalDownloadBtn" href="#" class="sec-btn sec-btn-secondary sec-btn-sm" style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                    <span>⬇️</span>
                    <span>{{ __('Unduh Berkas') }}</span>
                </a>
                <button type="button" id="modalVerifyBtn" class="sec-btn sec-btn-sm" style="background: rgba(34, 197, 94, 0.12); color: #16a34a; border: 1px solid #86efac; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">
                    <span>🛡️</span>
                    <span>{{ __('Tandai Aman & Verifikasi') }}</span>
                </button>
                <button type="button" class="sec-btn sec-btn-secondary sec-btn-sm" id="closeSuspiciousModalBtn">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Alert: Konfirmasi Verifikasi Berkas Aman -->
<div class="sec-modal-backdrop" id="verifySafeModal" hidden style="display: none; align-items: center; justify-content: center; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 99999; padding: 16px;">
    <div class="sec-modal-box" role="dialog" aria-modal="true" aria-labelledby="verifyModalTitle" style="max-width: 520px; width: 100%; border-radius: 12px; background: var(--sec-card); border: 1px solid var(--sec-border); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.2); overflow: hidden;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 20px 24px 0 24px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px; line-height: 1;">🛡️</span>
                <h3 id="verifyModalTitle" style="font-size: 18px; font-weight: 700; color: var(--sec-text); margin: 0;">
                    {{ __('Verifikasi Berkas Aman') }}
                </h3>
            </div>
            <button type="button" onclick="closeVerifyModal()" aria-label="Close" style="border: none; background: transparent; font-size: 18px; color: var(--sec-text-muted); cursor: pointer; padding: 4px; line-height: 1; border-radius: 6px;">
                ✕
            </button>
        </div>

        <p style="padding: 6px 24px 0 24px; margin: 0 0 16px 0; font-size: 13px; color: var(--sec-text-muted); line-height: 1.5;">
            {{ __('Apakah Anda yakin berkas ini aman dan bebas dari webshell/ancaman? Berkas akan dicatat dalam daftar integritas terverifikasi oleh admin dan dikecualikan dari pemindaian mencurigakan berikutnya.') }}
        </p>

        <form method="POST" action="{{ route('pasak.dashboard.server.suspicious-files.verify') }}" style="padding: 0 24px 20px 24px;">
            @csrf
            <input type="hidden" id="verifyModalFilePath" name="file_path" value="">

            <div style="margin-bottom: 14px;">
                <label class="sec-label" style="font-weight: 600; font-size: 13px; color: var(--sec-text); margin-bottom: 6px; display: block;">
                    {{ __('Jalur Berkas') }}
                </label>
                <div id="verifyModalDisplayPath" class="sec-font-mono" style="padding: 9px 12px; background: var(--sec-card-hover); border: 1px solid var(--sec-border); border-radius: 8px; font-size: 12.5px; word-break: break-all; color: var(--sec-text);">—</div>
            </div>

            <div style="margin-bottom: 18px;">
                <label class="sec-label" for="verifyModalNotes" style="font-weight: 600; font-size: 13px; color: var(--sec-text); margin-bottom: 6px; display: block;">
                    {{ __('Catatan Verifikasi (Opsional)') }}
                </label>
                <textarea id="verifyModalNotes" name="notes" rows="2" class="sec-input" style="width: 100%; padding: 9px 12px; font-size: 13px; border-radius: 8px; resize: vertical;" placeholder="{{ __('Contoh: Terverifikasi false positive, modul resmi tim developer, dll.') }}"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; padding-top: 14px; border-top: 1px solid var(--sec-border-light);">
                <button type="button" class="sec-btn sec-btn-secondary" onclick="closeVerifyModal()" style="border-radius: 8px; padding: 8px 18px; font-size: 13.5px;">
                    {{ __('Batal') }}
                </button>
                <button type="submit" style="border: 1px solid #86efac; background: #dcfce7; color: #15803d; border-radius: 8px; font-size: 13.5px; font-weight: 600; padding: 8px 18px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
                    <span>🛡️</span>
                    <span>{{ __('Ya, Tandai Aman & Terverifikasi') }}</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openVerifyModal(path) {
        var modal = document.getElementById('verifySafeModal');
        if (!modal) return;

        var inputPath = document.getElementById('verifyModalFilePath');
        var displayPath = document.getElementById('verifyModalDisplayPath');
        var inputNotes = document.getElementById('verifyModalNotes');

        if (inputPath) inputPath.value = path || '';
        if (displayPath) displayPath.textContent = path || '—';
        if (inputNotes) inputNotes.value = '';

        modal.hidden = false;
        modal.style.display = 'flex';
    }

    function closeVerifyModal() {
        var modal = document.getElementById('verifySafeModal');
        if (modal) {
            modal.hidden = true;
            modal.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('suspiciousDetailModal');
        var closeX = document.getElementById('closeSuspiciousModalX');
        var closeBtn = document.getElementById('closeSuspiciousModalBtn');
        var currentInspectedPath = '';

        function closeModal() {
            if (modal) {
                modal.hidden = true;
                modal.style.display = 'none';
            }
        }

        if (closeX) closeX.addEventListener('click', closeModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });
        }

        var verifyModalBackdrop = document.getElementById('verifySafeModal');
        if (verifyModalBackdrop) {
            verifyModalBackdrop.addEventListener('click', function(e) {
                if (e.target === verifyModalBackdrop) closeVerifyModal();
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeVerifyModal();
            }
        });

        // Trigger buka modal verifikasi dari tombol baris tabel
        document.querySelectorAll('.btn-verify-file').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                var path = this.dataset.path || '';
                openVerifyModal(path);
            });
        });

        // Trigger buka modal verifikasi dari dalam modal detail temuan
        var modalVerifyBtn = document.getElementById('modalVerifyBtn');
        if (modalVerifyBtn) {
            modalVerifyBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeModal();
                openVerifyModal(currentInspectedPath);
            });
        }

        document.querySelectorAll('.btn-inspect-file').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                var d = this.dataset;
                currentInspectedPath = d.path || '';

                document.getElementById('modalFileName').textContent = d.filename || d.path || '—';
                document.getElementById('modalCategory').textContent = d.category || 'Berkas Mencurigakan';
                document.getElementById('modalSize').textContent = d.size || '—';
                document.getElementById('modalModified').textContent = (d.modified || '—') + (d.modifiedHuman ? ' (' + d.modifiedHuman + ')' : '');
                document.getElementById('modalFilePath').textContent = d.path || '—';
                document.getElementById('modalReason').textContent = d.reason || '—';
                document.getElementById('modalDeleteInput').value = d.path || '';

                var downloadBtn = document.getElementById('modalDownloadBtn');
                if (downloadBtn) {
                    downloadBtn.href = d.downloadUrl || '#';
                }

                var badge = document.getElementById('modalThreatBadge');
                badge.textContent = d.threat || 'info';
                badge.className = 'sec-badge ' + (d.threat === 'critical' ? 'sec-badge-critical' : (d.threat === 'high' ? 'sec-badge-high' : 'sec-badge-low'));

                var snippetSection = document.getElementById('modalSnippetSection');
                var snippetEl = document.getElementById('modalSnippet');
                if (d.snippet && d.snippet.trim() !== '') {
                    snippetSection.style.display = 'block';
                    snippetEl.textContent = d.snippet;
                } else {
                    snippetSection.style.display = 'none';
                }

                if (modal) {
                    modal.hidden = false;
                    modal.style.display = 'flex';
                }
            });
        });
        // Penangan Interaktif Pengaturan Lokasi Server
        var btnDetectGps = document.getElementById('btnDetectGps');
        var btnClearLocation = document.getElementById('btnClearLocation');
        var locNameInput = document.getElementById('locNameInput');
        var locLatInput = document.getElementById('locLatInput');
        var locLonInput = document.getElementById('locLonInput');
        var locStatusMsg = document.getElementById('locStatusMsg');
        var locTimezoneSelect = document.getElementById('locTimezoneSelect');
        var currentClockTicker = document.getElementById('currentClockTicker');
        var btnDetectTimezone = document.getElementById('btnDetectTimezone');

        function updateServerClockTicker() {
            if (!currentClockTicker) return;
            var selectedTz = (locTimezoneSelect && locTimezoneSelect.value) ? locTimezoneSelect.value : '{{ $effectiveTz }}';
            try {
                var now = new Date();
                currentClockTicker.textContent = new Intl.DateTimeFormat('id-ID', {
                    timeZone: selectedTz,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                }).format(now);
            } catch (e) {
            }
        }
        setInterval(updateServerClockTicker, 1000);
        updateServerClockTicker();

        if (locTimezoneSelect) {
            locTimezoneSelect.addEventListener('change', updateServerClockTicker);
        }

        if (btnDetectTimezone) {
            btnDetectTimezone.addEventListener('click', function() {
                try {
                    var detectedTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    if (detectedTz && locTimezoneSelect) {
                        locTimezoneSelect.value = detectedTz;
                        updateServerClockTicker();
                        showLocStatus('{{ __('Zona waktu terdeteksi: ') }}' + detectedTz + '. {{ __('Klik Simpan untuk menerapkan.') }}', false);
                    }
                } catch (e) {
                    showLocStatus('{{ __('Gagal mendeteksi zona waktu peramban.') }}', true);
                }
            });
        }

        function showLocStatus(text, isError) {
            if (!locStatusMsg) return;
            locStatusMsg.textContent = text;
            locStatusMsg.style.display = 'inline';
            locStatusMsg.style.color = isError ? 'var(--sec-danger)' : 'var(--sec-success)';
            setTimeout(function() {
                locStatusMsg.style.display = 'none';
            }, 5000);
        }

        if (btnDetectGps) {
            btnDetectGps.addEventListener('click', function() {
                if (!navigator.geolocation) {
                    showLocStatus('{{ __('Geolokasi tidak didukung oleh peramban ini.') }}', true);
                    return;
                }
                btnDetectGps.disabled = true;
                btnDetectGps.innerHTML = '<span>⏳</span><span>{{ __('Mencari GPS...') }}</span>';
                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        btnDetectGps.disabled = false;
                        btnDetectGps.innerHTML = '<span>🎯</span><span>{{ __('Gunakan GPS Peramban') }}</span>';
                        if (locLatInput) locLatInput.value = pos.coords.latitude.toFixed(6);
                        if (locLonInput) locLonInput.value = pos.coords.longitude.toFixed(6);
                        if (locNameInput && (!locNameInput.value || locNameInput.value.trim() === '')) {
                            locNameInput.value = 'Node Administrator (GPS)';
                        }
                        showLocStatus('{{ __('Koordinat GPS berhasil diperoleh! Jangan lupa klik Simpan.') }}', false);
                    },
                    function(err) {
                        btnDetectGps.disabled = false;
                        btnDetectGps.innerHTML = '<span>🎯</span><span>{{ __('Gunakan GPS Peramban') }}</span>';
                        showLocStatus('{{ __('Gagal membaca GPS: ') }}' + (err.message || 'Izin ditolak'), true);
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            });
        }

        if (btnClearLocation) {
            btnClearLocation.addEventListener('click', function() {
                if (locNameInput) locNameInput.value = '';
                if (locLatInput) locLatInput.value = '';
                if (locLonInput) locLonInput.value = '';
                showLocStatus('{{ __('Bidang dikosongkan. Klik Simpan untuk kembali ke deteksi otomatis.') }}', false);
            });
        }

    });
</script>

@endsection
