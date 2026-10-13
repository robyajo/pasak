@extends('layouts.app')
@section('title', 'Ubah Kategori')
@section('content')
<h1>Ubah: {{ $category->name }}</h1>
<div class="card"><form method="POST" action="{{ route('categories.update', $category) }}">@csrf @method('PUT')
<label>Nama*<input type="text" name="name" value="{{ old('name', $category->name) }}" required></label>
<label>Slug<input type="text" name="slug" value="{{ old('slug', $category->slug) }}"></label>
<p><button class="btn primary" type="submit">Perbarui</button> <a href="{{ route('categories.index') }}">Batal</a></p></form>
<form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn danger" type="submit">Hapus</button></form></div>
@endsection
