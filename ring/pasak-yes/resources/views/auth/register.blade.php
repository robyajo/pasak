@extends('layouts.app')
@section('title', 'Daftar')
@section('content')
<div class="card" style="max-width:26rem;margin:2rem auto">
<h1 style="margin-top:0">📝 Daftar</h1>
<div class="card" style="background:#0d1420;margin-bottom:1rem">
<p class="meta" style="margin:0 0 .5rem">Lab lokal — formulir sudah terisi otomatis, tinggal klik <b>Buat akun</b>:</p>
<p style="margin:0"><button class="btn" type="button" onclick="fillDemo()">🔧 Isi otomatis</button>
<button class="btn" type="button" onclick="fillRandom()">🎲 Acak</button></p>
</div>
<form method="POST" action="{{ route('register') }}">@csrf
<label>Nama<input type="text" id="regName" name="name" value="{{ old('name', 'Pengguna Demo') }}" required autofocus></label>
<label>Email<input type="email" id="regEmail" name="email" value="{{ old('email', 'demo@example.com') }}" required></label>
<label>Kata sandi (min 8 karakter)<input type="password" id="regPassword" name="password" value="password123" required></label>
<label>Ulangi kata sandi<input type="password" id="regPasswordConfirm" name="password_confirmation" value="password123" required></label>
<p><button class="btn primary" type="submit" style="width:100%">Buat akun</button></p>
</form>
<p class="meta">Sudah punya akun? <a href="{{ route('login') }}">Masuk →</a></p>
</div>
<script>
function fillDemo(){
document.getElementById('regName').value='Pengguna Demo';
document.getElementById('regEmail').value='demo@example.com';
document.getElementById('regPassword').value='password123';
document.getElementById('regPasswordConfirm').value='password123';
}
function fillRandom(){
var n=Math.floor(Math.random()*9000)+1000;
document.getElementById('regName').value='Pengguna '+n;
document.getElementById('regEmail').value='pengguna'+n+'@example.com';
document.getElementById('regPassword').value='password123';
document.getElementById('regPasswordConfirm').value='password123';
}
</script>
@endsection
