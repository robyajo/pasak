@extends('layouts.app')
@section('title', 'Ubah Post')
@section('content')
<div class="hero"><h1>✏️ Ubah Post</h1><p>{{ $post->title }}</p></div>
@include('posts._form', ['post' => $post])
@endsection
