@extends('layouts.app')
@section('title', 'Daftar Whitelist IP — Pusat Keamanan Kustom')

@section('content')
@include('keamanan._nav')

<div class="two-col">
    {{-- Form Tambah Whitelist --}}
    <div class="card">
        <h2 style="margin:0 0 .5rem 0; font-size:1.15rem;">✅ Daftarkan Alamat IP / Subnet Whitelist</h2>
        <p style="margin:0 0 1rem 0; color:#9ca3af; font-size:.85rem;">
            Alamat IP atau subnet CIDR di daftar ini akan dilewatkan sepenuhnya dari inspeksi dan pemblokiran WAF.
        </p>

        <form method="POST" action="{{ route('keamanan.allowed-ips.store') }}">
            @csrf
            <label>
                Alamat IP / Subnet (CIDR) *
                <input type="text" name="ip_address" placeholder="misal: 192.168.1.50 atau 10.0.0.0/24" required>
            </label>

            <label>
                Label / Nama Pengenal
                <input type="text" name="label" placeholder="misal: Kantor Pusat, Webhook Midtrans, Server Pemantau">
            </label>

            <label>
                Catatan Tambahan
                <textarea name="notes" rows="2" placeholder="Catatan opsional untuk dokumentasi tim..."></textarea>
            </label>

            <button type="submit" class="btn primary" style="width:100%; font-weight:700; margin-top:.5rem;">
                + Tambah ke Daftar Whitelist
            </button>
        </form>
    </div>

    {{-- Info Card --}}
    <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <h2 style="margin:0 0 .5rem 0; font-size:1.15rem;">💡 Kebijakan Whitelist Aman</h2>
            <p style="color:#9ca3af; font-size:.85rem; line-height:1.6;">
                Gunakan fitur Whitelist secara bijak:
            </p>
            <ul style="color:#9ca3af; font-size:.85rem; padding-left:1.25rem; line-height:1.7;">
                <li><b>Webhook & Payment Gateway:</b> Pastikan IP callback payment gateway (Midtrans, Stripe, Xendit) terdaftar agar muatan JSON tidak sengaja dicurigai.</li>
                <li><b>IP Statis Kantor / VPN:</b> Mencegah staf internal terblokir saat melakukan pengujian form atau upload berkas besar.</li>
                <li><b>Mendukung CIDR:</b> Format seperti <code>192.168.1.0/24</code> didukung penuh untuk mencakup seluruh blok jaringan lokal.</li>
            </ul>
        </div>
        <div style="background:#0d1420; border:1px solid #1f2937; border-radius:.5rem; padding:.75rem; font-size:.8rem; color:#9ca3af;">
            🔒 <i>Pasak memprioritaskan evaluasi Whitelist sebelum mengeksekusi aturan deteksi tanda tangan ancaman apapun.</i>
        </div>
    </div>
</div>

<div class="card">
    <div style="margin-bottom:1rem;">
        <h2 style="margin:0; font-size:1.15rem;">📋 Daftar Alamat IP yang Diizinkan</h2>
        <p style="margin:0; color:#9ca3af; font-size:.85rem;">
            Total {{ $allowedIps->total() }} entri whitelist tersimpan di database.
        </p>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th style="width:180px;">Alamat IP / CIDR</th>
                    <th style="width:160px;">Label</th>
                    <th>Catatan</th>
                    <th style="width:140px;">Didaftarkan</th>
                    <th style="width:90px;">Status</th>
                    <th style="width:120px; text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allowedIps as $item)
                <tr>
                    <td><code>#{{ $item->id }}</code></td>
                    <td>
                        <b style="color:#86efac;">{{ $item->ip_address }}</b>
                    </td>
                    <td>
                        <b style="color:#f3f4f6;">{{ $item->label ?? '-' }}</b>
                    </td>
                    <td style="color:#9ca3af; font-size:.85rem;">
                        {{ $item->notes ?? '-' }}
                    </td>
                    <td style="color:#9ca3af; font-size:.8rem;">
                        {{ $item->created_at?->format('d M Y') ?? '-' }}
                    </td>
                    <td>
                        <span class="badge-status {{ $item->is_active ? 'active' : 'inactive' }}">
                            {{ $item->is_active ? 'AKTIF' : 'NONAKTIF' }}
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:.25rem;">
                            {{-- Toggle Active --}}
                            <form method="POST" action="{{ route('keamanan.allowed-ips.toggle', $item->id) }}" style="margin:0;">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn" style="padding:.2rem .5rem; font-size:.75rem;" title="{{ $item->is_active ? 'Nonaktifkan whitelist' : 'Aktifkan whitelist' }}">
                                    {{ $item->is_active ? '⏸️' : '▶️' }}
                                </button>
                            </form>

                            {{-- Delete --}}
                            <form method="POST" action="{{ route('keamanan.allowed-ips.destroy', $item->id) }}" onsubmit="return confirm('Hapus alamat {{ $item->ip_address }} dari whitelist?');" style="margin:0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn danger" style="padding:.2rem .5rem; font-size:.75rem;" title="Hapus dari Whitelist">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:2rem; color:#9ca3af;">
                        Belum ada alamat IP yang ditambahkan ke whitelist.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1.5rem;">
        {{ $allowedIps->links() }}
    </div>
</div>
@endsection
