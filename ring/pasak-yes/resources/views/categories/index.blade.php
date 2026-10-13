@extends('layouts.app')
@section('title', 'categories')
@section('content')
<h1 style="text-transform:capitalize">categories</h1>
<p><a class="btn primary" href="{{ route('categories.create') }}">+ Tambah</a></p>
<div class="card"><table><thead><tr><th>Nama</th><th>Slug</th><th>Jumlah Post</th><th>Aksi</th></tr></thead><tbody>
@foreach($categories as $item)
<tr><td>{{ $item->name }}</td><td><code>{{ $item->slug }}</code></td><td>{{ $item->posts_count }}</td><td><a href="{{ route('categories.edit', $item) }}">Ubah</a></td></tr>
@endforeach
</tbody></table><p>{{ $categories->links() }}</p></div>
@endsection
