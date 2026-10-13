@extends('layouts.app')
@section('title', 'Masuk')
@section('content')
<div class="card" style="max-width:26rem;margin:2rem auto">
<h1 style="margin-top:0">🔑 Masuk</h1>
<div class="card" style="background:#0d1420;margin-bottom:1rem">
<p class="meta" style="margin:0 0 .5rem">Lab lokal — kredensial admin demo sudah terisi otomatis:</p>
<p style="margin:0"><code>admin@demo.test</code> / <code>password</code></p>
<p style="margin:.5rem 0 0"><button class="btn" type="button" onclick="fillAdmin()">🔧 Isi otomatis admin</button></p>
</div>
<form method="POST" action="{{ route('login') }}">@csrf
<label>Email<input type="email" id="loginEmail" name="email" value="{{ old('email', 'admin@demo.test') }}" required autofocus></label>
<label>Kata sandi<input type="password" id="loginPassword" name="password" value="password" required></label>
<label style="display:inline-flex;gap:.4rem;align-items:center;width:auto"><input type="checkbox" name="remember" value="1" style="width:auto"> Ingat saya</label>
<p><button class="btn primary" type="submit" style="width:100%">Masuk</button></p>
</form>
<p class="meta">Belum punya akun? <a href="{{ route('register') }}">Daftar →</a></p>
</div>
<script>
function fillAdmin(){
document.getElementById('loginEmail').value='admin@demo.test';
document.getElementById('loginPassword').value='password';
}
</script>
@endsection
