<div style="max-width:36rem">
    @csrf
    @if(isset($contact))
        @method('PUT')
    @endif
    <p>
        <label>Nama*<br>
        <input type="text" name="name" value="{{ old('name', $contact->name ?? '') }}" required style="width:100%"></label>
    </p>
    <p>
        <label>Email*<br>
        <input type="email" name="email" value="{{ old('email', $contact->email ?? '') }}" required style="width:100%"></label>
    </p>
    <p>
        <label>Telepon<br>
        <input type="text" name="phone" value="{{ old('phone', $contact->phone ?? '') }}" style="width:100%"></label>
    </p>
    <p>
        <label>Alamat<br>
        <textarea name="address" rows="3" style="width:100%">{{ old('address', $contact->address ?? '') }}</textarea></label>
    </p>
    <p>
        <label>Avatar (jpg/png/gif/webp, maks 2MB)<br>
        <input type="file" name="avatar" accept="image/*"></label>
        @if(isset($contact) && $contact->avatarUrl())
            <br><img src="{{ $contact->avatarUrl() }}" alt="avatar" width="96" style="margin-top:.5rem;border-radius:.5rem">
            <br><small>Avatar saat ini — unggah baru untuk mengganti.</small>
        @endif
    </p>
    @if ($errors->any())
        <ul style="color:#b91c1c">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    <p><button type="submit">{{ $submitLabel ?? 'Simpan' }}</button>
    <a href="{{ route('contacts.index') }}">Batal</a></p>
</div>
