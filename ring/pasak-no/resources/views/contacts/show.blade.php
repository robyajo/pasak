<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kontak {{ $contact->name }}</title></head>
<body style="font-family:system-ui,sans-serif;max-width:52rem;margin:0 auto;padding:1.5rem">
<h1>{{ $contact->name }}</h1>
@if(session('status'))<p style="color:#15803d">{{ session('status') }}</p>@endif
@if($contact->avatarUrl())
<p><img src="{{ $contact->avatarUrl() }}" alt="avatar {{ $contact->name }}" width="160" style="border-radius:.75rem"></p>
@endif
<ul>
<li><b>Email:</b> {{ $contact->email }}</li>
<li><b>Telepon:</b> {{ $contact->phone ?? '—' }}</li>
<li><b>Alamat:</b> {{ $contact->address ?? '—' }}</li>
</ul>
<p>
<a href="{{ route('contacts.edit', $contact) }}">Ubah</a> ·
<a href="{{ route('contacts.index') }}">Kembali</a>
</p>
<form method="POST" action="{{ route('contacts.destroy', $contact) }}" onsubmit="return confirm('Hapus kontak ini?')">
@csrf @method('DELETE')
<button type="submit" style="color:#b91c1c">Hapus</button>
</form>
</body>
</html>
