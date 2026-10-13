@extends('layouts.app')
@section('title', 'Ubah Kontak')
@section('content')
<div class="hero"><h1>✏️ Ubah Kontak</h1><p>{{ $contact->name }}</p></div>
<div class="card" style="max-width:36rem">
<form method="POST" action="{{ route('contacts.update', $contact) }}" enctype="multipart/form-data">
@include('contacts._form', ['submitLabel' => 'Perbarui'])
</form>
</div>
@endsection
