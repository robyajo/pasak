<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Status Keamanan — {{ $pasakInstalled ? 'Terlindungi Pasak' : 'TIDAK AMAN' }}</title>
<style>
*{box-sizing:border-box}body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#0b0f14;color:#e6edf3}
.wrap{max-width:72rem;margin:0 auto;padding:1.5rem}
.banner{border-radius:.75rem;padding:1.25rem 1.5rem;margin-bottom:1.25rem;border:1px solid}
.danger{background:#3d0a0a;border-color:#7f1d1d}
.safe{background:#052e16;border-color:#166534}
.banner h1{margin:.25rem 0;font-size:1.5rem}
.badge{display:inline-block;font-size:.75rem;font-weight:700;padding:.15rem .6rem;border-radius:999px;letter-spacing:.05em}
.danger .badge{background:#ef4444;color:#fff}
.safe .badge{background:#22c55e;color:#052e16}
.card{background:#111827;border:1px solid #1f2937;border-radius:.75rem;padding:1.25rem;margin-bottom:1.25rem}
.card h2{margin:0 0 .5rem;font-size:1.1rem}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem}
.stat{background:#0d1420;border:1px solid #1f2937;border-radius:.5rem;padding:1rem;text-align:center}
.stat b{font-size:1.6rem;display:block}
table{width:100%;font-size:.85rem;border-collapse:collapse}
th,td{padding:.5rem;border-bottom:1px solid #1f2937;text-align:left;vertical-align:top}
code{background:#1f2937;padding:.1rem .35rem;border-radius:.25rem;font-size:.8rem}
.btn{border:1px solid #374151;background:#1f2937;color:#fff;border-radius:.375rem;padding:.4rem .9rem;cursor:pointer}
.btn:hover{background:#374151}
a{color:#60a5fa}
.reason li{margin:.35rem 0}
.muted{color:#9ca3af;font-size:.85rem}
pre{background:#0d1420;border:1px solid #1f2937;border-radius:.5rem;padding:.75rem;overflow-x:auto;font-size:.8rem}
</style>
</head>
<body>
<div class="wrap">

@if($pasakInstalled)
  <div class="banner safe">
    <span class="badge">AMAN</span>
    <h1>🛡️ Terlindungi oleh Pasak (Bulwark)</h1>
    <p>WAF aktif — signature instant-block, throttle login, dan karantina perangkat berjalan.</p>
  </div>
@else
  <div class="banner danger">
    <span class="badge">TIDAK AMAN — TANPA PASAK</span>
    <h1>⚠️ Aplikasi ini terbuka terhadap serangan</h1>
    <p>Paket <code>robyajo/pasak</code> <b>belum terpasang</b>. Semua request lolos tanpa penyaringan.</p>
  </div>

  <div class="card">
    <h2>Kenapa tidak aman tanpa Pasak?</h2>
    <ol class="reason">
      <li><b>Tanpa WAF / penyaringan:</b> pola <code>../</code>, <code>&lt;script&gt;</code>, <code>.env</code>, <code>{{7*7}}</code>, double-extension <code>.php.jpg</code> semuanya dijawab <code>200 OK</code> — terbukti di tabel serangan di bawah.</li>
      <li><b>Tanpa pemblokiran:</b> pencatat di halaman ini hanya <i>mengamati</i> (middleware <code>LogWafHits</code>), tidak pernah mengembalikan <code>403</code>. Penyerang bisa mencoba tanpa batas.</li>
      <li><b>Tanpa throttle login &amp; karantina IP/device:</b> brute-force <code>/login</code>, scan <code>wp-admin / .git</code>, dan upload webshell tidak dihentikan.</li>
      <li><b>Tanpa audit server:</b> tidak ada baseline integritas file, pemindaian webshell, maupun dasbor log terpusat.</li>
    </ol>
    <h2>Cara mengamankan (pasang Pasak)</h2>
<pre>cd laravel-test/laravel-none
composer require robyajo/pasak
php artisan pasak:install
php artisan migrate
# uji lagi dari app-waf → vektor instant-block harus jadi 403</pre>
  </div>
@endif

  <div class="grid">
    <div class="stat"><b>{{ $wafCount }}</b><span class="muted">serangan tercatat</span></div>
    <div class="stat"><b>{{ $pasakInstalled ? '403' : '200' }}</b><span class="muted">respons serangan (kini)</span></div>
    <div class="stat"><b>{{ $pasakInstalled ? 'Aktif' : 'Mati' }}</b><span class="muted">status WAF</span></div>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:.75rem">
      <h2>🛡️ Monitor Serangan Masuk (live)</h2>
      <div style="display:flex;gap:.5rem">
        <button class="btn" onclick="loadWafHits()">↻ Muat ulang</button>
        <form method="POST" action="{{ route('waf-hits.clear') }}">@csrf<button class="btn">Hapus log</button></form>
      </div>
    </div>
    <p class="muted">Uji dari <b>app-waf</b> (http://localhost:5175). Tanpa Pasak status = 200 (lolos). Setelah Pasak terpasang = 403 (diblokir). Auto-refresh 5 detik.</p>
    <div style="overflow-x:auto">
    <table id="waf-table">
      <thead><tr><th>Waktu</th><th>IP virtual</th><th>Method</th><th>Path</th><th>Pola</th><th>Status</th></tr></thead>
      <tbody>
      @forelse($wafHits as $h)
        <tr><td>{{ $h['time'] ?? '' }}</td><td><code>{{ $h['virtual_ip'] ?? $h['ip'] ?? '' }}</code></td><td>{{ $h['method'] ?? '' }}</td><td style="max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $h['path'] ?? '' }}</td><td><code>{{ $h['pattern'] ?? '' }}</code></td><td>{{ $h['status'] ?? '' }}</td></tr>
      @empty
        <tr><td colspan="6" class="muted">Belum ada serangan tercatat. Kirim vektor dari app-waf dulu.</td></tr>
      @endforelse
      </tbody>
    </table>
    </div>
  </div>

  <p class="muted" style="text-align:center">Demo lokal · <a href="/login">Login</a> · <a href="/waf-hits.json">waf-hits.json</a> · log: <code>storage/logs/waf-hits.log</code></p>
</div>
<script>
async function loadWafHits(){
  try{
    const r = await fetch('/waf-hits.json'); const j = await r.json();
    const tb = document.querySelector('#waf-table tbody');
    if(!j.hits.length){ tb.innerHTML = '<tr><td colspan="6">Belum ada serangan.</td></tr>'; return; }
    tb.innerHTML = j.hits.map(h=>`<tr><td>${h.time||''}</td><td><code>${h.virtual_ip||h.ip||''}</code></td><td>${h.method||''}</td><td>${(h.path||'').slice(0,90)}</td><td><code>${h.pattern||''}</code></td><td>${h.status||''}</td></tr>`).join('');
  }catch(e){ alert('Gagal memuat: '+e.message); }
}
setInterval(loadWafHits, 5000);
</script>
</body>
</html>
