@extends('layouts.app')
@section('title', 'Tambah')
@section('content')
<h1>Tambah</h1>
<div class="card"><form method="POST" action="{{ route('categories.store') }}">@csrf
<label>Nama*<input type="text" name="name" value="{{ old('name') }}" required></label>
<label>Slug (otomatis bila kosong)<input type="text" name="slug" value="{{ old('slug') }}"></label>
<p><button class="btn primary" type="submit">Simpan</button></p></form></div>
@endsection
