@extends('layouts.app')
@section('title', 'Tambah Kontak')
@section('content')
<div class="hero"><h1>➕ Tambah Kontak</h1><p>Tambahkan entri baru ke buku alamat.</p></div>
<div class="card" style="max-width:36rem">
<form method="POST" action="{{ route('contacts.store') }}" enctype="multipart/form-data">
@include('contacts._form', ['submitLabel' => 'Simpan'])
@include('layouts._autofill', ['presets' => [
    ['fields' => ['name' => 'Budi Santoso', 'email' => 'budi.santoso@contoh.test', 'phone' => '__RANDOM_PHONE__', 'address' => 'Jl. Merdeka No. 45, Pekanbaru']],
    ['fields' => ['name' => 'Siti Rahayu', 'email' => 'siti.rahayu@contoh.test', 'phone' => '__RANDOM_PHONE__', 'address' => 'Jl. Sudirman No. 12, Pekanbaru']],
    ['fields' => ['name' => 'Agus Wijaya', 'email' => 'agus.wijaya@contoh.test', 'phone' => '__RANDOM_PHONE__', 'address' => 'Jl. Hangtuah No. 8, Pekanbaru']],
    ['fields' => ['name' => 'Dewi Lestari', 'email' => 'dewi.lestari@contoh.test', 'phone' => '__RANDOM_PHONE__', 'address' => 'Jl. Cut Nyak Dien No. 21, Pekanbaru']],
]])
</form>
</div>
@endsection
