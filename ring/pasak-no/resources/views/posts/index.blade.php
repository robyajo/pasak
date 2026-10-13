@extends('layouts.app')
@section('title', 'Blog')
@section('content')
<div class="hero">
<h1>📰 Blog</h1>
<p>Tulisan terbaru seputar WAF, keamanan, dan Laravel. <a class="btn primary" href="{{ route('posts.create') }}" style="margin-left:.5rem">+ Tulis Post</a></p>
</div>
<div class="grid-posts">
@forelse($posts as $p)
<article class="post-card">
@if($p->thumbnailUrl())<a href="{{ route('posts.show', $p) }}"><img class="cover" src="{{ $p->thumbnailUrl() }}" alt="{{ $p->title }}" loading="lazy"></a>@endif
<div class="body">
<div>@if($p->category)<span class="badge cat">{{ $p->category->name }}</span>@endif @unless($p->is_published)<span class="badge">Draft</span>@endunless</div>
<h2><a href="{{ route('posts.show', $p) }}">{{ $p->title }}</a></h2>
@if($p->excerpt)<p class="ex">{{ \Illuminate\Support\Str::limit($p->excerpt, 120) }}</p>@endif
<p class="meta">{{ $p->created_at->format('d M Y') }} · {{ $p->created_at->diffForHumans() }}</p>
<div>@foreach($p->tags as $t)<span class="badge">#{{ $t->name }}</span>@endforeach</div>
</div>
</article>
@empty
<div class="card">Belum ada tulisan. <a href="{{ route('posts.create') }}">Tulis yang pertama →</a></div>
@endforelse
</div>
<p style="margin-top:1.5rem">{{ $posts->links() }}</p>
@endsection
