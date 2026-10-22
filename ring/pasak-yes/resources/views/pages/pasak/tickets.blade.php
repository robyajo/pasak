@extends('pages.pasak.layout', ['heading' => __('Unblock Appeals'), 'subheading' => __('Tinjau permohonan buka blokir dari pengguna terblokir')])

@section('content')
<div class="sec-grid-4">
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Pending') }}</div>
        <div class="sec-stat-value" style="color: var(--sec-warning);">{{ number_format($stats['pending'] ?? 0) }}</div>
    </div>
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Approved') }}</div>
        <div class="sec-stat-value" style="color: var(--sec-success);">{{ number_format($stats['approved'] ?? 0) }}</div>
    </div>
    <div class="sec-stat-card">
        <div class="sec-stat-label">{{ __('Rejected') }}</div>
        <div class="sec-stat-value sec-text-danger">{{ number_format($stats['rejected'] ?? 0) }}</div>
    </div>
</div>

<div class="sec-card">
    <form method="GET" action="{{ route('pasak.dashboard.tickets') }}" class="sec-toolbar" style="margin-bottom: 0;">
        <div class="sec-toolbar-group" style="flex: 1;">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search ticket, IP, name...') }}" class="sec-input" style="min-width: 200px; flex: 1;">
            <select name="status" class="sec-select">
                @foreach (['' => __('All'), 'pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected')] as $val => $label)
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
            <a href="{{ route('pasak.dashboard.tickets') }}" class="sec-btn sec-btn-secondary">{{ __('Reset') }}</a>
        </div>
    </form>
</div>

<div class="sec-card sec-card-flush">
    <div class="sec-card-header">
        <h3 class="sec-card-title">{{ __('Entries') }} ({{ number_format($tickets->total()) }})</h3>
    </div>
    <div class="sec-table-wrap">
        <table class="sec-table">
            <thead>
                <tr>
                    <th>{{ __('Ticket') }}</th>
                    <th>{{ __('Requester') }}</th>
                    <th>{{ __('IP address') }}</th>
                    <th>{{ __('Reason') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th style="text-align: right;">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($tickets ?? []) as $ticket)
                    <tr>
                        <td class="sec-font-mono" style="font-weight: 600;">{{ $ticket->ticket_number }}</td>
                        <td>{{ $ticket->name }}<br><span style="font-size: 12px; color: var(--sec-text-muted);">{{ $ticket->email }}</span></td>
                        <td class="sec-font-mono">{{ $ticket->ip_address }}</td>
                        <td style="max-width: 240px; font-size: 12px;" title="{{ $ticket->reason }}">{{ \Illuminate\Support\Str::limit($ticket->reason, 60) }}</td>
                        <td>
                            <span class="sec-badge {{ $ticket->status === 'approved' ? 'sec-badge-success' : ($ticket->status === 'rejected' ? 'sec-badge-critical' : 'sec-badge-high') }}">
                                {{ $ticket->status }}
                            </span>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="{{ route('pasak.dashboard.tickets.show', $ticket->id) }}" class="sec-btn sec-btn-ghost sec-btn-sm">{{ __('Detail') }}</a>
                            @if ($ticket->status === 'pending')
                                <form method="POST" action="{{ route('pasak.dashboard.tickets.respond', $ticket->id) }}" style="display: inline;">
                                    @csrf
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="sec-btn sec-btn-primary sec-btn-sm">{{ __('Approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('pasak.dashboard.tickets.respond', $ticket->id) }}" style="display: inline;">
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="sec-btn sec-btn-secondary sec-btn-sm">{{ __('Reject') }}</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('pasak.dashboard.tickets.destroy', $ticket->id) }}" style="display: inline;" onsubmit="return confirm({{ json_encode(__('Delete this ticket?')) }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sec-btn sec-btn-ghost sec-btn-sm">🗑️</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--sec-text-muted); padding: 32px;">{{ __('No tickets.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="sec-datatable-footer">
        <div>
            @if ($tickets->total() > 0)
                {{ __('Showing') }} <span style="font-weight: 600; color: var(--sec-text);">{{ $tickets->firstItem() }}</span>
                {{ __('to') }} <span style="font-weight: 600; color: var(--sec-text);">{{ $tickets->lastItem() }}</span>
                {{ __('of') }} <span style="font-weight: 600; color: var(--sec-text);">{{ number_format($tickets->total()) }}</span> {{ __('entries') }}
            @else
                {{ __('Showing 0 entries') }}
            @endif
        </div>
        <div>
            {{ $tickets->links('pages.pasak.pagination') }}
        </div>
    </div>
</div>
@endsection
