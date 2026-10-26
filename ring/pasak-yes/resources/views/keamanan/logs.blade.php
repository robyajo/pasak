@extends('layouts.app')
@section('title', 'Log Ancaman — Pusat Keamanan Kustom')

@section('content')
@include('keamanan._nav')

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
        <div>
            <h2 style="margin:0; font-size:1.25rem;">📜 Riwayat Log Ancaman WAF</h2>
            <p style="margin:0; color:#9ca3af; font-size:.85rem;">
                Setiap permintaan berbahaya yang diinspeksi oleh mesin deteksi Pasak tersimpan di sini secara realtime.
            </p>
        </div>
        @if($logs->total() > 0)
        <form method="POST" action="{{ route('keamanan.logs.clear') }}" onsubmit="return confirm('Apakah Anda yakin ingin mengosongkan SELURUH riwayat log ancaman?');" style="margin:0;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn danger" style="font-size:.8rem;">
                🗑️ Bersihkan Semua Log
            </button>
        </form>
        @endif
    </div>

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('keamanan.logs') }}" class="filter-bar">
        <input type="text" name="search" value="{{ $filterSearch }}" placeholder="Cari IP, path, atau nama aturan..." style="flex:1; min-width:220px;">
        <select name="level" style="width:auto; min-width:160px;">
            <option value="">-- Semua Level Ancaman --</option>
            <option value="critical" {{ $filterLevel === 'critical' ? 'selected' : '' }}>Critical (Kritis)</option>
            <option value="high" {{ $filterLevel === 'high' ? 'selected' : '' }}>High (Tinggi)</option>
            <option value="medium" {{ $filterLevel === 'medium' ? 'selected' : '' }}>Medium (Sedang)</option>
            <option value="low" {{ $filterLevel === 'low' ? 'selected' : '' }}>Low (Rendah)</option>
        </select>
        <button type="submit" class="btn primary" style="font-size:.85rem;">Filter</button>
        @if($filterSearch || $filterLevel)
            <a href="{{ route('keamanan.logs') }}" class="btn" style="font-size:.85rem;">Reset</a>
        @endif
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th style="width:140px;">Waktu</th>
                    <th style="width:160px;">Alamat IP</th>
                    <th style="width:90px;">Tingkat</th>
                    <th>Aturan / Pola</th>
                    <th>Metode & Path</th>
                    <th>Cuplikan Muatan</th>
                    <th style="width:80px; text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td><code>#{{ $log->id }}</code></td>
                    <td style="color:#9ca3af; font-size:.8rem;">
                        {{ $log->created_at?->format('d M H:i:s') ?? '-' }}
                    </td>
                    <td>
                        <b>{{ $log->ip_address }}</b>
                        @if($log->device_id)
                            <div style="font-size:.7rem; color:#9ca3af;">Dev: <code>{{ \Illuminate\Support\Str::limit($log->device_id, 12) }}</code></div>
                        @endif
                    </td>
                    <td>
                        <span class="badge-level {{ strtolower($log->threat_level ?? 'low') }}">
                            {{ $log->threat_level ?? 'LOW' }}
                        </span>
                    </td>
                    <td>
                        <b style="color:#f3f4f6;">{{ $log->rule_label ?? '-' }}</b>
                        @if($log->pattern_matched)
                            <div style="font-size:.75rem; color:#9ca3af;">Pola: <code>{{ \Illuminate\Support\Str::limit($log->pattern_matched, 30) }}</code></div>
                        @endif
                    </td>
                    <td>
                        <span class="badge">{{ $log->method ?? 'GET' }}</span>
                        <code style="word-break:break-all;">{{ \Illuminate\Support\Str::limit($log->path, 40) }}</code>
                    </td>
                    <td style="font-size:.75rem; color:#9ca3af; max-width:240px; word-break:break-all;">
                        {{ \Illuminate\Support\Str::limit(json_encode($log->payload ?? []), 60) }}
                    </td>
                    <td style="text-align:right;">
                        <form method="POST" action="{{ route('keamanan.logs.destroy', $log->id) }}" onsubmit="return confirm('Hapus rekaman log ini?');" style="margin:0; display:inline-block;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn danger" style="padding:.2rem .5rem; font-size:.75rem;">
                                ✕
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; padding:2rem; color:#9ca3af;">
                        Tidak ada log ancaman yang sesuai dengan filter.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1.5rem;">
        {{ $logs->links() }}
    </div>
</div>
@endsection
