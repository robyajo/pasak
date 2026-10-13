@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<h1>📊 Dashboard</h1>
<p class="muted">Ringkasan konten + CRUD Post lengkap (kategori, tags, thumbnail).</p>
<div class="grid">
<div class="stat"><b>{{ $postCount }}</b><span class="muted">Posts</span></div>
<div class="stat"><b>{{ $categoryCount }}</b><span class="muted">Categories</span></div>
<div class="stat"><b>{{ $tagCount }}</b><span class="muted">Tags</span></div>
<div class="stat"><b>{{ $contactCount }}</b><span class="muted">Contacts</span></div>
</div>
<div class="card">
<h2>Post terbaru</h2>
<table><thead><tr><th>Thumbnail</th><th>Judul</th><th>Kategori</th><th>Tags</th><th>Aksi</th></tr></thead>
<tbody>
@forelse($latestPosts as $p)
<tr>
<td>@if($p->thumbnailUrl())<img class="thumb" src="{{ $p->thumbnailUrl() }}" width="64" height="48">@else<span class="muted">—</span>@endif</td>
<td><a href="{{ route('posts.show', $p) }}">{{ $p->title }}</a></td>
<td>{{ $p->category->name ?? '—' }}</td>
<td>@foreach($p->tags as $t)<span class="badge">{{ $t->name }}</span>@endforeach</td>
<td><a href="{{ route('posts.edit', $p) }}">Ubah</a></td>
</tr>
@empty<tr><td colspan="5" class="muted">Belum ada post. <a href="{{ route('posts.create') }}">Buat pertama →</a></td></tr>
@endforelse
</tbody></table>
<p><a class="btn primary" href="{{ route('posts.create') }}">+ Tulis Post</a></p>
</div>
@endsection
