@extends('pages.pasak.layout', ['heading' => __('Allowed IPs'), 'subheading' => __('Daftar IP yang selalu diizinkan — tidak pernah dicatat maupun diblokir')])

@section('content')
<div class="sec-grid-4">
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Total entries') }}</div>
        <div class="sec-stat-value">{{ number_format($stats['total'] ?? 0) }}</div>
    </div>
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Active') }}</div>
        <div class="sec-stat-value" style="color: var(--sec-success);">{{ number_format($stats['active'] ?? 0) }}</div>
    </div>
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Permanent') }}</div>
        <div class="sec-stat-value">{{ number_format($stats['permanent'] ?? 0) }}</div>
    </div>
</div>

<div class="sec-card">
    <h3 class="sec-card-title" style="margin-bottom: 14px;">{{ __('Allow an IP, wildcard, or CIDR') }}</h3>
    <form method="POST" action="{{ route('pasak.dashboard.allowed-ips.store') }}">
        @csrf
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: flex-end;">
            <div>
                <label class="sec-label" for="ip_address">{{ __('IP / wildcard / CIDR') }} *</label>
                <input id="ip_address" type="text" name="ip_address" required placeholder="203.0.113.10 / 192.168.1.* / 10.0.0.0/8" class="sec-input" style="width: 100%;" value="{{ old('ip_address') }}">
                @error('ip_address')<div style="font-size: 12px; color: var(--sec-danger); margin-top: 4px;">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="sec-label" for="label">{{ __('Label') }}</label>
                <input id="label" type="text" name="label" class="sec-input" style="width: 100%;" value="{{ old('label') }}" placeholder="{{ __('e.g. Office network') }}">
            </div>
            <div>
                <label class="sec-label" for="duration_hours">{{ __('Duration (hours, 0 = permanent)') }}</label>
                <input id="duration_hours" type="number" name="duration_hours" min="0" value="{{ old('duration_hours', 0) }}" class="sec-input" style="width: 100%;">
            </div>
            <div>
                <button type="submit" class="sec-btn sec-btn-primary">{{ __('Allow') }}</button>
            </div>
        </div>
    </form>
</div>

<div class="sec-card">
    <form method="GET" action="{{ route('pasak.dashboard.allowed-ips') }}" class="sec-toolbar">
        <div class="sec-toolbar-group">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search IP, label...') }}" class="sec-input" style="min-width: 220px;">
            <select name="status" class="sec-select">
                @foreach (['' => __('All'), 'active' => __('Active'), 'expired' => __('Expired'), 'permanent' => __('Permanent')] as $val => $label)
                    <option value="{{ $val }}" @selected(($filters['status'] ?? '') === $val)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="sec-btn sec-btn-primary">{{ __('Filter') }}</button>
        </div>
    </form>
</div>

<div class="sec-card sec-card-flush">
    <div class="sec-card-header">
        <h3 class="sec-card-title">{{ __('Entries') }} ({{ number_format($entries->total()) }})</h3>
    </div>
    <div class="sec-table-wrap">
        <table class="sec-table">
            <thead>
                <tr>
                    <th>{{ __('IP / range') }}</th>
                    <th>{{ __('Label') }}</th>
                    <th>{{ __('Expires') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th style="text-align: right;">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="sec-font-mono" style="font-weight: 600;">{{ $entry->ip_address }}</td>
                        <td style="font-size: 12px;">{{ $entry->label ?? '-' }}</td>
                        <td style="font-size: 12px;">{{ $entry->expires_at ?? __('Permanent') }}</td>
                        <td>
                            <span class="sec-badge {{ $entry->is_active ? 'sec-badge-success' : 'sec-badge-low' }}">
                                {{ $entry->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <form method="POST" action="{{ route('pasak.dashboard.allowed-ips.toggle', $entry->id) }}" style="display: inline;">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="sec-btn sec-btn-secondary sec-btn-sm">{{ $entry->is_active ? __('Disable') : __('Enable') }}</button>
                            </form>
                            <form method="POST" action="{{ route('pasak.dashboard.allowed-ips.destroy', $entry->id) }}" style="display: inline;" onsubmit="return confirm({{ json_encode(__('Remove this allow entry?')) }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sec-btn sec-btn-ghost sec-btn-sm">🗑️</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--sec-text-muted); padding: 32px;">{{ __('No allowed IPs yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($entries->hasPages())
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-top: 1px solid var(--sec-border); font-size: 13px; color: var(--sec-text-muted);">
            <span>{{ __('Page :current of :last', ['current' => $entries->currentPage(), 'last' => $entries->lastPage()]) }}</span>
            <div style="display: flex; gap: 8px;">
                @if ($entries->previousPageUrl())
                    <a href="{{ $entries->previousPageUrl() }}" class="sec-btn sec-btn-secondary sec-btn-sm">← {{ __('Prev') }}</a>
                @endif
                @if ($entries->nextPageUrl())
                    <a href="{{ $entries->nextPageUrl() }}" class="sec-btn sec-btn-secondary sec-btn-sm">{{ __('Next') }} →</a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
