@extends('layouts.app')
@section('title', 'Beranda')
@section('content')
<div class="hero">
<h1>📰 Blog Pasak-No</h1>
<p>Catatan seputar WAF, keamanan web, dan Laravel — ditulis dari lab yang sengaja rentan untuk audit.</p>
</div>

@if($featured !== null)
<article class="card" style="display:flex;gap:1.5rem;flex-wrap:wrap;align-items:center">
@if($featured->thumbnailUrl())<a href="{{ route('posts.show', $featured) }}" style="flex:1 1 280px"><img src="{{ $featured->thumbnailUrl() }}" alt="{{ $featured->title }}" style="width:100%;border-radius:.5rem;object-fit:cover;max-height:280px"></a>@endif
<div style="flex:2 1 320px">
<span class="badge">✨ Sorotan</span> @if($featured->category)<span class="badge cat">{{ $featured->category->name }}</span>@endif
<h2 style="margin:.5rem 0"><a href="{{ route('posts.show', $featured) }}" style="color:#fff">{{ $featured->title }}</a></h2>
@if($featured->excerpt)<p style="color:#9ca3af">{{ $featured->excerpt }}</p>@endif
<p class="meta">{{ $featured->created_at->format('d M Y') }} · {{ $featured->created_at->diffForHumans() }}</p>
<p><a class="btn primary" href="{{ route('posts.show', $featured) }}">Baca selengkapnya →</a></p>
</div>
</article>
@endif

<h2 style="font-size:1.2rem">Tulisan terbaru</h2>
<div class="grid-posts">
@forelse($latestPosts as $p)
<article class="post-card">
@if($p->thumbnailUrl())<a href="{{ route('posts.show', $p) }}"><img class="cover" src="{{ $p->thumbnailUrl() }}" alt="{{ $p->title }}" loading="lazy"></a>@endif
<div class="body">
<div>@if($p->category)<span class="badge cat">{{ $p->category->name }}</span>@endif</div>
<h2><a href="{{ route('posts.show', $p) }}">{{ $p->title }}</a></h2>
@if($p->excerpt)<p class="ex">{{ \Illuminate\Support\Str::limit($p->excerpt, 120) }}</p>@endif
<p class="meta">{{ $p->created_at->format('d M Y') }} · {{ $p->created_at->diffForHumans() }}</p>
<div>@foreach($p->tags as $t)<span class="badge">#{{ $t->name }}</span>@endforeach</div>
</div>
</article>
@empty
<div class="card">Belum ada tulisan lain. <a href="{{ route('posts.index') }}">Lihat semua →</a></div>
@endforelse
</div>
<p><a class="btn" href="{{ route('posts.index') }}">Lihat semua tulisan →</a></p>

@if($categories->isNotEmpty())
<h2 style="font-size:1.2rem">Jelajahi kategori</h2>
<p>@foreach($categories as $c)<span class="badge cat">{{ $c->name }} ({{ $c->posts_count }})</span>@endforeach</p>
@endif

<div class="card" style="border-color:{{ $pasakInstalled ? '#166534' : '#7f1d1d' }}">
<h2>{{ $pasakInstalled ? '🛡️ Terlindungi Pasak' : '⚠️ Lab ini tanpa WAF — sengaja rentan' }}</h2>
@if($pasakInstalled)
<p class="meta">WAF aktif — signature instant-block, throttle login, dan karantina perangkat berjalan.</p>
@else
<p class="meta">Paket <code>robyajo/pasak</code> belum terpasang. Pola <code>../</code>, <code>&lt;script&gt;</code>, <code>.env</code>, <code>.php.jpg</code> semuanya lolos <code>200 OK</code>. Detail tiap celah ada di tombol melayang <b>⚠️ Kerentanan</b>.</p>
<pre>cd laravel-test/laravel-none
composer require robyajo/pasak
php artisan pasak:install
php artisan migrate</pre>
@endif
</div>

<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:.75rem">
<h2 style="margin:0">🛡️ Monitor serangan masuk (live)</h2>
<div style="display:flex;gap:.5rem">
<button class="btn" onclick="loadWafHits()">↻ Muat ulang</button>
<form method="POST" action="{{ route('waf-hits.clear') }}">@csrf<button class="btn">Hapus log</button></form>
</div>
</div>
<p class="meta">Uji dari <b>app-waf</b> (http://localhost:5175). Tanpa Pasak status = 200 (lolos), dengan Pasak = 403 (diblokir). {{ $wafCount }} serangan tercatat · auto-refresh 5 detik.</p>
<div style="overflow-x:auto">
<table id="waf-table">
<thead><tr><th>Waktu</th><th>IP virtual</th><th>Method</th><th>Path</th><th>Pola</th><th>Status</th></tr></thead>
<tbody>
@forelse($wafHits as $h)
<tr><td>{{ $h['time'] ?? '' }}</td><td><code>{{ $h['virtual_ip'] ?? $h['ip'] ?? '' }}</code></td><td>{{ $h['method'] ?? '' }}</td><td style="max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $h['path'] ?? '' }}</td><td><code>{{ $h['pattern'] ?? '' }}</code></td><td>{{ $h['status'] ?? '' }}</td></tr>
@empty
<tr><td colspan="6" class="meta">Belum ada serangan tercatat. Kirim vektor dari app-waf dulu.</td></tr>
@endforelse
</tbody>
</table>
</div>
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
@endsection
