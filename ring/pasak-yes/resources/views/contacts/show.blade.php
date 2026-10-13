@extends('layouts.app')
@section('title', 'Kontak '.$contact->name)
@section('content')
<p><a href="{{ route('contacts.index') }}">← Daftar kontak</a></p>
<div class="card" style="max-width:36rem">
@if($contact->avatarUrl())
<p><img src="{{ $contact->avatarUrl() }}" alt="avatar {{ $contact->name }}" width="160" style="border-radius:.75rem"></p>
@endif
<h1 style="margin-top:0">{{ $contact->name }}</h1>
<ul>
<li><b>Email:</b> {{ $contact->email }}</li>
<li><b>Telepon:</b> {{ $contact->phone ?? '—' }}</li>
<li><b>Alamat:</b> {{ $contact->address ?? '—' }}</li>
</ul>
<p>
<a class="btn primary" href="{{ route('contacts.edit', $contact) }}">Ubah</a>
<a class="btn" href="{{ route('contacts.index') }}">Kembali</a>
</p>
<form method="POST" action="{{ route('contacts.destroy', $contact) }}" onsubmit="return confirm('Hapus kontak ini?')">
@csrf @method('DELETE')
<button class="btn danger" type="submit">Hapus</button>
</form>
</div>
@endsection
