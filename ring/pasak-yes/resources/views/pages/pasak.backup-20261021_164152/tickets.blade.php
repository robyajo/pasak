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
    <form method="GET" action="{{ route('pasak.dashboard.tickets') }}" class="sec-toolbar">
        <div class="sec-toolbar-group">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search ticket, IP, name...') }}" class="sec-input" style="min-width: 220px;">
            <select name="status" class="sec-select">
                @foreach (['' => __('All'), 'pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected')] as $val => $label)
                    <option value="{{ $val }}" @selected(($filters['status'] ?? '') === $val)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="sec-btn sec-btn-primary">{{ __('Filter') }}</button>
        </div>
    </form>
</div>

<div class="sec-card sec-card-flush">
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
    @if (($tickets ?? null) && $tickets->hasPages())
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-top: 1px solid var(--sec-border); font-size: 13px; color: var(--sec-text-muted);">
            <span>{{ __('Page :current of :last', ['current' => $tickets->currentPage(), 'last' => $tickets->lastPage()]) }}</span>
            <div style="display: flex; gap: 8px;">
                @if ($tickets->previousPageUrl())
                    <a href="{{ $tickets->previousPageUrl() }}" class="sec-btn sec-btn-secondary sec-btn-sm">← {{ __('Prev') }}</a>
                @endif
                @if ($tickets->nextPageUrl())
                    <a href="{{ $tickets->nextPageUrl() }}" class="sec-btn sec-btn-secondary sec-btn-sm">{{ __('Next') }} →</a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
