<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Daftar Kontak</title></head>
<body style="font-family:system-ui,sans-serif;max-width:52rem;margin:0 auto;padding:1.5rem">
<h1>📇 Daftar Kontak</h1>
<p><a href="{{ route('contacts.create') }}">+ Tambah Kontak</a> · <a href="/">Beranda</a></p>
@if(session('status'))<p style="color:#15803d">{{ session('status') }}</p>@endif
<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%">
<thead><tr><th>Avatar</th><th>Nama</th><th>Email</th><th>Telepon</th><th>Aksi</th></tr></thead>
<tbody>
@forelse($contacts as $contact)
<tr>
    <td>@if($contact->avatarUrl())<img src="{{ $contact->avatarUrl() }}" width="48" style="border-radius:.375rem">@else<span style="color:#888">—</span>@endif</td>
    <td>{{ $contact->name }}</td>
    <td>{{ $contact->email }}</td>
    <td>{{ $contact->phone ?? '—' }}</td>
    <td><a href="{{ route('contacts.show', $contact) }}">Lihat</a> · <a href="{{ route('contacts.edit', $contact) }}">Ubah</a></td>
</tr>
@empty
<tr><td colspan="5">Belum ada kontak.</td></tr>
@endforelse
</tbody>
</table>
<p>{{ $contacts->links() }}</p>
</body>
</html>
