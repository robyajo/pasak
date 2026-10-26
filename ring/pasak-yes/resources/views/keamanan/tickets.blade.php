@extends('layouts.app')
@section('title', 'Tiket Banding Unblock — Pusat Keamanan Kustom')

@section('content')
@include('keamanan._nav')

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
        <div>
            <h2 style="margin:0; font-size:1.25rem;">🎫 Tiket Permohonan Banding (Self-Service Appeal)</h2>
            <p style="margin:0; color:#9ca3af; font-size:.85rem;">
                Permohonan buka blokir yang diajukan oleh pengguna sah yang mungkin terblokir tidak sengaja (false positive).
            </p>
        </div>

        {{-- Filter Status --}}
        <form method="GET" action="{{ route('keamanan.tickets') }}" style="display:flex; gap:.5rem; margin:0;">
            <select name="status" onchange="this.form.submit()" style="width:auto; min-width:160px;">
                <option value="">-- Semua Status --</option>
                <option value="pending" {{ $filterStatus === 'pending' ? 'selected' : '' }}>⏳ Menunggu (Pending)</option>
                <option value="approved" {{ $filterStatus === 'approved' ? 'selected' : '' }}>✅ Disetujui (Approved)</option>
                <option value="rejected" {{ $filterStatus === 'rejected' ? 'selected' : '' }}>❌ Ditolak (Rejected)</option>
            </select>
            @if($filterStatus)
                <a href="{{ route('keamanan.tickets') }}" class="btn" style="font-size:.85rem;">Reset</a>
            @endif
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width:130px;">No. Tiket</th>
                    <th style="width:160px;">Pemohon</th>
                    <th style="width:160px;">Alamat IP / Device</th>
                    <th>Alasan Pengajuan Banding</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:140px;">Diajukan</th>
                    <th style="width:220px; text-align:right;">Keputusan Admin</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                <tr>
                    <td>
                        <b style="color:#60a5fa;">#{{ $ticket->ticket_number }}</b>
                    </td>
                    <td>
                        <b>{{ $ticket->name }}</b>
                        <div style="font-size:.75rem; color:#9ca3af;">{{ $ticket->email }}</div>
                    </td>
                    <td>
                        <code>{{ $ticket->ip_address }}</code>
                        @if($ticket->device_id)
                            <div style="font-size:.7rem; color:#9ca3af;">Dev: <code>{{ \Illuminate\Support\Str::limit($ticket->device_id, 10) }}</code></div>
                        @endif
                    </td>
                    <td style="font-size:.85rem;">
                        {{ $ticket->reason }}
                        @if($ticket->admin_notes)
                            <div style="font-size:.75rem; color:#fde047; margin-top:.35rem; background:#1f2937; padding:.3rem .5rem; border-radius:.25rem;">
                                💬 Catatan: {{ $ticket->admin_notes }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <span class="badge-status {{ $ticket->status }}">
                            {{ strtoupper($ticket->status) }}
                        </span>
                    </td>
                    <td style="color:#9ca3af; font-size:.8rem;">
                        {{ $ticket->created_at?->format('d M Y H:i') ?? '-' }}
                    </td>
                    <td style="text-align:right;">
                        @if($ticket->status === 'pending')
                        <div style="display:flex; flex-direction:column; gap:.35rem; align-items:flex-end;">
                            {{-- Form Setujui --}}
                            <form method="POST" action="{{ route('keamanan.tickets.respond', $ticket->id) }}" style="margin:0; display:inline-flex; gap:.25rem;">
                                @csrf
                                <input type="hidden" name="status" value="approved">
                                <input type="text" name="admin_notes" placeholder="Catatan persetujuan..." style="font-size:.75rem; padding:.2rem .4rem; width:120px;">
                                <button type="submit" class="btn primary" style="font-size:.75rem; padding:.2rem .5rem;">
                                    ✓ Buka
                                </button>
                            </form>

                            {{-- Form Tolak --}}
                            <form method="POST" action="{{ route('keamanan.tickets.respond', $ticket->id) }}" style="margin:0; display:inline-flex; gap:.25rem;">
                                @csrf
                                <input type="hidden" name="status" value="rejected">
                                <input type="text" name="admin_notes" placeholder="Alasan penolakan..." style="font-size:.75rem; padding:.2rem .4rem; width:120px;">
                                <button type="submit" class="btn danger" style="font-size:.75rem; padding:.2rem .5rem;">
                                    ✕ Tolak
                                </button>
                            </form>
                        </div>
                        @else
                        <div style="display:inline-flex; gap:.25rem; align-items:center;">
                            <span style="font-size:.75rem; color:#9ca3af;">Selesai</span>
                            <form method="POST" action="{{ route('keamanan.tickets.destroy', $ticket->id) }}" onsubmit="return confirm('Hapus tiket ini?');" style="margin:0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn danger" style="font-size:.75rem; padding:.2rem .5rem;">🗑️</button>
                            </form>
                        </div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:2rem; color:#9ca3af;">
                        Tidak ada tiket permohonan banding unblock.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1.5rem;">
        {{ $tickets->links() }}
    </div>
</div>
@endsection
