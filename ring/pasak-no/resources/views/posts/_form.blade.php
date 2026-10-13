@php $isEdit = isset($post); @endphp
<div class="card" style="max-width:46rem;margin:0 auto">
<form method="POST" action="{{ $isEdit ? route('posts.update', $post) : route('posts.store') }}" enctype="multipart/form-data">
@csrf @if($isEdit) @method('PUT') @endif
<label>Judul*<input type="text" name="title" value="{{ old('title', $post->title ?? '') }}" required></label>
<label>Slug (kosongkan = otomatis)<input type="text" name="slug" value="{{ old('slug', $post->slug ?? '') }}" placeholder="judul-post"></label>
<label>Kategori<select name="category_id"><option value="">— Tanpa kategori —</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $post->category_id ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select></label>
<label>Ringkasan<textarea name="excerpt" rows="2">{{ old('excerpt', $post->excerpt ?? '') }}</textarea></label>
<label>Isi*<textarea name="body" rows="10" required>{{ old('body', $post->body ?? '') }}</textarea></label>
<div><p class="meta">Tags (boleh banyak)</p><div class="checks">@foreach($tags as $t)<label><input type="checkbox" name="tags[]" value="{{ $t->id }}" @checked(in_array($t->id, old('tags', isset($post) ? $post->tags->pluck('id')->all() : [])))> {{ $t->name }}</label>@endforeach</div></div>
<label>Thumbnail (jpg/png/gif/webp, maks 2MB)<input type="file" name="thumbnail" accept="image/*" data-max-mb="2"></label>
<script>
(function (label) {
    var input = label ? label.querySelector('input[type="file"]') : null;
    if (!input) return;
    input.addEventListener('change', function () {
        var max = parseInt(input.dataset.maxMb || '2', 10) * 1024 * 1024;
        if (input.files && input.files[0] && input.files[0].size > max) {
            alert('Ukuran gambar melebihi ' + input.dataset.maxMb + 'MB — pilih berkas yang lebih kecil agar bisa diunggah.');
            input.value = '';
        }
    });
})(document.currentScript ? document.currentScript.previousElementSibling : null);
</script>
@if($isEdit && $post->thumbnailUrl())<p><img src="{{ $post->thumbnailUrl() }}" width="200" style="border-radius:.5rem"><br><label style="display:inline-flex;gap:.4rem;align-items:center;width:auto"><input type="checkbox" name="remove_thumbnail" value="1" style="width:auto"> Hapus thumbnail</label></p>@endif
<label style="display:inline-flex;gap:.4rem;align-items:center;width:auto"><input type="checkbox" name="is_published" value="1" style="width:auto" @checked(old('is_published', $post->is_published ?? true))> Published</label>
<p><button class="btn primary" type="submit">{{ $isEdit ? 'Perbarui' : 'Terbitkan' }}</button>@if(!$isEdit) @include('layouts._autofill', ['presets' => [
    ['fields' => ['title' => 'Cara Aman Menyimpan Rahasia Aplikasi', 'category_id' => '__RANDOM_OPTION__', 'excerpt' => 'Jangan taruh API key di kode: pakai .env, config, dan secrets manager.', 'body' => "Kunci API, token, dan kredensial basis data tidak boleh tertulis di kode sumber atau commit Git.\n\nTaruh di .env, baca via config(), dan putar ulang (rotate) berkala.\n\nUntuk production, gunakan secrets manager bawaan platform hosting Anda."], 'checks' => ['tags[]' => ['take' => 2]]],
    ['fields' => ['title' => 'Rate Limit Sederhana Tanpa Paket Tambahan', 'category_id' => '__RANDOM_OPTION__', 'excerpt' => 'Throttle bawaan Laravel untuk menahan brute force dan spam API.', 'body' => "Middleware throttle membatasi jumlah request per menit per IP atau per user.\n\nPasang di rute login dan API publik agar serangan brute force melambat drastis.\n\nKombinasikan dengan pencatatan agar pola serangan terbaca di dashboard."] , 'checks' => ['tags[]' => ['take' => 2]]],
    ['fields' => ['title' => 'Backup Terenkripsi Sebelum Migrasi Server', 'category_id' => '__RANDOM_OPTION__', 'excerpt' => 'Checklist backup: database, storage, dan .env sebelum pindah VPS.', 'body' => "Sebelum migrasi, cadangkan database, folder storage, dan berkas .env ke lokasi terenkripsi.\n\nUji restore di server staging sebelum mematikan server lama.\n\nCatat checksum tiap arsip agar korupsi berkas langsung ketahuan."], 'checks' => ['tags[]' => ['take' => 2]]],
    ['fields' => ['title' => 'Aktifkan 2FA untuk Akun Admin Panel', 'category_id' => '__RANDOM_OPTION__', 'excerpt' => 'Kata sandi saja tidak cukup untuk akun berkuasa penuh.', 'body' => "Akun admin adalah target utama pencurian kredensial.\n\nAktifkan autentikasi dua faktor dan simpan kode pemulihan di tempat aman.\n\nTinjau sesi aktif berkala dan cabut sesi dari perangkat yang tidak dikenal."], 'checks' => ['tags[]' => ['take' => 2]]],
]]) @endif <a href="{{ route('posts.index') }}">Batal</a></p>
</form>
</div>
