@extends('layouts.app')
@section('title', 'Daftar IP Diblokir — Pusat Keamanan Kustom')

@section('content')
@include('keamanan._nav')

<div class="two-col">
    {{-- Form Blokir Manual --}}
    <div class="card">
        <h2 style="margin:0 0 .5rem 0; font-size:1.15rem;">🚫 Blokir IP / Device Manual</h2>
        <p style="margin:0 0 1rem 0; color:#9ca3af; font-size:.85rem;">
            Masukkan alamat IP penyerang untuk memblokir akses secara langsung ke seluruh aplikasi host.
        </p>

        <form method="POST" action="{{ route('keamanan.block') }}">
            @csrf
            <label>
                Alamat IP Sasaran *
                <input type="text" name="ip" placeholder="misal: 198.51.100.44" required>
            </label>

            <label>
                Alasan Pemblokiran
                <input type="text" name="reason" placeholder="misal: Aktivitas mencurigakan, brute-force login...">
            </label>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem;">
                <label>
                    Durasi Blokir (Jam)
                    <input type="number" name="hours" placeholder="Kosong = Permanen" min="1" max="8760">
                </label>
                <label>
                    Lingkup Blokir
                    <select name="scope">
                        <option value="ip">IP Saja (Router / NAT)</option>
                        <option value="device">Perangkat Spesifik (Device Quarantine)</option>
                    </select>
                </label>
            </div>

            <button type="submit" class="btn danger" style="width:100%; font-weight:700; margin-top:.5rem;">
                Terapkan Pemblokiran Sekarang
            </button>
        </form>
    </div>

    {{-- Info Card / Penjelasan --}}
    <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <h2 style="margin:0 0 .5rem 0; font-size:1.15rem;">🛡️ Mekanisme Karantina (Device Quarantine)</h2>
            <p style="color:#9ca3af; font-size:.85rem; line-height:1.6;">
                Pasak mendukung mitigasi presisi tinggi:
            </p>
            <ul style="color:#9ca3af; font-size:.85rem; padding-left:1.25rem; line-height:1.7;">
                <li><b>IP Router (NAT):</b> Menutup akses untuk seluruh perangkat yang berada di balik router/IP publik yang sama.</li>
                <li><b>Device Quarantine:</b> Mengisolasi penyerang spesifik melalui sidik jari <code>device_id</code> tanpa mengganggu pengguna sah lain yang berbagi WiFi atau kantor yang sama.</li>
                <li><b>Tindakan Cepat:</b> Anda dapat mengaktifkan atau menonaktifkan status blokir seketika tanpa menghapus rekaman audit.</li>
            </ul>
        </div>
        <div style="background:#0d1420; border:1px solid #1f2937; border-radius:.5rem; padding:.75rem; font-size:.8rem; color:#9ca3af;">
            💡 <i>Setiap pemblokiran dicatat dengan audit trail lengkap termasuk identitas admin yang mengeksekusi.</i>
        </div>
    </div>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
        <h2 style="margin:0; font-size:1.15rem;">📋 Daftar IP / Perangkat yang Terisolasi</h2>

        <form method="GET" action="{{ route('keamanan.blocked-ips') }}" style="display:flex; gap:.5rem; margin:0;">
            <input type="text" name="search" value="{{ $filterSearch }}" placeholder="Cari IP atau alasan..." style="width:200px;">
            <button type="submit" class="btn primary" style="font-size:.85rem;">Cari</button>
            @if($filterSearch)
                <a href="{{ route('keamanan.blocked-ips') }}" class="btn" style="font-size:.85rem;">Reset</a>
            @endif
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th style="width:160px;">Alamat IP</th>
                    <th style="width:90px;">Lingkup</th>
                    <th>Alasan Pemblokiran</th>
                    <th style="width:120px;">Sumber</th>
                    <th style="width:150px;">Berlaku Sampai</th>
                    <th style="width:90px;">Status</th>
                    <th style="width:160px; text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($blockedIps as $item)
                <tr>
                    <td><code>#{{ $item->id }}</code></td>
                    <td>
                        <b style="color:#f87171;">{{ $item->ip_address }}</b>
                        @if($item->device_id)
                            <div style="font-size:.7rem; color:#9ca3af;">Dev: <code>{{ \Illuminate\Support\Str::limit($item->device_id, 10) }}</code></div>
                        @endif
                    </td>
                    <td>
                        <span class="badge">{{ strtoupper($item->block_scope ?? 'IP') }}</span>
                    </td>
                    <td style="color:#d1d5db;">
                        {{ $item->reason ?? 'Tidak ada keterangan' }}
                    </td>
                    <td style="color:#9ca3af; font-size:.8rem;">
                        <span class="badge">{{ $item->source ?? 'manual' }}</span>
                    </td>
                    <td style="font-size:.8rem; color:#9ca3af;">
                        @if($item->blocked_until)
                            {{ $item->blocked_until->format('d M Y H:i') }}
                        @else
                            <span style="color:#f87171; font-weight:700;">Permanen</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge-status {{ $item->is_active ? 'active' : 'inactive' }}">
                            {{ $item->is_active ? 'AKTIF' : 'NONAKTIF' }}
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:.25rem;">
                            {{-- Toggle Active --}}
                            <form method="POST" action="{{ route('keamanan.blocked-ips.toggle', $item->id) }}" style="margin:0;">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn" style="padding:.2rem .5rem; font-size:.75rem;" title="{{ $item->is_active ? 'Nonaktifkan blokir' : 'Aktifkan blokir' }}">
                                    {{ $item->is_active ? '⏸️' : '▶️' }}
                                </button>
                            </form>

                            {{-- Unblock / Delete --}}
                            <form method="POST" action="{{ route('keamanan.blocked-ips.destroy', $item->id) }}" onsubmit="return confirm('Buka blokir dan hapus rekaman untuk IP {{ $item->ip_address }}?');" style="margin:0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn danger" style="padding:.2rem .5rem; font-size:.75rem;" title="Hapus Blokir">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; padding:2rem; color:#9ca3af;">
                        Tidak ada alamat IP yang sedang diblokir.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1.5rem;">
        {{ $blockedIps->links() }}
    </div>
</div>
@endsection
