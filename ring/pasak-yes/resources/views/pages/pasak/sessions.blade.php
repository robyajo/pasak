@extends('pages.pasak.layout', ['heading' => __('User Sessions'), 'subheading' => __('Sesi login aktif, riwayat perangkat, dan IP terpercaya')])

@section('content')
<div class="sec-grid-4">
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Online now (5 min)') }}</div>
        <div class="sec-stat-value" style="color: var(--sec-success);">{{ number_format($onlineCount ?? 0) }}</div>
    </div>
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Trusted IPs') }}</div>
        <div class="sec-stat-value">{{ number_format(count($trustedIps ?? [])) }}</div>
    </div>
</div>

<div class="sec-card">
    <h3 class="sec-card-title" style="margin-bottom: 14px;">{{ __('Trust my current IP') }}</h3>
    <form method="POST" action="{{ route('pasak.dashboard.trusted-ips.store-my-ip') }}">
        @csrf
        <button type="submit" class="sec-btn sec-btn-primary">{{ __('Save my IP') }}</button>
    </form>
</div>

<div class="sec-card">
    <form method="GET" action="{{ route('pasak.dashboard.sessions') }}" class="sec-toolbar" style="margin-bottom: 0;">
        <div class="sec-toolbar-group" style="flex: 1;">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search IP, browser, user...') }}" class="sec-input" style="min-width: 200px; flex: 1;">
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
            <a href="{{ route('pasak.dashboard.sessions') }}" class="sec-btn sec-btn-secondary">{{ __('Reset') }}</a>
        </div>
    </form>
</div>

<div class="sec-card sec-card-flush">
    <div class="sec-card-header">
        <h3 class="sec-card-title">{{ __('Login history') }} ({{ number_format($logins->total()) }})</h3>
    </div>
    <div class="sec-table-wrap">
        <table class="sec-table">
            <thead>
                <tr>
                    <th>{{ __('User') }}</th>
                    <th>{{ __('IP address') }}</th>
                    <th>{{ __('Browser') }}</th>
                    <th>{{ __('Last activity') }}</th>
                    <th style="text-align: right;">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($logins ?? []) as $login)
                    <tr>
                        <td>{{ $login->user?->name ?? $login->user?->email ?? '-' }}</td>
                        <td class="sec-font-mono" style="font-weight: 600;">{{ $login->ip_address }}</td>
                        <td style="font-size: 12px;">{{ $login->browser }} · {{ $login->operating_system }}</td>
                        <td style="font-size: 12px; color: var(--sec-text-muted);">{{ $login->last_activity_at }}</td>
                        <td style="text-align: right; white-space: nowrap;">
                            @if ($login->session_id)
                                <form method="POST" action="{{ route('pasak.dashboard.sessions.destroy', $login->session_id) }}" style="display: inline;" onsubmit="return confirm({{ json_encode(__('Terminate this session?')) }})">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="sec-btn sec-btn-secondary sec-btn-sm">{{ __('Logout') }}</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('pasak.dashboard.user-sessions.destroy', $login->id) }}" style="display: inline;" onsubmit="return confirm({{ json_encode(__('Delete this session record?')) }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sec-btn sec-btn-ghost sec-btn-sm">🗑️</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--sec-text-muted); padding: 32px;">{{ __('No sessions recorded.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="sec-datatable-footer">
        <div>
            @if ($logins->total() > 0)
                {{ __('Showing') }} <span style="font-weight: 600; color: var(--sec-text);">{{ $logins->firstItem() }}</span>
                {{ __('to') }} <span style="font-weight: 600; color: var(--sec-text);">{{ $logins->lastItem() }}</span>
                {{ __('of') }} <span style="font-weight: 600; color: var(--sec-text);">{{ number_format($logins->total()) }}</span> {{ __('entries') }}
            @else
                {{ __('Showing 0 entries') }}
            @endif
        </div>
        <div>
            {{ $logins->links('pages.pasak.pagination') }}
        </div>
    </div>
</div>

<div class="sec-card sec-card-flush">
    <div class="sec-card-header">
        <h3 class="sec-card-title">{{ __('Trusted IPs') }}</h3>
    </div>
    <div class="sec-table-wrap">
        <table class="sec-table">
            <thead>
                <tr>
                    <th>{{ __('User') }}</th>
                    <th>{{ __('IP address') }}</th>
                    <th>{{ __('Device') }}</th>
                    <th style="text-align: right;">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($trustedIps ?? []) as $ip)
                    <tr>
                        <td>{{ $ip->user?->name ?? $ip->user?->email ?? '-' }}</td>
                        <td class="sec-font-mono" style="font-weight: 600;">{{ $ip->ip_address }}</td>
                        <td style="font-size: 12px;">{{ $ip->device_name ?? '-' }}</td>
                        <td style="text-align: right;">
                            <form method="POST" action="{{ route('pasak.dashboard.trusted-ips.destroy', $ip->id) }}" onsubmit="return confirm({{ json_encode(__('Remove this trusted IP?')) }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sec-btn sec-btn-ghost sec-btn-sm">🗑️</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--sec-text-muted); padding: 32px;">{{ __('No trusted IPs.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
