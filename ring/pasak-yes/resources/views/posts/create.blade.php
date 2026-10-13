@extends('layouts.app')
@section('title', 'Tulis Post')
@section('content')
<div class="hero"><h1>✍️ Tulis Post</h1><p>Terbitkan tulisan baru ke blog.</p></div>
@include('posts._form')
@endsection
