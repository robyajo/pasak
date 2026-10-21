@extends('pages.pasak.layout', ['heading' => __('Log entry #:id', ['id' => $log->id]), 'subheading' => $log->event_type])

@section('content')
@php($DV = \Internal\Pasak\Support\DashboardView::class)

<a href="{{ $urls['index'] }}" class="sec-back-link">← {{ __('Back to logs') }}</a>

<div class="sec-card">
    <div class="sec-detail-grid">
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Time') }}</div>
            <div class="sec-detail-value sec-font-mono">{{ $log->created_at }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('IP address') }}</div>
            <div class="sec-detail-value sec-font-mono" style="font-weight: 600;">{{ $log->ip_address }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Threat level') }}</div>
            <div class="sec-detail-value"><span class="sec-badge {{ $DV::levelClass($log->threat_level) }}">{{ $DV::levelLabel($log->threat_level) }}</span></div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Blocked') }}</div>
            <div class="sec-detail-value">{{ $log->was_blocked ? __('Yes') : __('No') }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Method') }}</div>
            <div class="sec-detail-value sec-font-mono">{{ $log->method ?? '—' }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Event type') }}</div>
            <div class="sec-detail-value">{{ $log->event_type }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Rule') }}</div>
            <div class="sec-detail-value">{{ $log->rule_label ?? '—' }}</div>
        </div>
        <div class="sec-detail-item">
            <div class="sec-detail-label">{{ __('Action taken') }}</div>
            <div class="sec-detail-value">{{ $log->action_taken ?? '—' }}</div>
        </div>
        @if ($log->device_id || $log->local_ip)
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Device') }}</div>
                <div class="sec-detail-value sec-font-mono">{{ $log->device_id ?? '—' }} / {{ $log->local_ip ?? '—' }}</div>
            </div>
        @endif
    </div>
</div>

<div class="sec-card">
    <div class="sec-card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="sec-card-title">{{ __('Request path') }}</h3>
    </div>
    <div class="sec-card-body" style="padding-top: 12px;">
        <pre class="sec-code-block">{{ $log->full_url ?? $log->path ?? '—' }}</pre>
    </div>
</div>

@if ($log->evidence)
    <div class="sec-card">
        <div class="sec-card-header" style="border-bottom: none; padding-bottom: 0;">
            <h3 class="sec-card-title">{{ __('Evidence') }}</h3>
        </div>
        <div class="sec-card-body" style="padding-top: 12px;">
            <pre class="sec-code-block">{{ $log->evidence }}</pre>
        </div>
    </div>
@endif

<div class="sec-card">
    <div class="sec-card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="sec-card-title">{{ __('Request context') }}</h3>
    </div>
    <div class="sec-card-body" style="padding-top: 12px;">
        <div class="sec-detail-grid">
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('User agent') }}</div>
                <div class="sec-detail-value" style="font-size: 12.5px;">{{ $log->user_agent ?? '—' }}</div>
            </div>
            <div class="sec-detail-item">
                <div class="sec-detail-label">{{ __('Referer') }}</div>
                <div class="sec-detail-value" style="font-size: 12.5px;">{{ $log->referer ?? '—' }}</div>
            </div>
        </div>
        @if (! empty($log->meta))
            <div style="margin-top: 14px;">
                <div class="sec-detail-label">{{ __('Metadata') }}</div>
                <pre class="sec-code-block">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endif
    </div>
</div>

@if ($block)
    <div class="sec-card">
        <div class="sec-card-header">
            <h3 class="sec-card-title">{{ __('Related block') }}</h3>
            <a href="{{ route('pasak.dashboard.blocked-ips.show', $block->id) }}" class="sec-btn sec-btn-secondary sec-btn-sm">{{ __('View block') }}</a>
        </div>
        <div class="sec-card-body">
            <div class="sec-detail-grid">
                <div class="sec-detail-item">
                    <div class="sec-detail-label">{{ __('Status') }}</div>
                    <div class="sec-detail-value">{{ $block->is_active ? __('Active') : __('Inactive') }}</div>
                </div>
                <div class="sec-detail-item">
                    <div class="sec-detail-label">{{ __('Reason') }}</div>
                    <div class="sec-detail-value">{{ $block->reason ?? '—' }}</div>
                </div>
                <div class="sec-detail-item">
                    <div class="sec-detail-label">{{ __('Expires') }}</div>
                    <div class="sec-detail-value">{{ $block->expires_at ?? __('Permanent') }}</div>
                </div>
            </div>
        </div>
    </div>
@endif

@if (($relatedLogs ?? collect())->isNotEmpty())
    <div class="sec-card sec-card-flush">
        <div class="sec-card-header">
            <h3 class="sec-card-title">{{ __('Other logs from this IP') }}</h3>
        </div>
        <div class="sec-table-wrap">
            <table class="sec-table">
                <thead>
                    <tr>
                        <th>{{ __('Time') }}</th>
                        <th>{{ __('Event') }}</th>
                        <th>{{ __('Level') }}</th>
                        <th>{{ __('Path') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($relatedLogs as $related)
                        <tr>
                            <td class="sec-font-mono" style="font-size: 12px; white-space: nowrap;">{{ $related->created_at }}</td>
                            <td>{{ $related->event_type }}</td>
                            <td><span class="sec-badge {{ $DV::levelClass($related->threat_level) }}">{{ $DV::levelLabel($related->threat_level) }}</span></td>
                            <td class="sec-font-mono" style="font-size: 12px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $related->path }}">{{ $related->path }}</td>
                            <td style="text-align: right;">
                                <a href="{{ route('pasak.dashboard.logs.show', $related->id) }}" class="sec-btn sec-btn-ghost sec-btn-sm">{{ __('Detail') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <form method="POST" action="{{ $urls['destroy'] }}" onsubmit="return confirm({{ json_encode(__('Delete this log entry?')) }})">
        @csrf
        @method('DELETE')
        <button type="submit" class="sec-btn sec-btn-danger sec-btn-sm">{{ __('Delete entry') }}</button>
    </form>
</div>
@endsection
