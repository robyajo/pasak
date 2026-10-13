@extends('layouts.app')
@section('title', 'Tambah Kontak')
@section('content')
<div class="hero"><h1>➕ Tambah Kontak</h1><p>Tambahkan entri baru ke buku alamat.</p></div>
<div class="card" style="max-width:36rem">
<form method="POST" action="{{ route('contacts.store') }}" enctype="multipart/form-data">
@include('contacts._form', ['submitLabel' => 'Simpan'])
</form>
</div>
@endsection
