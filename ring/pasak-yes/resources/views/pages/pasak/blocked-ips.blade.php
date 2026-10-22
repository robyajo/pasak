@extends('pages.pasak.layout', ['heading' => __('Blocked IPs'), 'subheading' => __('Karantina IP dan perangkat aktif beserta riwayatnya')])

@section('content')
<div class="sec-grid-4">
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Total blocks') }}</div>
        <div class="sec-stat-value">{{ number_format($stats['total'] ?? 0) }}</div>
    </div>
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Active') }}</div>
        <div class="sec-stat-value sec-text-danger">{{ number_format($stats['active'] ?? 0) }}</div>
    </div>
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Permanent') }}</div>
        <div class="sec-stat-value">{{ number_format($stats['permanent'] ?? 0) }}</div>
    </div>
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Total hits') }}</div>
        <div class="sec-stat-value">{{ number_format($stats['hits'] ?? 0) }}</div>
    </div>
</div>

<div class="sec-card">
    <h3 class="sec-card-title" style="margin-bottom: 14px;">{{ __('Block an IP manually') }}</h3>
    <form method="POST" action="{{ route('pasak.dashboard.blocked-ips.store') }}">
        @csrf
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: flex-end;">
            <div>
                <label class="sec-label" for="ip_address">{{ __('IP address') }} *</label>
                <input id="ip_address" type="text" name="ip_address" required placeholder="203.0.113.50" class="sec-input" style="width: 100%;" value="{{ old('ip_address') }}">
                @error('ip_address')<div style="font-size: 12px; color: var(--sec-danger); margin-top: 4px;">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="sec-label" for="duration_hours">{{ __('Duration (hours, 0 = permanent)') }}</label>
                <input id="duration_hours" type="number" name="duration_hours" min="0" value="{{ old('duration_hours', 24) }}" class="sec-input" style="width: 100%;">
            </div>
            <div>
                <label class="sec-label" for="reason">{{ __('Reason') }}</label>
                <input id="reason" type="text" name="reason" class="sec-input" style="width: 100%;" value="{{ old('reason') }}">
            </div>
            <div>
                <button type="submit" class="sec-btn sec-btn-danger">{{ __('Block IP') }}</button>
            </div>
        </div>
    </form>
</div>

<div class="sec-card">
    <form method="GET" action="{{ route('pasak.dashboard.blocked-ips') }}" class="sec-toolbar" style="margin-bottom: 0;">
        <div class="sec-toolbar-group" style="flex: 1;">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search IP, device, reason...') }}" class="sec-input" style="min-width: 200px; flex: 1;">
            <select name="status" class="sec-select">
                @foreach (['' => __('All'), 'active' => __('Active'), 'expired' => __('Expired'), 'permanent' => __('Permanent')] as $val => $label)
                    <option value="{{ $val }}" @selected(($filters['status'] ?? '') === $val)>{{ $label }}</option>
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
            <a href="{{ route('pasak.dashboard.blocked-ips') }}" class="sec-btn sec-btn-secondary">{{ __('Reset') }}</a>
        </div>
    </form>
</div>

<div class="sec-card sec-card-flush">
    <div class="sec-card-header">
        <h3 class="sec-card-title">{{ __('Entries') }} ({{ number_format($blocks->total()) }})</h3>
    </div>
    <div class="sec-table-wrap">
        <table class="sec-table">
            <thead>
                <tr>
                    <th>{{ __('IP address') }}</th>
                    <th>{{ __('Scope') }}</th>
                    <th>{{ __('Reason') }}</th>
                    <th>{{ __('Expires') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th style="text-align: right;">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($blocks as $block)
                    <tr>
                        <td class="sec-font-mono" style="font-weight: 600;">{{ $block->ip_address }}</td>
                        <td><span class="sec-badge sec-badge-low">{{ $block->block_scope }}</span></td>
                        <td style="max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $block->reason }}">{{ $block->reason }}</td>
                        <td style="font-size: 12px;">{{ $block->expires_at ?? __('Permanent') }}</td>
                        <td>
                            <span class="sec-badge {{ $block->is_active ? 'sec-badge-critical' : 'sec-badge-low' }}">
                                {{ $block->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="{{ route('pasak.dashboard.blocked-ips.show', $block->id) }}" class="sec-btn sec-btn-ghost sec-btn-sm">{{ __('Detail') }}</a>
                            <form method="POST" action="{{ route('pasak.dashboard.blocked-ips.toggle', $block->id) }}" style="display: inline;">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="sec-btn sec-btn-secondary sec-btn-sm">{{ $block->is_active ? __('Disable') : __('Enable') }}</button>
                            </form>
                            <form method="POST" action="{{ route('pasak.dashboard.blocked-ips.destroy', $block->id) }}" style="display: inline;" onsubmit="return confirm({{ json_encode(__('Remove this block entirely?')) }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sec-btn sec-btn-ghost sec-btn-sm">🗑️</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--sec-text-muted); padding: 32px;">{{ __('No blocked IPs.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="sec-datatable-footer">
        <div>
            @if ($blocks->total() > 0)
                {{ __('Showing') }} <span style="font-weight: 600; color: var(--sec-text);">{{ $blocks->firstItem() }}</span>
                {{ __('to') }} <span style="font-weight: 600; color: var(--sec-text);">{{ $blocks->lastItem() }}</span>
                {{ __('of') }} <span style="font-weight: 600; color: var(--sec-text);">{{ number_format($blocks->total()) }}</span> {{ __('entries') }}
            @else
                {{ __('Showing 0 entries') }}
            @endif
        </div>
        <div>
            {{ $blocks->links('pages.pasak.pagination') }}
        </div>
    </div>
</div>
@endsection
