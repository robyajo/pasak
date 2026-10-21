@extends('pages.pasak.layout', ['heading' => __('Settings'), 'subheading' => __('Ambang batas, lingkup isolasi, dan penegakan blokir')])

@section('content')
@php($s = $settings ?? [])

<div class="sec-card">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
        <div>
            <h3 class="sec-card-title">{{ __('Package version') }}</h3>
            <p class="sec-card-desc" style="margin: 0;">{{ __('Installed :current · Latest :latest', ['current' => ($version['current'] ?? '-'), 'latest' => ($version['latest'] ?? '-')]) }}</p>
        </div>
        @if (! empty($version['update_available']))
            <span class="sec-badge sec-badge-high">{{ __('Update available') }}</span>
        @else
            <span class="sec-badge sec-badge-success">{{ __('Up to date') }}</span>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('pasak.dashboard.settings.update') }}">
    @csrf
    <div class="sec-card">
        <h3 class="sec-card-title">{{ __('Auto-block scope') }}</h3>
        <p class="sec-card-desc">{{ __('Pilih apakah pemblokiran otomatis saat mencapai ambang batas hanya mengisolasi perangkat penyerang atau seluruh IP publik router.') }}</p>
        <div class="sec-scope-grid">
            <label class="sec-scope-box {{ ($s['auto_block_scope'] ?? 'device') === 'device' ? 'active-primary' : '' }}">
                <input type="radio" name="auto_block_scope" value="device" @checked(($s['auto_block_scope'] ?? 'device') === 'device') style="display: none;">
                <div class="sec-scope-header">
                    <span class="sec-scope-title">📱 {{ __('Isolasi Perangkat Saja') }}</span>
                </div>
                <p class="sec-scope-desc">{{ __('Aman untuk WiFi/NAT bersama. Hanya perangkat penyerang yang dikarantina.') }}</p>
            </label>
            <label class="sec-scope-box {{ ($s['auto_block_scope'] ?? 'device') === 'ip' ? 'active-warning' : '' }}">
                <input type="radio" name="auto_block_scope" value="ip" @checked(($s['auto_block_scope'] ?? 'device') === 'ip') style="display: none;">
                <div class="sec-scope-header">
                    <span class="sec-scope-title">🌐 {{ __('Seluruh IP Router Publik') }}</span>
                </div>
                <p class="sec-scope-desc">{{ __('Seluruh perangkat di balik IP publik ikut terblokir.') }}</p>
            </label>
        </div>
    </div>

    <div class="sec-card">
        <h3 class="sec-card-title">{{ __('Instant block scope') }}</h3>
        <p class="sec-card-desc">{{ __('Lingkup isolasi untuk serangan berbahaya yang memicu blokir instan pada percobaan pertama.') }}</p>
        <div class="sec-scope-grid">
            <label class="sec-scope-box {{ ($s['instant_block_scope'] ?? 'device') === 'device' ? 'active-primary' : '' }}">
                <input type="radio" name="instant_block_scope" value="device" @checked(($s['instant_block_scope'] ?? 'device') === 'device') style="display: none;">
                <div class="sec-scope-header">
                    <span class="sec-scope-title">📱 {{ __('Isolasi Perangkat Saja') }}</span>
                </div>
                <p class="sec-scope-desc">{{ __('Direkomendasikan untuk IP publik bersama.') }}</p>
            </label>
            <label class="sec-scope-box {{ ($s['instant_block_scope'] ?? 'device') === 'ip' ? 'active-warning' : '' }}">
                <input type="radio" name="instant_block_scope" value="ip" @checked(($s['instant_block_scope'] ?? 'device') === 'ip') style="display: none;">
                <div class="sec-scope-header">
                    <span class="sec-scope-title">🌐 {{ __('Seluruh IP Router Publik') }}</span>
                </div>
                <p class="sec-scope-desc">{{ __('Blokir total satu alamat IP publik.') }}</p>
            </label>
        </div>
    </div>

    <div class="sec-card">
        <h3 class="sec-card-title">{{ __('Thresholds') }}</h3>
        <p class="sec-card-desc">{{ __('Aturan ambang batas jumlah pelanggaran sebelum tindakan karantina dijalankan.') }}</p>
        <div class="sec-card-body" style="display: flex; flex-direction: column; gap: 18px;">
            <div>
                <span class="sec-label">{{ __('Threat levels counted toward auto-block') }}</span>
                <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                    @foreach (['low' => __('Low'), 'medium' => __('Medium'), 'high' => __('High'), 'critical' => __('Critical')] as $lvl => $label)
                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600;">
                            <input type="checkbox" name="auto_block_levels[]" value="{{ $lvl }}" @checked(in_array($lvl, $s['auto_block_levels'] ?? ['high', 'critical'], true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="sec-label" for="auto_block_enabled">
                    <input id="auto_block_enabled" type="checkbox" name="auto_block_enabled" value="1" @checked(! empty($s['auto_block_enabled']))>
                    {{ __('Enable auto-block') }}
                </label>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <div>
                    <label class="sec-label" for="auto_block_threshold">{{ __('Threshold (events)') }}</label>
                    <input id="auto_block_threshold" type="number" name="auto_block_threshold" min="1" max="100" value="{{ $s['auto_block_threshold'] ?? 3 }}" class="sec-input" style="width: 100%;">
                </div>
                <div>
                    <label class="sec-label" for="auto_block_window">{{ __('Window (minutes)') }}</label>
                    <input id="auto_block_window" type="number" name="auto_block_window" min="1" max="1440" value="{{ $s['auto_block_window'] ?? 10 }}" class="sec-input" style="width: 100%;">
                </div>
                <div>
                    <label class="sec-label" for="auto_block_duration">{{ __('Duration (hours, 0 = permanent)') }}</label>
                    <input id="auto_block_duration" type="number" name="auto_block_duration" min="0" max="8760" value="{{ $s['auto_block_duration'] ?? 24 }}" class="sec-input" style="width: 100%;">
                </div>
            </div>
            <div>
                <label class="sec-label" for="instant_block_enabled">
                    <input id="instant_block_enabled" type="checkbox" name="instant_block_enabled" value="1" @checked(! empty($s['instant_block_enabled']))>
                    {{ __('Enable instant block (zero tolerance)') }}
                </label>
            </div>
            <div>
                <label class="sec-label" for="instant_block_duration">{{ __('Instant block duration (hours, 0 = permanent)') }}</label>
                <input id="instant_block_duration" type="number" name="instant_block_duration" min="0" max="8760" value="{{ $s['instant_block_duration'] ?? 720 }}" class="sec-input" style="width: 100%; max-width: 280px;">
            </div>
            <div>
                <label class="sec-label" for="block_enforcement">
                    <input id="block_enforcement" type="checkbox" name="block_enforcement" value="1" @checked(! empty($s['block_enforcement']))>
                    {{ __('Enforce blocks (return HTTP 403)') }}
                </label>
            </div>
        </div>
    </div>

    <div style="position: sticky; bottom: 0; padding: 12px 0;">
        <button type="submit" class="sec-btn sec-btn-primary">💾 {{ __('Save settings') }}</button>
    </div>
</form>
@endsection
