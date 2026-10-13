@extends('layouts.app')
@section('title', 'Tambah')
@section('content')
<h1>Tambah</h1>
<div class="card"><form method="POST" action="{{ route('tags.store') }}">@csrf
<label>Nama*<input type="text" name="name" value="{{ old('name') }}" required></label>
<label>Slug (otomatis bila kosong)<input type="text" name="slug" value="{{ old('slug') }}"></label>
<p><button class="btn primary" type="submit">Simpan</button> @include('layouts._autofill', ['presets' => [
    ['fields' => ['name' => 'Resep Harian']],
    ['fields' => ['name' => 'Jalan-Jalan']],
    ['fields' => ['name' => 'Ulasan Film']],
    ['fields' => ['name' => 'Tips Produktif']],
    ['fields' => ['name' => 'Komunitas']],
]])</p></form></div>
@endsection
