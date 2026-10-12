<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tambah Kontak</title></head>
<body style="font-family:system-ui,sans-serif;max-width:52rem;margin:0 auto;padding:1.5rem">
<h1>➕ Tambah Kontak</h1>
<form method="POST" action="{{ route('contacts.store') }}" enctype="multipart/form-data">
@include('contacts._form', ['submitLabel' => 'Simpan'])
</form>
</body>
</html>
