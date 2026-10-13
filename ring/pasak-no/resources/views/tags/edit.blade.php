@extends('layouts.app')
@section('title', 'Ubah Tag')
@section('content')
<h1>Ubah: {{ $tag->name }}</h1>
<div class="card"><form method="POST" action="{{ route('tags.update', $tag) }}">@csrf @method('PUT')
<label>Nama*<input type="text" name="name" value="{{ old('name', $tag->name) }}" required></label>
<label>Slug<input type="text" name="slug" value="{{ old('slug', $tag->slug) }}"></label>
<p><button class="btn primary" type="submit">Perbarui</button> <a href="{{ route('tags.index') }}">Batal</a></p></form>
<form method="POST" action="{{ route('tags.destroy', $tag) }}" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn danger" type="submit">Hapus</button></form></div>
@endsection
