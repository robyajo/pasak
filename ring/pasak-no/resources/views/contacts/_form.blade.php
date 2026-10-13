@csrf
@if(isset($contact))
    @method('PUT')
@endif
<label>Nama*<input type="text" name="name" value="{{ old('name', $contact->name ?? '') }}" required></label>
<label>Email*<input type="email" name="email" value="{{ old('email', $contact->email ?? '') }}" required></label>
<label>Telepon<input type="text" name="phone" value="{{ old('phone', $contact->phone ?? '') }}"></label>
<label>Alamat<textarea name="address" rows="3">{{ old('address', $contact->address ?? '') }}</textarea></label>
<label>Avatar (jpg/png/gif/webp, maks 2MB)<input type="file" name="avatar" accept="image/*"></label>
@if(isset($contact) && $contact->avatarUrl())
<p><img src="{{ $contact->avatarUrl() }}" alt="avatar" width="96" style="border-radius:.5rem"></p>
<p class="muted">Avatar saat ini — unggah baru untuk mengganti.</p>
@endif
<p><button class="btn primary" type="submit">{{ $submitLabel ?? 'Simpan' }}</button>
<a class="btn" href="{{ route('contacts.index') }}">Batal</a></p>
