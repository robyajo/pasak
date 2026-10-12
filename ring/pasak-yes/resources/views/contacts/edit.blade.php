<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ubah Kontak</title></head>
<body style="font-family:system-ui,sans-serif;max-width:52rem;margin:0 auto;padding:1.5rem">
<h1>✏️ Ubah Kontak</h1>
<form method="POST" action="{{ route('contacts.update', $contact) }}" enctype="multipart/form-data">
@include('contacts._form', ['submitLabel' => 'Perbarui'])
</form>
</body>
</html>
