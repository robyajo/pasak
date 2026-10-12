<?php

use Internal\Pasak\Models\AllowedIp;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Allowed IPs')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public int $perPage = 25;

    public bool $showAllowModal = false;

    public string $ipAddress = '';

    public string $label = '';

    public string $notes = '';

    public int $durationHours = 0;

    public bool $permanent = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function openAllowModal(): void
    {
        $this->reset(['ipAddress', 'label', 'notes']);
        $this->durationHours = 0;
        $this->permanent = true;
        $this->resetErrorBag();
        $this->showAllowModal = true;
    }

    public function closeAllowModal(): void
    {
        $this->showAllowModal = false;
        $this->reset(['ipAddress', 'label', 'notes']);
    }

    public function allow(): void
    {
        $this->validate([
            'ipAddress' => ['required', 'string', 'max:45'],
            'label' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'durationHours' => ['required', 'integer', 'min:0'],
            'permanent' => ['boolean'],
        ]);

        $pattern = trim($this->ipAddress);

        if (! AllowedIp::isValidPattern($pattern)) {
            $this->addError('ipAddress', __('Gunakan IP tunggal, wildcard (192.168.1.*), atau CIDR (10.0.0.0/8).'));

            return;
        }

        $isPermanent = $this->permanent || $this->durationHours === 0;

        AllowedIp::query()->updateOrCreate(
            ['ip_address' => $pattern],
            [
                'label' => $this->label !== '' ? $this->label : null,
                'notes' => $this->notes !== '' ? $this->notes : null,
                'allowed_by' => auth()->id(),
                'is_active' => true,
                'expires_at' => $isPermanent ? null : now()->addHours($this->durationHours),
            ],
        );

        $this->showAllowModal = false;
        $this->reset(['ipAddress', 'label', 'notes']);

        session()->flash('security_message', __('IP :ip berhasil diizinkan.', ['ip' => $pattern]));
    }

    public function toggle(int $id): void
    {
        $allowed = AllowedIp::query()->find($id);

        if (! $allowed) {
            return;
        }

        $allowed->is_active = ! $allowed->is_active;
        $allowed->save();

        session()->flash(
            'security_message',
            $allowed->is_active
                ? __('Izin IP :ip diaktifkan.', ['ip' => $allowed->ip_address])
                : __('Izin IP :ip dinonaktifkan.', ['ip' => $allowed->ip_address]),
        );
    }

    public function remove(int $id): void
    {
        $allowed = AllowedIp::query()->find($id);

        if (! $allowed) {
            return;
        }

        $ip = $allowed->ip_address;
        $allowed->delete();

        session()->flash('security_message', __('IP :ip dihapus dari daftar izin.', ['ip' => $ip]));
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $query = AllowedIp::query()->latest('id');

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($this->status === 'active') {
            $query->active();
        } elseif ($this->status === 'expired') {
            $query->expired();
        } elseif ($this->status === 'permanent') {
            $query->whereNull('expires_at')->where('is_active', true);
        }

        return [
            'entries' => $query->paginate($this->perPage),
            'stats' => [
                'total' => AllowedIp::query()->count(),
                'active' => AllowedIp::query()->active()->count(),
                'permanent' => AllowedIp::query()->whereNull('expires_at')->where('is_active', true)->count(),
            ],
        ];
    }
}; ?>

<div>
    <x-pages::pasak.layout :heading="__('Allowed IPs')" :subheading="__('IP yang selalu diizinkan — tidak pernah dicatat maupun diblokir')">
        <!-- Stat Grid -->
        <div class="sec-grid-4">
            <div class="sec-stat-card">
                <div class="sec-stat-label">{{ __('Total entries') }}</div>
                <div class="sec-stat-value">{{ number_format($stats['total']) }}</div>
            </div>
            <div class="sec-stat-card">
                <div class="sec-stat-label">{{ __('Active') }}</div>
                <div class="sec-stat-value" style="color: var(--sec-success);">{{ number_format($stats['active']) }}</div>
            </div>
            <div class="sec-stat-card">
                <div class="sec-stat-label">{{ __('Permanent') }}</div>
                <div class="sec-stat-value">{{ number_format($stats['permanent']) }}</div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="sec-toolbar">
            <div class="sec-toolbar-group">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search IP, label…') }}"
                    class="sec-input"
                    style="min-width: 240px;"
                />

                <select wire:model.live="status" class="sec-select">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="expired">{{ __('Expired') }}</option>
                    <option value="permanent">{{ __('Permanent') }}</option>
                </select>
            </div>

            <button type="button" class="sec-btn sec-btn-primary" wire:click="openAllowModal">
                <span>➕</span>
                <span>{{ __('Allow IP') }}</span>
            </button>
        </div>

        <!-- Table Card -->
        <div class="sec-card sec-card-flush">
            <div class="sec-table-wrap">
                <table class="sec-table">
                    <thead>
                        <tr>
                            <th>{{ __('IP / range') }}</th>
                            <th>{{ __('Label') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Expires') }}</th>
                            <th style="text-align: right;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td>
                                    <div class="sec-font-mono" style="font-weight: 600; font-size: 13px;">{{ $entry->ip_address }}</div>
                                </td>
                                <td style="max-width: 250px;" title="{{ $entry->label }}">
                                    <span style="font-size: 12px;">{{ $entry->label ?? '—' }}</span>
                                </td>
                                <td>
                                    @if ($entry->isEffective())
                                        <span class="sec-badge sec-badge-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="sec-badge sec-badge-low">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td style="font-size: 12px; color: var(--sec-text-muted); white-space: nowrap;">
                                    {{ $entry->isPermanent() ? __('Never') : $entry->expires_at }}
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button
                                        type="button"
                                        class="sec-btn sec-btn-ghost sec-btn-sm"
                                        wire:click="toggle({{ $entry->id }})"
                                        title="{{ $entry->is_active ? __('Disable') : __('Enable') }}"
                                    >
                                        {{ $entry->is_active ? '⏸️' : '▶️' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="sec-btn sec-btn-ghost sec-btn-sm"
                                        wire:click="remove({{ $entry->id }})"
                                        wire:confirm="{{ __('Remove this allow entry?') }}"
                                        title="{{ __('Remove') }}"
                                    >
                                        🗑️
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--sec-text-muted); padding: 36px;">
                                    {{ __('No allowed IPs yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($entries->hasPages())
                <div style="padding: 16px 20px; border-top: 1px solid var(--sec-border-light);">
                    {{ $entries->links() }}
                </div>
            @endif
        </div>

        <!-- Allow IP Modal -->
        @if ($showAllowModal)
            <div class="sec-modal-backdrop" wire:keydown.escape="closeAllowModal">
                <div class="sec-modal-box">
                    <div class="sec-modal-header">
                        <h3 class="sec-card-title">{{ __('Allow an IP address') }}</h3>
                        <p class="sec-card-subtitle">{{ __('IP, wildcard, atau CIDR yang diizinkan tidak akan pernah diblokir.') }}</p>
                    </div>

                    <form wire:submit="allow">
                        <div class="sec-modal-body">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">{{ __('IP / wildcard / CIDR') }} *</label>
                                <input
                                    type="text"
                                    wire:model="ipAddress"
                                    placeholder="203.0.113.10 / 192.168.1.* / 10.0.0.0/8"
                                    class="sec-input"
                                    style="width: 100%;"
                                    required
                                />
                                @error('ipAddress')
                                    <div style="color: var(--sec-danger); font-size: 11px; margin-top: 4px;">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">{{ __('Label') }}</label>
                                <input
                                    type="text"
                                    wire:model="label"
                                    placeholder="{{ __('e.g. Office network') }}"
                                    class="sec-input"
                                    style="width: 100%;"
                                />
                            </div>

                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">{{ __('Internal notes') }}</label>
                                <textarea
                                    wire:model="notes"
                                    rows="2"
                                    class="sec-textarea"
                                    placeholder="{{ __('Optional internal audit notes…') }}"
                                ></textarea>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: center;">
                                <div>
                                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">{{ __('Duration (hours)') }}</label>
                                    <input
                                        type="number"
                                        min="0"
                                        wire:model="durationHours"
                                        class="sec-input"
                                        style="width: 100%;"
                                    />
                                    <span style="font-size: 11px; color: var(--sec-text-muted);">0 = permanent</span>
                                </div>

                                <div style="padding-top: 18px;">
                                    <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                                        <input type="checkbox" wire:model="permanent" />
                                        <span>{{ __('Permanent') }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="sec-modal-footer">
                            <button type="button" class="sec-btn sec-btn-secondary" wire:click="closeAllowModal">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="sec-btn sec-btn-primary">
                                {{ __('Allow') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-pages::pasak.layout>
</div>
