{{-- Tombol melayang + sheet setengah layar (50vh) berisi informasi kerentanan lab ini. Murni Blade + JS vanilla, tanpa NPM. --}}
<button class="vuln-fab" id="vulnFab" type="button" aria-haspopup="dialog">⚠️ Kerentanan <span class="dot" id="vulnCount">?</span></button>
<div class="vuln-backdrop" id="vulnBackdrop"></div>
<section class="vuln-sheet" id="vulnSheet" role="dialog" aria-label="Informasi kerentanan">
<header>
<span style="font-size:1.2rem">⚠️</span>
<h2>Informasi Kerentanan Lab Ini</h2>
<button class="btn" id="vulnClose" type="button">Tutup ✕</button>
</header>
<div class="sheet-body">
<p class="meta">Aplikasi ini <b>sengaja tanpa WAF</b> (paket <code>robyajo/pasak</code> belum dipasang) untuk keperluan audit. Daftar celah yang terbuka:</p>
<div class="vuln-item"><b>Upload webshell via double-extension<span class="sev critical">CRITICAL</span></b><p>Berkas <code>shell.php.jpg</code> lolos validasi dan tersimpan publik, lalu dieksekusi via <code>/shell/{nama}?cmd=...</code>. Buktikan di <a href="/upload">/upload</a>.</p></div>
<div class="vuln-item"><b>Cross-Site Scripting (XSS) tercermin<span class="sev high">HIGH</span></b><p>Parameter pencarian / nama tanpa escaping (<code>&lt;script&gt;</code>) dirender mentah ke browser pengunjung.</p></div>
<div class="vuln-item"><b>Path traversal &amp; probe berkas sensitif<span class="sev high">HIGH</span></b><p>Pola <code>../</code>, <code>.env</code>, <code>.git</code>, <code>.htaccess</code> dijawab <code>200 OK</code> — tidak ada instant-block.</p></div>
<div class="vuln-item"><b>Brute-force login tanpa throttle<span class="sev high">HIGH</span></b><p>Tidak ada stepped lockout maupun karantina IP/device — endpoint <code>/login</code> bisa dicoba tanpa batas.</p></div>
<div class="vuln-item"><b>Server-Side Template Injection (SSTI)<span class="sev medium">MEDIUM</span></b><p>Canary <code>&#123;&#123;7*7&#125;&#125;</code> tidak terdeteksi; tidak ada signature yang memblokir.</p></div>
<div class="vuln-item"><b>Tanpa audit &amp; dasbor keamanan<span class="sev medium">MEDIUM</span></b><p>Tidak ada baseline integritas file, pemindaian webshell, maupun log ancaman terpusat.</p></div>
<h3 style="font-size:.9rem">Serangan tercatat (live)</h3>
<p class="meta" id="vulnLiveStatus">Memuat…</p>
<div id="vulnLiveList"></div>
<p class="meta">Pasang <code>composer require robyajo/pasak</code> + <code>php artisan pasak:install</code> untuk menutup celah di atas.</p>
</div>
</section>
<script>
(function(){
var fab=document.getElementById('vulnFab'),sheet=document.getElementById('vulnSheet'),bd=document.getElementById('vulnBackdrop'),closeBtn=document.getElementById('vulnClose');
function open(){sheet.classList.add('open');bd.classList.add('open');loadHits();}
function shut(){sheet.classList.remove('open');bd.classList.remove('open');}
fab.addEventListener('click',open);closeBtn.addEventListener('click',shut);bd.addEventListener('click',shut);
document.addEventListener('keydown',function(e){if(e.key==='Escape'){shut();}});
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
async function loadHits(){
var st=document.getElementById('vulnLiveStatus'),list=document.getElementById('vulnLiveList'),cnt=document.getElementById('vulnCount');
try{
var r=await fetch('/waf-hits.json');var j=await r.json();
cnt.textContent=j.count||0;
if(!j.hits||!j.hits.length){st.textContent='Belum ada serangan tercatat.';list.innerHTML='';return;}
st.textContent=j.count+' serangan tercatat (tanpa Pasak = lolos 200).';
list.innerHTML=j.hits.slice(0,10).map(function(h){return '<div class="vuln-item"><b>'+esc(h.method||'')+' '+esc((h.path||'').slice(0,80))+'</b><p>'+esc(h.time||'')+' · '+esc(h.virtual_ip||h.ip||'')+' · pola <code>'+esc(h.pattern||'')+'</code> · status '+esc(h.status||'')+'</p></div>';}).join('');
}catch(e){st.textContent='Gagal memuat: '+e.message;}
}
loadHits();
})();
</script>
