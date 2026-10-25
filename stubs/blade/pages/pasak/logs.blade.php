@extends('pages.pasak.layout', ['heading' => __('Security Logs'), 'subheading' => __('Jejak audit ancaman, pola terdeteksi, dan aksi mitigasi')])

@section('content')
@php($DV = \Internal\Pasak\Support\DashboardView::class)

<div class="sec-card">
    <form method="GET" action="{{ route('pasak.dashboard.logs') }}" class="sec-toolbar" style="margin-bottom: 0;">
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
            <a href="{{ route('pasak.dashboard.logs') }}" class="sec-btn sec-btn-secondary">{{ __('Reset') }}</a>
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
                    <th>{{ __('IP address') }}</th>
                    <th>{{ __('Event') }}</th>
                    <th>{{ __('Level') }}</th>
                    <th>{{ __('Path') }}</th>
                    <th>{{ __('Blocked') }}</th>
                    <th style="text-align: right;">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="sec-font-mono" style="font-size: 12px; white-space: nowrap;">{{ $log->created_at }}</td>
                        <td class="sec-font-mono" style="font-weight: 600;">{{ $log->ip_address }}</td>
                        <td>{{ $log->event_type }}</td>
                        <td><span class="sec-badge {{ $DV::levelClass($log->threat_level) }}">{{ $DV::levelLabel($log->threat_level) }}</span></td>
                        <td class="sec-font-mono" style="font-size: 12px; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $log->path }}">{{ $log->path }}</td>
                        <td>{{ $log->was_blocked ? __('Yes') : __('No') }}</td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="{{ route('pasak.dashboard.logs.show', $log->id) }}" class="sec-btn sec-btn-ghost sec-btn-sm">{{ __('Detail') }}</a>
                            <form method="POST" action="{{ route('pasak.dashboard.logs.destroy', $log->id) }}" style="display: inline;" data-sec-confirm="{{ __('Hapus entri log ancaman ini secara permanen?') }}" data-confirm-title="{{ __('Konfirmasi Hapus Log') }}" data-confirm-type="danger" data-confirm-btn="{{ __('Ya, Hapus') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sec-btn sec-btn-ghost sec-btn-sm">🗑️</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--sec-text-muted); padding: 32px;">{{ __('No logs found.') }}</td>
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
@endsection
