@extends('layouts.app')
@section('title', 'Daftar Kontak')
@section('content')
<div class="hero"><h1>📇 Daftar Kontak</h1><p>Buku alamat demo.</p></div>
<p><a class="btn primary" href="{{ route('contacts.create') }}">+ Tambah Kontak</a></p>
<div class="card" style="padding:0;overflow-x:auto"><table>
<thead><tr><th>Avatar</th><th>Nama</th><th>Email</th><th>Telepon</th><th>Aksi</th></tr></thead>
<tbody>
@forelse($contacts as $contact)
<tr>
    <td>@if($contact->avatarUrl())<img src="{{ $contact->avatarUrl() }}" width="48" height="48" style="border-radius:.5rem;object-fit:cover" alt="">@else<span class="muted">—</span>@endif</td>
    <td><a href="{{ route('contacts.show', $contact) }}">{{ $contact->name }}</a></td>
    <td>{{ $contact->email }}</td>
    <td>{{ $contact->phone ?? '—' }}</td>
    <td><a href="{{ route('contacts.show', $contact) }}">Lihat</a> · <a href="{{ route('contacts.edit', $contact) }}">Ubah</a></td>
</tr>
@empty
<tr><td colspan="5" class="muted">Belum ada kontak.</td></tr>
@endforelse
</tbody>
</table></div>
<p>{{ $contacts->links() }}</p>
@endsection
