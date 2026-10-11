<?php

use Internal\Pasak\Models\IpUnblockRequest;
use Internal\Pasak\Models\SecurityLog;
use Internal\Pasak\Services\PasakService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ticket Detail')] class extends Component {
    public IpUnblockRequest $ticket;

    public bool $showRespondModal = false;

    public string $respondingAction = 'approve';

    public string $adminNotes = '';

    public function mount(int $id): void
    {
        $ticket = IpUnblockRequest::query()->find($id);
        abort_if(! $ticket, 404);
        $this->ticket = $ticket;
    }

    public function openRespond(string $action): void
    {
        $this->respondingAction = $action === 'reject' ? 'reject' : 'approve';
        $this->adminNotes = '';
        $this->resetErrorBag();
        $this->showRespondModal = true;
    }

    public function closeRespondModal(): void
    {
        $this->showRespondModal = false;
        $this->adminNotes = '';
    }

    public function confirmRespond(): void
    {
        $this->validate([
            'adminNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $ticket = IpUnblockRequest::query()->find($this->ticket->getKey());

        if (! $ticket || ! $ticket->isPending()) {
            $this->showRespondModal = false;

            return;
        }

        $approve = $this->respondingAction === 'approve';

        $ticket->status = $approve ? 'approved' : 'rejected';
        $ticket->admin_notes = $this->adminNotes !== ''
            ? $this->adminNotes
            : ($approve ? __('Request approved.') : __('Request rejected.'));
        $ticket->resolved_by = auth()->id();
        $ticket->resolved_at = now();
        $ticket->save();

        if ($approve) {
            app(PasakService::class)->unblockIp($ticket->ip_address, $ticket->device_id);
        }

        $this->ticket = $ticket->refresh();
        $this->showRespondModal = false;

        session()->flash('security_message', $approve
            ? __('Ticket :ticket approved and IP unblocked.', ['ticket' => $ticket->ticket_number])
            : __('Ticket :ticket rejected.', ['ticket' => $ticket->ticket_number]));
    }

    public function destroy(int $id): void
    {
        IpUnblockRequest::query()->whereKey($id)->delete();

        $this->redirect(route('pasak.dashboard.tickets'), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $this->ticket->loadMissing(['blockedIp', 'resolver']);

        return [
            'relatedLogs' => SecurityLog::query()
                ->where('ip_address', $this->ticket->ip_address)
                ->latest('id')
                ->limit(10)
                ->get(),
        ];
    }
}; ?>

<div>
    <x-pages::pasak.layout :heading="__('Ticket :number', ['number' => $ticket->ticket_number])" :subheading="$ticket->name . ' — ' . $ticket->ip_address">
        <a href="{{ route('pasak.dashboard.tickets') }}" class="sec-back-link" wire:navigate>← {{ __('Back to appeals') }}</a>

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
                    <a href="{{ route('pasak.dashboard.blocked-ips.show', $ticket->blockedIp->id) }}" class="sec-btn sec-btn-secondary sec-btn-sm" wire:navigate>{{ __('View block') }}</a>
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

        @if ($relatedLogs->isNotEmpty())
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
                                        <a href="{{ route('pasak.dashboard.logs.show', $log->id) }}" class="sec-btn sec-btn-ghost sec-btn-sm" wire:navigate>{{ __('Detail') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($ticket->isPending())
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="sec-btn sec-btn-primary" wire:click="openRespond('approve')">
                    {{ __('Approve & unblock') }}
                </button>
                <button type="button" class="sec-btn sec-btn-danger" wire:click="openRespond('reject')">
                    {{ __('Reject') }}
                </button>
            </div>
        @else
            <button
                type="button"
                class="sec-btn sec-btn-danger sec-btn-sm"
                wire:click="destroy({{ $ticket->id }})"
                wire:confirm="{{ __('Delete this ticket?') }}"
            >
                {{ __('Delete ticket') }}
            </button>
        @endif

        <!-- Respond Modal -->
        @if ($showRespondModal)
            <div class="sec-modal-backdrop" wire:keydown.escape="closeRespondModal">
                <div class="sec-modal-box">
                    <div class="sec-modal-header">
                        <h3 class="sec-card-title">
                            {{ $respondingAction === 'approve' ? __('Approve appeal') : __('Reject appeal') }}
                        </h3>
                        <p class="sec-card-subtitle">
                            {{ $respondingAction === 'approve'
                                ? __('The blocked IP/device will be unblocked automatically.')
                                : __('The block will remain active.') }}
                        </p>
                    </div>

                    <form wire:submit="confirmRespond">
                        <div class="sec-modal-body">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">{{ __('Admin notes') }}</label>
                                <textarea
                                    wire:model="adminNotes"
                                    rows="3"
                                    class="sec-textarea"
                                    placeholder="{{ __('Optional notes shown to the applicant…') }}"
                                ></textarea>
                                @error('adminNotes')
                                    <div style="color: var(--sec-danger); font-size: 11px; margin-top: 4px;">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="sec-modal-footer">
                            <button type="button" class="sec-btn sec-btn-secondary" wire:click="closeRespondModal">
                                {{ __('Cancel') }}
                            </button>
                            <button
                                type="submit"
                                class="sec-btn {{ $respondingAction === 'approve' ? 'sec-btn-primary' : 'sec-btn-danger' }}"
                            >
                                {{ $respondingAction === 'approve' ? __('Approve & unblock') : __('Reject') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-pages::pasak.layout>
</div>
