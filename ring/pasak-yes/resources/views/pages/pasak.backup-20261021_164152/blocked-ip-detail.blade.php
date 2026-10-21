@extends('pages.pasak.layout', ['heading' => __('Block detail'), 'subheading' => $block->ip_address])

@section('content')
<a href="{{ $urls['index'] }}" class="sec-back-link">← {{ __('Back to blocked IPs') }}</a>

<div class="sec-card">
    <div class="sec-detail-grid">
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('IP address') }}</div>
            <div class="sec-detail-value sec-font-mono" style="font-weight: 600;">{{ $block->ip_address }}</div>
        </div>
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
            <div class="sec-detail-value">{{ $block->blocked_at ?? $block->created_at }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Expires') }}</div>
            <div class="sec-detail-value">{{ $block->expires_at ?? __('Permanent') }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Hits since block') }}</div>
            <div class="sec-detail-value">{{ number_format((int) $block->hit_count) }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Last hit') }}</div>
            <div class="sec-detail-value">{{ $block->last_hit_at ?? '—' }}</div>
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

@if (($relatedLogs ?? collect())->isNotEmpty())
    <div class="sec-card sec-card-flush">
        <div class="sec-card-header">
            <h3 class="sec-card-title">{{ __('Triggering logs') }} ({{ $relatedLogs->count() }})</h3>
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
    <form method="POST" action="{{ $urls['toggle'] }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="sec-btn sec-btn-secondary sec-btn-sm">{{ $block->is_active ? __('Disable block') : __('Re-enable block') }}</button>
    </form>
    <form method="POST" action="{{ $urls['destroy'] }}" onsubmit="return confirm({{ json_encode(__('Unblock this IP?')) }})">
        @csrf
        @method('DELETE')
        <button type="submit" class="sec-btn sec-btn-danger sec-btn-sm">{{ __('Unblock') }}</button>
    </form>
</div>
@endsection
