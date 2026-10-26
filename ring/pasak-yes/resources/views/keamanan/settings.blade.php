@extends('layouts.app')
@section('title', 'Pengaturan WAF Dinamis — Pusat Keamanan Kustom')

@section('content')
@include('keamanan._nav')

<div class="two-col">
    {{-- Form Pengaturan WAF --}}
    <div class="card">
        <h2 style="margin:0 0 .5rem 0; font-size:1.15rem;">⚙️ Konfigurasi Mesin WAF & Pemblokiran</h2>
        <p style="margin:0 0 1.25rem 0; color:#9ca3af; font-size:.85rem;">
            Pengaturan ini disimpan di database (runtime database settings) dan berlaku langsung tanpa restart server atau edit <code>.env</code>.
        </p>

        <form method="POST" action="{{ route('keamanan.settings.update') }}">
            @csrf

            {{-- Mode Penegakan Blokir --}}
            <label style="margin-bottom:1.25rem;">
                <b style="color:#f3f4f6; font-size:.9rem;">Mode Penegakan Blokir (Block Enforcement)</b>
                <div style="font-size:.8rem; color:#9ca3af; margin-bottom:.5rem;">
                    Bila dimatikan, WAF berjalan dalam mode simulasi (hanya mencatat ancaman di log tanpa mengembalikan 403 Forbidden).
                </div>
                <select name="block_enforcement">
                    <option value="1" {{ !empty($settings['block_enforcement']) ? 'selected' : '' }}>🛡️ Aktif Penuh (Mengembalikan HTTP 403 Forbidden)</option>
                    <option value="0" {{ empty($settings['block_enforcement']) ? 'selected' : '' }}>🧪 Simulasi / Pasif (Catat Log Ancaman Saja)</option>
                </select>
            </label>

            {{-- Instant Block --}}
            <label style="margin-bottom:1.25rem;">
                <b style="color:#f3f4f6; font-size:.9rem;">Pemblokiran Instan (Instant Block)</b>
                <div style="font-size:.8rem; color:#9ca3af; margin-bottom:.5rem;">
                    Langsung blokir penyerang pada percobaan pertama jika terdeteksi muatan berbahaya berkategori kritis (ReDoS, Null-byte, webshell probe).
                </div>
                <select name="instant_block_enabled">
                    <option value="1" {{ !empty($settings['instant_block_enabled']) ? 'selected' : '' }}>✅ Aktif (Blokir Instan pada Serangan Kritis)</option>
                    <option value="0" {{ empty($settings['instant_block_enabled']) ? 'selected' : '' }}>❌ Non-aktif</option>
                </select>
            </label>

            {{-- Auto Block --}}
            <label style="margin-bottom:1.25rem;">
                <b style="color:#f3f4f6; font-size:.9rem;">Pemblokiran Otomatis Berulang (Auto-Block)</b>
                <div style="font-size:.8rem; color:#9ca3af; margin-bottom:.5rem;">
                    Otomatis mengisolasi IP atau perangkat setelah mencapai ambang batas pelanggaran tertentu dalam jendela waktu pemantauan.
                </div>
                <select name="auto_block_enabled">
                    <option value="1" {{ !empty($settings['auto_block_enabled']) ? 'selected' : '' }}>✅ Aktifkan Auto-Block</option>
                    <option value="0" {{ empty($settings['auto_block_enabled']) ? 'selected' : '' }}>❌ Non-aktifkan</option>
                </select>
            </label>

            {{-- Lingkup Karantina (Scope) --}}
            <label style="margin-bottom:1.25rem;">
                <b style="color:#f3f4f6; font-size:.9rem;">Lingkup Default Karantina (Block Scope)</b>
                <div style="font-size:.8rem; color:#9ca3af; margin-bottom:.5rem;">
                    Tingkat isolasi yang diterapkan saat pemblokiran otomatis terjadi.
                </div>
                <select name="auto_block_scope">
                    <option value="device" {{ ($settings['auto_block_scope'] ?? '') === 'device' ? 'selected' : '' }}>📱 Device Quarantine (Hanya perangkat pelanggar via X-Device-Id)</option>
                    <option value="ip" {{ ($settings['auto_block_scope'] ?? '') === 'ip' ? 'selected' : '' }}>🌐 IP Publik Saja (Seluruh jaringan NAT / Router)</option>
                    <option value="auto" {{ ($settings['auto_block_scope'] ?? '') === 'auto' ? 'selected' : '' }}>⚡ Auto (Device jika ada header, fallback ke IP)</option>
                </select>
            </label>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:1.25rem;">
                <label>
                    <b style="color:#f3f4f6; font-size:.9rem;">Ambang Batas Pelanggaran</b>
                    <input type="number" name="auto_block_threshold" value="{{ $settings['auto_block_threshold'] ?? 3 }}" min="1" max="100">
                </label>
                <label>
                    <b style="color:#f3f4f6; font-size:.9rem;">Durasi Blokir (Jam)</b>
                    <input type="number" name="auto_block_duration" value="{{ $settings['auto_block_duration'] ?? 24 }}" min="1" max="8760">
                </label>
            </div>

            <button type="submit" class="btn primary" style="width:100%; font-weight:700; font-size:.9rem; padding:.6rem;">
                💾 Simpan Perubahan Pengaturan
            </button>
        </form>
    </div>

    {{-- Penjelasan Arsitektur Database Settings --}}
    <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <h2 style="margin:0 0 .5rem 0; font-size:1.15rem;">🔒 Keamanan Runtime Tanpa Sentuh Vendor</h2>
            <p style="color:#9ca3af; font-size:.85rem; line-height:1.6;">
                Melalui Facade <code>Pasak::getSettings()</code> dan <code>Pasak::updateSettings()</code>, sistem Anda memiliki keunggulan berikut:
            </p>
            <ul style="color:#9ca3af; font-size:.85rem; padding-left:1.25rem; line-height:1.7;">
                <li><b>Zero Code Modification:</b> Seluruh controller dan views berada di dalam folder aplikasi host (<code>app/Http/Controllers/KeamananController.php</code>).</li>
                <li><b>Pemisahan Lapis (Layering):</b> Pengaturan disimpan dalam tabel <code>pasak_settings</code> sehingga tidak mengubah berkas <code>config/pasak.php</code> atau <code>vendor/</code>.</li>
                <li><b>Audit Trail:</b> Setiap penyesuaian aturan langsung terbaca oleh engine deteksi WAF pada siklus permintaan berikutnya.</li>
            </ul>
        </div>

        <div style="background:#0d1420; border:1px solid #1f2937; border-radius:.5rem; padding:.85rem; font-size:.8rem; color:#9ca3af;">
            <b style="color:#60a5fa;">💡 Tips Produksi:</b>
            <div style="margin-top:.25rem;">
                Sebelum menerapkan pemblokiran agresif di lingkungan produksi, gunakan mode <i>Simulasi</i> selama 24 jam pertama untuk memantau kemungkinan <i>false positives</i> dari lalu lintas sah.
            </div>
        </div>
    </div>
</div>
@endsection
