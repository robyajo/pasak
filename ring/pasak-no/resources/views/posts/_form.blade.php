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
<label>Thumbnail (jpg/png/gif/webp, maks 2MB)<input type="file" name="thumbnail" accept="image/*"></label>
@if($isEdit && $post->thumbnailUrl())<p><img src="{{ $post->thumbnailUrl() }}" width="200" style="border-radius:.5rem"><br><label style="display:inline-flex;gap:.4rem;align-items:center;width:auto"><input type="checkbox" name="remove_thumbnail" value="1" style="width:auto"> Hapus thumbnail</label></p>@endif
<label style="display:inline-flex;gap:.4rem;align-items:center;width:auto"><input type="checkbox" name="is_published" value="1" style="width:auto" @checked(old('is_published', $post->is_published ?? true))> Published</label>
<p><button class="btn primary" type="submit">{{ $isEdit ? 'Perbarui' : 'Terbitkan' }}</button> <a href="{{ route('posts.index') }}">Batal</a></p>
</form>
</div>
