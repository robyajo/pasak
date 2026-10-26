@extends('layouts.app')
@section('title', 'Sesi Login & IP Terpercaya — Pusat Keamanan Kustom')

@section('content')
@include('keamanan._nav')

<div class="two-col">
    {{-- IP Terpercaya --}}
    <div class="card">
        <h2 style="margin:0 0 .5rem 0; font-size:1.15rem;">⭐ Alamat IP Terpercaya (Trusted IPs)</h2>
        <p style="margin:0 0 1rem 0; color:#9ca3af; font-size:.85rem;">
            IP terpercaya akan dilewatkan dari throttling login dan diverifikasi sebagai perangkat admin resmi.
        </p>

        <form method="POST" action="{{ route('keamanan.trusted-ips.store') }}" style="margin-bottom:1.5rem;">
            @csrf
            <button type="submit" class="btn primary" style="width:100%; font-size:.85rem;">
                📍 Daftarkan IP Saya Saat Ini ({{ request()->ip() }})
            </button>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Alamat IP</th>
                        <th>Perangkat</th>
                        <th>Waktu</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trustedIps as $trusted)
                    <tr>
                        <td><b style="color:#86efac;">{{ $trusted->ip_address }}</b></td>
                        <td style="color:#9ca3af; font-size:.8rem;">{{ \Illuminate\Support\Str::limit($trusted->device_name ?? 'Perangkat Admin', 25) }}</td>
                        <td style="color:#9ca3af; font-size:.75rem;">{{ $trusted->created_at?->format('d M H:i') ?? '-' }}</td>
                        <td style="text-align:right;">
                            <form method="POST" action="{{ route('keamanan.trusted-ips.destroy', $trusted->id) }}" onsubmit="return confirm('Hapus IP terpercaya ini?');" style="margin:0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn danger" style="padding:.2rem .5rem; font-size:.75rem;">✕</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center; color:#9ca3af; padding:1rem; font-size:.85rem;">
                            Belum ada IP terpercaya yang didaftarkan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Penjelasan Heartbeat & Monitoring Sesi --}}
    <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <h2 style="margin:0 0 .5rem 0; font-size:1.15rem;">👥 Pemantauan Sesi Aktif & Heartbeat</h2>
            <p style="color:#9ca3af; font-size:.85rem; line-height:1.6;">
                Pasak secara otomatis melacak aktivitas masuk pengguna aplikasi:
            </p>
            <ul style="color:#9ca3af; font-size:.85rem; padding-left:1.25rem; line-height:1.7;">
                <li><b>Heartbeat Realtime:</b> Memperbarui <code>last_activity_at</code> secara periodik tanpa membebani database (dithrottle 45 detik).</li>
                <li><b>Deteksi Perangkat:</b> Mengidentifikasi OS, Browser, tipe perangkat (Desktop/Mobile), serta sidik jari IP.</li>
                <li><b>Pemutusan Paksa:</b> Admin dapat mencabut sesi login yang mencurigakan secara instan.</li>
            </ul>
        </div>
        <div style="background:#0d1420; border:1px solid #1f2937; border-radius:.5rem; padding:.75rem; font-size:.8rem; color:#9ca3af;">
            💡 <i>Sesi yang telah logout ditandai dengan waktu <code>logout_at</code> sehingga jejak forensik tetap terjaga.</i>
        </div>
    </div>
</div>

{{-- Riwayat Sesi Pengguna --}}
<div class="card">
    <div style="margin-bottom:1rem;">
        <h2 style="margin:0; font-size:1.15rem;">📋 Daftar Sesi Pengguna</h2>
        <p style="margin:0; color:#9ca3af; font-size:.85rem;">
            Daftar sesi login pengguna aktif dan riwayat sesi sebelumnya.
        </p>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th style="width:100px;">User ID</th>
                    <th style="width:160px;">Alamat IP</th>
                    <th>Perangkat & Browser</th>
                    <th style="width:140px;">Waktu Masuk</th>
                    <th style="width:140px;">Aktivitas Terakhir</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:100px; text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions as $sess)
                <tr>
                    <td><code>#{{ $sess->id }}</code></td>
                    <td><b>User #{{ $sess->user_id }}</b></td>
                    <td>
                        <code>{{ $sess->ip_address }}</code>
                        @if($sess->device_id)
                            <div style="font-size:.7rem; color:#9ca3af;">Dev: <code>{{ \Illuminate\Support\Str::limit($sess->device_id, 10) }}</code></div>
                        @endif
                    </td>
                    <td>
                        <b>{{ $sess->browser ?? 'Browser' }}</b> on {{ $sess->operating_system ?? 'OS' }}
                        <div style="font-size:.75rem; color:#9ca3af;">
                            {{ $sess->device_type ?? 'desktop' }}
                        </div>
                    </td>
                    <td style="font-size:.8rem; color:#9ca3af;">
                        {{ $sess->login_at?->format('d M H:i:s') ?? '-' }}
                    </td>
                    <td style="font-size:.8rem; color:#9ca3af;">
                        {{ $sess->last_activity_at?->diffForHumans() ?? '-' }}
                    </td>
                    <td>
                        @if($sess->logout_at)
                            <span class="badge-status inactive">LOGOUT</span>
                        @else
                            <span class="badge-status active">ONLINE</span>
                        @endif
                    </td>
                    <td style="text-align:right;">
                        @if(! $sess->logout_at)
                        <form method="POST" action="{{ route('keamanan.sessions.destroy', $sess->id) }}" onsubmit="return confirm('Putus paksa sesi ini?');" style="margin:0;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn danger" style="padding:.2rem .5rem; font-size:.75rem;">
                                Putus
                            </button>
                        </form>
                        @else
                        <span style="color:#6b7280; font-size:.8rem;">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; padding:2rem; color:#9ca3af;">
                        Belum ada riwayat sesi login.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1.5rem;">
        {{ $sessions->links() }}
    </div>
</div>
@endsection
