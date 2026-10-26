@extends('layouts.app')
@section('title', 'Pusat Keamanan Kustom — Pasak WAF')

@section('content')
<style>
.sec-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.sec-badges {
    display: flex;
    gap: .5rem;
    align-items: center;
}
.pill {
    font-size: .75rem;
    padding: .25rem .65rem;
    border-radius: 999px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
}
.pill.active { background: #052e16; color: #86efac; border: 1px solid #16a34a; }
.pill.warning { background: #451a03; color: #fde047; border: 1px solid #ca8a04; }
.pill.inactive { background: #450a0a; color: #fca5a5; border: 1px solid #dc2626; }
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.stat-box {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: .75rem;
    padding: 1.1rem;
}
.stat-box .val {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: .25rem;
}
.stat-box .lbl {
    font-size: .85rem;
    color: #9ca3af;
}
.two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 900px) {
    .two-col { grid-template-columns: 1fr; }
}
.badge-level {
    font-size: .7rem;
    font-weight: 700;
    padding: .15rem .5rem;
    border-radius: .375rem;
    text-transform: uppercase;
}
.badge-level.critical { background: #7f1d1d; color: #fecaca; }
.badge-level.high { background: #78350d; color: #fef08a; }
.badge-level.medium { background: #1e3a8a; color: #bfdbfe; }
.badge-level.low { background: #374151; color: #e5e7eb; }
.table-responsive {
    overflow-x: auto;
}
</style>

@include('keamanan._nav')

<div class="sec-header">
    <div>
        <h2 style="margin: 0; font-size: 1.25rem;">📊 Ringkasan Keamanan Sistem</h2>
    </div>
    <div class="sec-badges">
        <span class="pill {{ $wafEnabled ? 'active' : 'inactive' }}">
            ● WAF Engine: {{ $wafEnabled ? 'Aktif' : 'Non-aktif' }}
        </span>
        <span class="pill {{ $enforcementEnabled ? 'active' : 'warning' }}">
            ● Penegakan Blokir: {{ $enforcementEnabled ? 'Aktif (403)' : 'Simulasi (Log Saja)' }}
        </span>
    </div>
</div>

{{-- Kartu Statistik --}}
<div class="stats-grid">
    <div class="stat-box">
        <div class="val" style="color: #60a5fa;">{{ number_format($stats['total_logs'] ?? 0) }}</div>
        <div class="lbl">Total Log Ancaman</div>
    </div>
    <div class="stat-box">
        <div class="val" style="color: #fbbf24;">{{ number_format($stats['logs_today'] ?? 0) }}</div>
        <div class="lbl">Serangan Hari Ini</div>
    </div>
    <div class="stat-box">
        <div class="val" style="color: #f87171;">{{ number_format($stats['critical_today'] ?? 0) }}</div>
        <div class="lbl">Kritis Hari Ini</div>
    </div>
    <div class="stat-box">
        <div class="val" style="color: #ef4444;">{{ number_format($stats['active_blocks'] ?? 0) }}</div>
        <div class="lbl">IP Diblokir Aktif</div>
    </div>
</div>

<div class="two-col">
    {{-- Form Manual Blokir IP --}}
    <div class="card">
        <h3 style="margin-top:0;">🚫 Blokir IP Manual</h3>
        <p style="color:#9ca3af; font-size:.85rem; margin-top:-.25rem;">
            Tambahkan IP attacker ke daftar hitam Pasak secara instan.
        </p>
        <form method="POST" action="{{ route('keamanan.block') }}">
            @csrf
            <div style="margin-bottom: .75rem;">
                <label style="margin-bottom: .25rem;">Alamat IP Target:</label>
                <input type="text" name="ip" placeholder="mis. 192.168.1.50 atau 10.0.0.1" required>
            </div>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: .5rem; margin-bottom: .75rem;">
                <div>
                    <label style="margin-bottom: .25rem;">Alasan Pemblokiran:</label>
                    <input type="text" name="reason" placeholder="mis. Percobaan SQLi berulang">
                </div>
                <div>
                    <label style="margin-bottom: .25rem;">Durasi (Jam):</label>
                    <input type="number" name="hours" placeholder="Permanen (kosong)" min="1">
                </div>
            </div>
            <button type="submit" class="btn danger" style="width: 100%;">+ Blokir Sekarang</button>
        </form>
    </div>

    {{-- Daftar IP yang Sedang Diblokir --}}
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.5rem;">
            <h3 style="margin:0;">📋 IP Diblokir Terbaru ({{ count($recentBlocks) }})</h3>
            <a href="{{ route('keamanan.blocked-ips') }}" class="btn" style="font-size:.75rem; padding:.2rem .5rem;">Lihat Semua →</a>
        </div>
        <p style="color:#9ca3af; font-size:.85rem; margin-top:-.25rem;">
            Daftar IP yang langsung di-block 403 saat mengakses situs.
        </p>
        @if($recentBlocks->isEmpty())
            <p style="color: #9ca3af; font-size: .85rem; font-style: italic;">
                Belum ada IP yang diblokir saat ini.
            </p>
        @else
            <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Alamat IP</th>
                            <th>Alasan</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentBlocks as $item)
                        <tr>
                            <td>
                                <code>{{ $item->ip_address }}</code>
                                @if($item->blocked_until)
                                    <div class="meta" style="font-size: .7rem;">Hingga: {{ $item->blocked_until->diffForHumans() }}</div>
                                @else
                                    <div class="meta" style="font-size: .7rem;">Permanen</div>
                                @endif
                            </td>
                            <td>
                                <span style="font-size: .8rem;">{{ $item->reason ?? 'Manual Block' }}</span>
                                <div class="meta" style="font-size: .7rem;">Hits: {{ $item->hit_count ?? 0 }}</div>
                            </td>
                            <td style="text-align:right;">
                                <form method="POST" action="{{ route('keamanan.unblock', $item->ip_address) }}" onsubmit="return confirm('Buka blokir untuk IP ini?')">
                                    @csrf
                                    <button type="submit" class="btn" style="padding: .2rem .5rem; font-size: .75rem;">Buka</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Tabel Log Ancaman --}}
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem;">
        <div>
            <h3 style="margin: 0;">📜 Riwayat Deteksi WAF Terbaru</h3>
            <span class="meta">Data langsung dari tabel <code>{{ (new \Internal\Pasak\Models\PasakLog)->getTable() }}</code></span>
        </div>
        <div style="display:flex; gap:.5rem; align-items:center;">
            @if($recentLogs->isNotEmpty())
            <form method="POST" action="{{ route('keamanan.logs.clear') }}" onsubmit="return confirm('Apakah Anda yakin ingin mengosongkan semua riwayat log ancaman?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn" style="font-size: .8rem; background: #27272a;">🗑️ Bersihkan Semua Log</button>
            </form>
            @endif
            <a href="{{ route('keamanan.logs') }}" class="btn primary" style="font-size: .8rem;">Semua Log →</a>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Tingkat</th>
                    <th>IP & Metode</th>
                    <th>Path / URL</th>
                    <th>Aturan / Bukti</th>
                    <th>Respon</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentLogs as $log)
                <tr>
                    <td style="white-space: nowrap;">
                        <span style="font-size: .8rem;">{{ $log->created_at?->format('H:i:s') }}</span>
                        <div class="meta" style="font-size: .7rem;">{{ $log->created_at?->diffForHumans() }}</div>
                    </td>
                    <td>
                        <span class="badge-level {{ strtolower($log->threat_level ?? 'low') }}">
                            {{ $log->threat_level }}
                        </span>
                    </td>
                    <td>
                        <code>{{ $log->ip_address }}</code>
                        <div class="meta" style="font-size: .75rem;">{{ $log->method ?? 'GET' }}</div>
                    </td>
                    <td>
                        <span style="word-break: break-all; font-family: monospace; font-size: .8rem;">{{ $log->path }}</span>
                    </td>
                    <td>
                        <b style="font-size: .8rem; color: #fca5a5;">{{ $log->rule_label ?? $log->event_type }}</b>
                        @if(!empty($log->evidence))
                            <div class="meta" style="font-size: .7rem; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ is_string($log->evidence) ? $log->evidence : json_encode($log->evidence) }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($log->was_blocked)
                            <span class="badge" style="background: #7f1d1d; color: #fecaca; border: 1px solid #ef4444;">403 Blocked</span>
                        @else
                            <span class="badge" style="background: #1f2937; color: #9ca3af;">Logged</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #9ca3af; padding: 2rem;">
                        Belum ada aktivitas ancaman yang terdeteksi. Sistem berjalan aman!
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
