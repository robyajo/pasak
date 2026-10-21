@extends('pages.pasak.layout', ['heading' => __('Security Logs'), 'subheading' => __('Jejak audit ancaman, pola terdeteksi, dan aksi mitigasi')])

@section('content')
@php($DV = \Internal\Pasak\Support\DashboardView::class)

<div class="sec-card">
    <form method="GET" action="{{ route('pasak.dashboard.logs') }}" class="sec-toolbar">
        <div class="sec-toolbar-group">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search path, evidence, IP...') }}" class="sec-input" style="min-width: 220px;">
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
            <button type="submit" class="sec-btn sec-btn-primary">{{ __('Filter') }}</button>
        </div>
        <div class="sec-toolbar-group">
            <a href="{{ route('pasak.dashboard.logs') }}" class="sec-btn sec-btn-secondary">{{ __('Reset') }}</a>
        </div>
    </form>
</div>

<div class="sec-card sec-card-flush">
    <div class="sec-card-header">
        <h3 class="sec-card-title">{{ __('Entries') }} ({{ number_format($logs->total()) }})</h3>
        <form method="POST" action="{{ route('pasak.dashboard.logs.clear') }}" onsubmit="return confirm({{ json_encode(__('Clear ALL security logs? This cannot be undone.')) }})">
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
                            <form method="POST" action="{{ route('pasak.dashboard.logs.destroy', $log->id) }}" onsubmit="return confirm({{ json_encode(__('Delete this log entry?')) }})" style="display: inline;">
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
    @if ($logs->hasPages())
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-top: 1px solid var(--sec-border); font-size: 13px; color: var(--sec-text-muted);">
            <span>{{ __('Page :current of :last', ['current' => $logs->currentPage(), 'last' => $logs->lastPage()]) }}</span>
            <div style="display: flex; gap: 8px;">
                @if ($logs->previousPageUrl())
                    <a href="{{ $logs->previousPageUrl() }}" class="sec-btn sec-btn-secondary sec-btn-sm">← {{ __('Prev') }}</a>
                @endif
                @if ($logs->nextPageUrl())
                    <a href="{{ $logs->nextPageUrl() }}" class="sec-btn sec-btn-secondary sec-btn-sm">{{ __('Next') }} →</a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
