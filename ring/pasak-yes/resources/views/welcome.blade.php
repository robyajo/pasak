@extends('layouts.app')
@section('title', 'Beranda')
@section('content')
<div class="hero">
<h1>📰 Blog Pasak-Yes</h1>
<p>Catatan seputar WAF, keamanan web, dan Laravel — ditulis dari lab yang dilindungi Pasak.</p>
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

<div class="card" style="border-color:#166534">
<h2>🛡️ Terlindungi Pasak (Bulwark)</h2>
<p class="meta">WAF aktif — signature instant-block, throttle login, dan karantina perangkat berjalan. Coba kirim vektor serangan dari <b>app-waf</b>: pola berbahaya dijawab <code>403</code>.</p>
@if(Route::has('pasak.dashboard.overview'))
<p><a class="btn primary" href="{{ route('pasak.dashboard.overview') }}">Buka dasbor keamanan →</a></p>
@endif
</div>
@endsection
