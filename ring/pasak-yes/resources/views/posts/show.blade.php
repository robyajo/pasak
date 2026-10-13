@extends('layouts.app')
@section('title', $post->title)
@section('content')
<article class="article">
<p><a href="{{ route('posts.index') }}">← Semua tulisan</a></p>
@if($post->category)<span class="badge cat">{{ $post->category->name }}</span>@endif
@foreach($post->tags as $t)<span class="badge">#{{ $t->name }}</span>@endforeach
<h1>{{ $post->title }}</h1>
<p class="meta">{{ $post->created_at->format('d M Y H:i') }} · {{ $post->created_at->diffForHumans() }} · {{ $post->is_published ? 'Published' : 'Draft' }}</p>
@if($post->thumbnailUrl())<img class="hero-img" src="{{ $post->thumbnailUrl() }}" alt="{{ $post->title }}">@endif
@if($post->excerpt)<p class="lead">{{ $post->excerpt }}</p>@endif
<div class="content">{!! nl2br(e($post->body)) !!}</div>
<hr style="border-color:#1f2937;margin:2rem 0">
<p><a class="btn" href="{{ route('posts.edit', $post) }}">✏️ Ubah</a></p>
<form method="POST" action="{{ route('posts.destroy', $post) }}" onsubmit="return confirm('Hapus post ini?')">@csrf @method('DELETE')<button class="btn danger" type="submit">🗑️ Hapus</button></form>
@if($related->isNotEmpty())
<h2 style="font-size:1.2rem;margin-top:2.5rem">Bacaan terkait</h2>
<div class="grid-posts">
@foreach($related as $r)
<article class="post-card">
@if($r->thumbnailUrl())<a href="{{ route('posts.show', $r) }}"><img class="cover" src="{{ $r->thumbnailUrl() }}" alt="{{ $r->title }}" loading="lazy"></a>@endif
<div class="body">
<h2><a href="{{ route('posts.show', $r) }}">{{ $r->title }}</a></h2>
<p class="meta">{{ $r->created_at->format('d M Y') }}</p>
</div>
</article>
@endforeach
</div>
@endif
</article>
@endsection
