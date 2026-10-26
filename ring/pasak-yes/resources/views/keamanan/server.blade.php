@extends('layouts.app')
@section('title', 'Audit Server & Webshell — Pusat Keamanan Kustom')

@section('content')
@include('keamanan._nav')

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
    <div>
        <h2 style="margin:0; font-size:1.25rem;">🖥️ Audit Keamanan Server & Integritas Berkas</h2>
        <p style="margin:0; color:#9ca3af; font-size:.85rem;">
            Pemeriksaan mendalam konfigurasi PHP, database, izin berkas, integritas SHA-256, dan webshell scanner.
        </p>
    </div>
    <div>
        <a href="{{ route('keamanan.server', ['refresh' => 1]) }}" class="btn primary" style="font-size:.85rem;">
            🔄 Pindai Ulang Sekarang
        </a>
    </div>
</div>

{{-- Skor & Metrik Ringkasan --}}
<div class="stats-grid">
    <div class="stat-box">
        <div class="val" style="color: {{ ($report['summary']['score'] ?? 0) >= 80 ? '#4ade80' : (($report['summary']['score'] ?? 0) >= 60 ? '#fbbf24' : '#f87171') }};">
            {{ $report['summary']['score'] ?? 0 }}<span style="font-size:1rem; color:#9ca3af;">/100</span>
        </div>
        <div class="lbl">Skor Kesehatan Server</div>
    </div>
    <div class="stat-box">
        <div class="val" style="color: #4ade80;">{{ $report['summary']['ok'] ?? 0 }}</div>
        <div class="lbl">Pemeriksaan Lolos (OK)</div>
    </div>
    <div class="stat-box">
        <div class="val" style="color: #fbbf24;">{{ $report['summary']['warning'] ?? 0 }}</div>
        <div class="lbl">Peringatan (Warning)</div>
    </div>
    <div class="stat-box">
        <div class="val" style="color: #f87171;">{{ $report['summary']['critical'] ?? 0 }}</div>
        <div class="lbl">Masalah Kritis (Critical)</div>
    </div>
</div>

<div class="two-col">
    {{-- Card Baseline Integritas SHA-256 --}}
    <div class="card">
        <h3 style="margin-top:0; font-size:1.1rem;">🛡️ Baseline Integritas Berkas (SHA-256)</h3>
        <p style="color:#9ca3af; font-size:.85rem;">
            Merekam sidik jari kriptografis seluruh berkas PHP kritis aplikasi. Perubahan atau injeksi kode tak sah akan langsung terdeteksi.
        </p>

        <div style="background:#0d1420; border:1px solid #1f2937; border-radius:.5rem; padding:.75rem; margin-bottom:1rem; font-size:.85rem;">
            <div>Status: <b>{{ $baseline['exists'] ? '✅ Aktif' : '⚠️ Belum Dibuat' }}</b></div>
            <div>Berkas Terpantau: <b>{{ $baseline['files'] ?? 0 }} / {{ $baseline['watched'] ?? 0 }} berkas</b></div>
            @if($baseline['created_at'])
                <div style="color:#9ca3af; font-size:.75rem; margin-top:.25rem;">
                    Dibuat pada: {{ \Carbon\Carbon::parse($baseline['created_at'])->format('d M Y H:i:s') }}
                </div>
            @endif
        </div>

        <div style="display:flex; gap:.5rem;">
            <form method="POST" action="{{ route('keamanan.server.baseline') }}" style="margin:0; flex:1;">
                @csrf
                <button type="submit" class="btn primary" style="width:100%; font-size:.85rem;">
                    {{ $baseline['exists'] ? '🔄 Perbarui Baseline' : '+ Buat Baseline Baru' }}
                </button>
            </form>

            @if($baseline['exists'])
            <form method="POST" action="{{ route('keamanan.server.baseline.destroy') }}" onsubmit="return confirm('Hapus baseline integritas berkas saat ini?');" style="margin:0;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn danger" style="font-size:.85rem;">
                    Hapus
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- Card Sanitasi Berkas Berbahaya --}}
    <div class="card">
        <h3 style="margin-top:0; font-size:1.1rem;">🧹 Musnahkan Berkas Mencurigakan</h3>
        <p style="color:#9ca3af; font-size:.85rem;">
            Pembersihan defensif berkas webshell atau malware yang terisolasi. Dilindungi sistem pengaman traversal & berkas vital.
        </p>

        <form method="POST" action="{{ route('keamanan.server.suspicious-files.destroy') }}" onsubmit="return confirm('PERINGATAN: Berkas yang dihapus tidak dapat dipulihkan. Lanjutkan penghapusan berkas ini?');">
            @csrf
            @method('DELETE')
            <label>
                Path Relatif Berkas (di dalam direktori proyek) *
                <input type="text" name="path" placeholder="misal: storage/app/public/shell.php" required>
            </label>

            <button type="submit" class="btn danger" style="width:100%; font-weight:700; margin-top:.5rem; font-size:.85rem;">
                Musnahkan Berkas Permanen
            </button>
        </form>

        <div style="font-size:.75rem; color:#9ca3af; margin-top:.75rem;">
            🛡️ <i>Berkas sistem vital seperti <code>public/index.php</code>, <code>.env</code>, dan <code>composer.json</code> dilindungi otomatis dan tidak dapat dihapus.</i>
        </div>
    </div>
</div>

{{-- Akun Terkunci / Stepped Throttling --}}
@if($lockouts->count() > 0)
<div class="card" style="border-color:#7f1d1d;">
    <h3 style="margin-top:0; color:#f87171; font-size:1.1rem;">🔒 Akun Pengguna yang Sedang Terkunci (Stepped Lockout)</h3>
    <p style="color:#9ca3af; font-size:.85rem;">
        Akun-akun berikut mengalami percobaan login gagal berulang kali dan dikunci sementara untuk mencegah serangan brute-force.
    </p>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Email / Identifier</th>
                    <th>Alamat IP</th>
                    <th>Jumlah Percobaan</th>
                    <th>Terkunci Sampai</th>
                    <th style="text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lockouts as $lockout)
                <tr>
                    <td><b>{{ $lockout->email }}</b></td>
                    <td><code>{{ $lockout->ip_address }}</code></td>
                    <td><span class="badge">{{ $lockout->attempts }}x gagal</span></td>
                    <td style="color:#f87171;">
                        {{ $lockout->locked_until?->format('d M Y H:i:s') ?? 'Terkunci' }}
                    </td>
                    <td style="text-align:right;">
                        <form method="POST" action="{{ route('keamanan.lockouts.destroy', $lockout->id) }}" style="margin:0;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn primary" style="font-size:.75rem; padding:.2rem .6rem;">
                                🔓 Buka Kunci
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Rincian Hasil Pemeriksaan Per Kategori --}}
<div class="card">
    <h3 style="margin-top:0; font-size:1.1rem; margin-bottom:1rem;">📋 Rincian Pemeriksaan Audit Sistem</h3>

    @foreach($report['categories'] ?? [] as $category)
    <div style="margin-bottom:1.5rem; background:#0d1420; border:1px solid #1f2937; border-radius:.5rem; overflow:hidden;">
        <div style="padding:.75rem 1rem; background:#111827; border-bottom:1px solid #1f2937; display:flex; justify-content:space-between; align-items:center;">
            <b style="font-size:.95rem;">{{ $category['title'] ?? 'Kategori' }}</b>
            <span class="badge">{{ count($category['checks'] ?? []) }} indikator</span>
        </div>
        <div style="padding:.5rem 1rem;">
            @foreach($category['checks'] ?? [] as $check)
            <div style="padding:.6rem 0; border-bottom:1px solid #1a2332; display:flex; justify-content:space-between; align-items:flex-start; gap:1rem;">
                <div style="flex:1;">
                    <div style="display:flex; align-items:center; gap:.5rem;">
                        <span class="badge-status {{ $check['status'] ?? 'info' }}">
                            {{ strtoupper($check['status'] ?? 'INFO') }}
                        </span>
                        <b style="font-size:.9rem; color:#f3f4f6;">{{ $check['label'] ?? '' }}</b>
                    </div>
                    <div style="font-size:.8rem; color:#9ca3af; margin-top:.25rem;">
                        {{ $check['summary'] ?? ($check['description'] ?? '') }}
                    </div>
                    @if(!empty($check['recommendation']) && ($check['status'] ?? '') !== 'ok')
                        <div style="font-size:.75rem; color:#fbbf24; margin-top:.25rem;">
                            💡 Rekomendasi: {{ $check['recommendation'] }}
                        </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
@endsection
