@extends('layouts.app')
@section('title', 'tags')
@section('content')
<h1 style="text-transform:capitalize">tags</h1>
<p><a class="btn primary" href="{{ route('tags.create') }}">+ Tambah</a></p>
<div class="card"><table><thead><tr><th>Nama</th><th>Slug</th><th>Jumlah Post</th><th>Aksi</th></tr></thead><tbody>
@foreach($tags as $item)
<tr><td>{{ $item->name }}</td><td><code>{{ $item->slug }}</code></td><td>{{ $item->posts_count }}</td><td><a href="{{ route('tags.edit', $item) }}">Ubah</a></td></tr>
@endforeach
</tbody></table><p>{{ $tags->links() }}</p></div>
@endsection
