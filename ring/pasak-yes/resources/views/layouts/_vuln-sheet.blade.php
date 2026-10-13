{{-- Tombol melayang + sheet setengah layar (50vw) berisi status proteksi Pasak. Murni Blade + JS vanilla, tanpa NPM. --}}
<button class="vuln-fab" id="vulnFab" type="button" aria-haspopup="dialog" style="background:#052e16;border-color:#22c55e">🛡️ Proteksi</button>
<div class="vuln-backdrop" id="vulnBackdrop"></div>
<section class="vuln-sheet" id="vulnSheet" role="dialog" aria-label="Status proteksi">
<header>
<span style="font-size:1.2rem">🛡️</span>
<h2>Status Proteksi Pasak</h2>
<button class="btn" id="vulnClose" type="button">Tutup ✕</button>
</header>
<div class="sheet-body">
<p class="meta">Aplikasi ini <b>dilindungi Pasak (Bulwark) WAF</b>. Lapisan yang aktif:</p>
<div class="vuln-item"><b>Instant-block signatures<span class="sev critical" style="background:#052e16;color:#bbf7d0">AKTIF</span></b><p>Null-byte, double-extension (<code>.php.jpg</code>), path traversal, probe <code>.env/.git</code>, dan SSTI diblokir <code>403</code> sejak request pertama.</p></div>
<div class="vuln-item"><b>Stepped login throttling<span class="sev high" style="background:#052e16;color:#bbf7d0">AKTIF</span></b><p>Brute-force <code>/login</code> dikunci bertingkat, reset otomatis setelah login sukses.</p></div>
<div class="vuln-item"><b>Karantina IP / perangkat<span class="sev high" style="background:#052e16;color:#bbf7d0">AKTIF</span></b><p>IP atau perangkat (<code>device_id</code>) yang melewati ambang dikarantina otomatis.</p></div>
<div class="vuln-item"><b>Upload aman<span class="sev medium" style="background:#052e16;color:#bbf7d0">AKTIF</span></b><p>Avatar &amp; thumbnail divalidasi MIME + ekstensi + ukuran; webshell ditolak sebelum tersimpan.</p></div>
<div class="vuln-item"><b>Audit &amp; log terpusat<span class="sev medium" style="background:#052e16;color:#bbf7d0">AKTIF</span></b><p>Baseline integritas, pemindaian webshell, dan dasbor log tersedia untuk admin.</p></div>
@if(Route::has('pasak.dashboard.overview'))
<p><a class="btn primary" href="{{ route('pasak.dashboard.overview') }}">Buka dasbor keamanan →</a></p>
@endif
</div>
</section>
<script>
(function(){
var fab=document.getElementById('vulnFab'),sheet=document.getElementById('vulnSheet'),bd=document.getElementById('vulnBackdrop'),closeBtn=document.getElementById('vulnClose');
function open(){sheet.classList.add('open');bd.classList.add('open');}
function shut(){sheet.classList.remove('open');bd.classList.remove('open');}
fab.addEventListener('click',open);closeBtn.addEventListener('click',shut);bd.addEventListener('click',shut);
document.addEventListener('keydown',function(e){if(e.key==='Escape'){shut();}});
})();
</script>
