@extends('pages.pasak.layout', ['heading' => __('Ticket :number', ['number' => $ticket->ticket_number]), 'subheading' => $ticket->name . ' — ' . $ticket->ip_address])

@section('content')
<a href="{{ $urls['index'] }}" class="sec-back-link">← {{ __('Back to appeals') }}</a>

<div class="sec-card">
    <div class="sec-detail-grid">
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Status') }}</div>
            <div class="sec-detail-value">
                <span class="sec-badge {{ $ticket->status === 'approved' ? 'sec-badge-success' : ($ticket->status === 'rejected' ? 'sec-badge-critical' : 'sec-badge-high') }}">
                    {{ $ticket->status }}
                </span>
            </div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('IP address') }}</div>
            <div class="sec-detail-value sec-font-mono" style="font-weight: 600;">{{ $ticket->ip_address }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Requester') }}</div>
            <div class="sec-detail-value">{{ $ticket->name }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Email') }}</div>
            <div class="sec-detail-value">{{ $ticket->email }}</div>
        </div>
        @if ($ticket->phone)
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Phone') }}</div>
                <div class="sec-detail-value">{{ $ticket->phone }}</div>
            </div>
        @endif
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Submitted') }}</div>
            <div class="sec-detail-value">{{ $ticket->created_at }}</div>
        </div>
        @if ($ticket->device_id || $ticket->local_ip)
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Device') }}</div>
                <div class="sec-detail-value sec-font-mono">{{ $ticket->device_id ?? '—' }} / {{ $ticket->local_ip ?? '—' }}</div>
            </div>
        @endif
        @if ($ticket->resolved_at)
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Resolved') }}</div>
                <div class="sec-detail-value">{{ $ticket->resolved_at }}{{ $ticket->resolver ? ' — ' . $ticket->resolver->name : '' }}</div>
            </div>
        @endif
    </div>
</div>

<div class="sec-card">
    <div class="sec-card-body">
        <div class="sec-detail-label">{{ __('Requester reason') }}</div>
        <pre class="sec-code-block">{{ $ticket->reason }}</pre>
        @if ($ticket->user_agent)
            <div class="sec-detail-label" style="margin-top: 14px;">{{ __('User agent') }}</div>
            <div class="sec-detail-value" style="font-size: 12.5px;">{{ $ticket->user_agent }}</div>
        @endif
        @if ($ticket->admin_notes)
            <div class="sec-detail-label" style="margin-top: 14px;">{{ __('Admin notes') }}</div>
            <pre class="sec-code-block">{{ $ticket->admin_notes }}</pre>
        @endif
    </div>
</div>

@if ($ticket->blockedIp)
    <div class="sec-card">
        <div class="sec-card-header">
            <h3 class="sec-card-title">{{ __('Related block') }}</h3>
            <a href="{{ route('pasak.dashboard.blocked-ips.show', $ticket->blockedIp->id) }}" class="sec-btn sec-btn-secondary sec-btn-sm">{{ __('View block') }}</a>
        </div>
        <div class="sec-card-body">
            <div class="sec-detail-grid">
                <div class="sec-detail-item">
                    <div class="sec-detail-label">{{ __('Status') }}</div>
                    <div class="sec-detail-value">{{ $ticket->blockedIp->is_active ? __('Active') : __('Inactive') }}</div>
                </div>
                <div class="sec-detail-item">
                    <div class="sec-detail-label">{{ __('Reason') }}</div>
                    <div class="sec-detail-value">{{ $ticket->blockedIp->reason ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>
@endif

@if (($relatedLogs ?? collect())->isNotEmpty())
    <div class="sec-card sec-card-flush">
        <div class="sec-card-header">
            <h3 class="sec-card-title">{{ __('Recent logs from this IP') }}</h3>
        </div>
        <div class="sec-table-wrap">
            <table class="sec-table">
                <thead>
                    <tr>
                        <th>{{ __('Time') }}</th>
                        <th>{{ __('Event') }}</th>
                        <th>{{ __('Path') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($relatedLogs as $log)
                        <tr>
                            <td class="sec-font-mono" style="font-size: 12px; white-space: nowrap;">{{ $log->created_at }}</td>
                            <td>{{ $log->event_type }}</td>
                            <td class="sec-font-mono" style="font-size: 12px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $log->path }}">{{ $log->path }}</td>
                            <td style="text-align: right;">
                                <a href="{{ route('pasak.dashboard.logs.show', $log->id) }}" class="sec-btn sec-btn-ghost sec-btn-sm">{{ __('Detail') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if ($ticket->isPending())
    <div class="sec-card">
        <div class="sec-card-header">
            <h3 class="sec-card-title">{{ __('Respond to appeal') }}</h3>
        </div>
        <div class="sec-card-body">
            <form method="POST" action="{{ $urls['respond'] }}" style="display: flex; flex-direction: column; gap: 12px;">
                @csrf
                <div>
                    <label class="sec-label" for="admin_notes">{{ __('Admin notes') }}</label>
                    <textarea id="admin_notes" name="admin_notes" rows="3" class="sec-textarea" placeholder="{{ __('Reason for approval or rejection...') }}"></textarea>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" name="action" value="approve" class="sec-btn sec-btn-primary" data-sec-confirm="{{ __('Setujui permohonan banding dan buka blokir IP ini secara otomatis?') }}" data-confirm-title="{{ __('Konfirmasi Persetujuan Banding') }}" data-confirm-type="primary" data-confirm-btn="{{ __('Ya, Setujui & Buka') }}">{{ __('Approve & unblock') }}</button>
                    <button type="submit" name="action" value="reject" class="sec-btn sec-btn-danger" data-sec-confirm="{{ __('Tolak permohonan banding pembukaan blokir ini?') }}" data-confirm-title="{{ __('Konfirmasi Penolakan Banding') }}" data-confirm-type="warning" data-confirm-btn="{{ __('Ya, Tolak Banding') }}">{{ __('Reject') }}</button>
                </div>
            </form>
        </div>
    </div>
@else
    <form method="POST" action="{{ $urls['destroy'] }}" data-sec-confirm="{{ __('Hapus tiket banding ini secara permanen?') }}" data-confirm-title="{{ __('Konfirmasi Hapus Tiket') }}" data-confirm-type="danger" data-confirm-btn="{{ __('Ya, Hapus') }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="sec-btn sec-btn-danger sec-btn-sm">{{ __('Delete ticket') }}</button>
    </form>
@endif
@endsection
