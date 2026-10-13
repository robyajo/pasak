<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Blog') — Pasak-No</title>
<style>
*{box-sizing:border-box}body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#0b0f14;color:#e6edf3;line-height:1.6}
a{color:#60a5fa;text-decoration:none}a:hover{text-decoration:underline}
/* Navbar blog */
.navbar{position:sticky;top:0;z-index:40;background:rgba(13,20,32,.95);backdrop-filter:blur(6px);border-bottom:1px solid #1f2937}
.navbar .inner{max-width:72rem;margin:0 auto;padding:.8rem 1.5rem;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap}
.brand{font-weight:800;font-size:1.1rem;color:#fff;text-decoration:none}
.brand:hover{text-decoration:none}
.navlinks{display:flex;gap:.25rem;flex-wrap:wrap;margin-left:auto;align-items:center}
.navlinks a{padding:.4rem .8rem;border-radius:.5rem;color:#e6edf3;font-size:.9rem}
.navlinks a:hover{background:#1f2937;text-decoration:none}
.navlinks a.active{background:#1f2937;font-weight:600}
.nav-auth{display:flex;gap:.5rem;align-items:center;margin-left:.5rem}
.hamburger{display:none;background:none;border:1px solid #374151;color:#fff;border-radius:.375rem;padding:.35rem .6rem;cursor:pointer;font-size:1rem;margin-left:auto}
/* Konten blog */
.container{max-width:72rem;margin:0 auto;padding:2rem 1.5rem 4rem}
.hero{margin-bottom:2rem}.hero h1{font-size:2rem;margin:0 0 .25rem}.hero p{color:#9ca3af;margin:0}
.card{background:#111827;border:1px solid #1f2937;border-radius:.75rem;padding:1.25rem;margin-bottom:1.25rem}
.grid-posts{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem}
.post-card{background:#111827;border:1px solid #1f2937;border-radius:.75rem;overflow:hidden;display:flex;flex-direction:column;transition:border-color .15s}
.post-card:hover{border-color:#374151}
.post-card img.cover{width:100%;height:170px;object-fit:cover;background:#0d1420}
.post-card .body{padding:1rem 1.1rem;display:flex;flex-direction:column;gap:.5rem;flex:1}
.post-card h2{font-size:1.05rem;margin:0;line-height:1.4}
.post-card h2 a{color:#fff}.post-card p.ex{font-size:.87rem;color:#9ca3af;margin:0}
.meta{font-size:.78rem;color:#9ca3af}
.badge{display:inline-block;font-size:.72rem;background:#1f2937;border:1px solid #374151;border-radius:999px;padding:.1rem .55rem;margin:.1rem;color:#e6edf3}
.badge.cat{background:#052e16;border-color:#166534;color:#bbf7d0}
.article{max-width:46rem;margin:0 auto}
.article h1{font-size:2rem;line-height:1.3;margin:0 0 .5rem}
.article .lead{font-size:1.05rem;color:#9ca3af;border-left:3px solid #374151;padding-left:1rem;margin:1.25rem 0}
.article .content{font-size:1rem;line-height:1.8}
.article img.hero-img{width:100%;border-radius:.75rem;margin:1.25rem 0}
.btn{border:1px solid #374151;background:#1f2937;color:#fff;border-radius:.375rem;padding:.4rem .9rem;cursor:pointer;text-decoration:none;display:inline-block;font-size:.9rem}
.btn:hover{background:#374151;text-decoration:none}.btn.danger{background:#7f1d1d;border-color:#991b1b}.btn.primary{background:#166534;border-color:#15803d}
input,select,textarea{width:100%;background:#0d1420;border:1px solid #374151;color:#e6edf3;border-radius:.375rem;padding:.5rem;font-size:.9rem}
label{font-size:.85rem;display:block;margin-bottom:.75rem}.ok{color:#22c55e}.err{color:#ef4444}
.checks{display:flex;gap:.4rem;flex-wrap:wrap}.checks label{border:1px solid #374151;border-radius:.5rem;padding:.35rem .6rem;display:inline-flex;gap:.4rem;align-items:center;width:auto;margin-bottom:.4rem}
.checks input{width:auto}
table{width:100%;font-size:.85rem;border-collapse:collapse}th,td{padding:.5rem;border-bottom:1px solid #1f2937;text-align:left;vertical-align:top}
code{background:#1f2937;padding:.1rem .35rem;border-radius:.25rem;font-size:.8rem}
.footer{border-top:1px solid #1f2937;padding:1.5rem;text-align:center;color:#9ca3af;font-size:.85rem}
/* Floating button kerentanan */
.vuln-fab{position:fixed;right:1.25rem;bottom:1.25rem;z-index:60;display:flex;align-items:center;gap:.5rem;background:#7f1d1d;border:1px solid #ef4444;color:#fff;font-weight:700;font-size:.85rem;border-radius:999px;padding:.7rem 1.1rem;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,.5)}
.vuln-fab:hover{background:#991b1b}
.vuln-fab .dot{display:inline-block;min-width:1.4rem;text-align:center;background:#ef4444;border-radius:999px;font-size:.75rem;padding:.05rem .35rem}
/* Sheet setengah layar */
.vuln-backdrop{position:fixed;inset:0;z-index:70;background:rgba(0,0,0,.6);opacity:0;pointer-events:none;transition:opacity .2s}
.vuln-backdrop.open{opacity:1;pointer-events:auto}
.vuln-sheet{position:fixed;top:0;right:0;bottom:0;z-index:71;width:min(50vw,720px);background:#0d1420;border-left:1px solid #374151;transform:translateX(100%);transition:transform .25s ease;display:flex;flex-direction:column}
.vuln-sheet.open{transform:translateX(0)}
.vuln-sheet header{display:flex;align-items:center;gap:.75rem;padding:1rem 1.25rem;border-bottom:1px solid #1f2937;flex-shrink:0}
.vuln-sheet header h2{font-size:1rem;margin:0;flex:1}
.vuln-sheet .sheet-body{overflow-y:auto;padding:1rem 1.25rem}
.vuln-item{border:1px solid #1f2937;border-radius:.5rem;padding:.75rem;margin-bottom:.6rem;background:#111827}
.vuln-item b{font-size:.87rem}.vuln-item p{font-size:.8rem;color:#9ca3af;margin:.25rem 0 0}
.sev{display:inline-block;font-size:.7rem;font-weight:700;border-radius:.25rem;padding:.1rem .45rem;margin-left:.5rem}
.sev.critical{background:#7f1d1d;color:#fecaca}.sev.high{background:#78350d;color:#fde68a}.sev.medium{background:#1e3a8a;color:#bfdbff}
/* Responsif */
@media (max-width:768px){
.navbar .inner{padding:.7rem 1rem;gap:.75rem}
.hamburger{display:block}
.navlinks{display:none;width:100%;flex-direction:column;align-items:stretch;margin-left:0}
.navlinks.open{display:flex}
.navlinks a{padding:.6rem .8rem}
.nav-auth{margin-left:0;flex-direction:column;align-items:stretch}
.nav-auth .btn{text-align:center}
.vuln-sheet{width:100vw}
.hero h1{font-size:1.5rem}
.article h1{font-size:1.5rem}
.container{padding:1.25rem 1rem 3rem}
.vuln-fab{right:.75rem;bottom:.75rem;padding:.6rem .9rem;font-size:.8rem}
table{font-size:.78rem}
}
</style>
</head>
<body>
<nav class="navbar"><div class="inner">
<a class="brand" href="{{ route('posts.index') }}">📰 Blog Pasak-No</a>
<button class="hamburger" id="navToggle" type="button" aria-label="Menu">☰</button>
<div class="navlinks" id="navLinks">
<a href="{{ route('posts.index') }}" class="{{ request()->routeIs('posts.*') ? 'active' : '' }}">Beranda</a>
@auth<a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>@endauth
<a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active' : '' }}">Kategori</a>
<a href="{{ route('tags.index') }}" class="{{ request()->routeIs('tags.*') ? 'active' : '' }}">Tags</a>
<a href="{{ route('contacts.index') }}" class="{{ request()->routeIs('contacts.*') ? 'active' : '' }}">Kontak</a>
@if(Route::has('pasak.dashboard.overview'))<a href="{{ route('pasak.dashboard.overview') }}" class="{{ request()->routeIs('pasak.dashboard.*') ? 'active' : '' }}">🛡️ Dasbor Pasak</a>@endif
<span class="nav-auth">
@auth
<span class="meta">👤 {{ auth()->user()->name }}</span>
<form method="POST" action="{{ route('logout') }}" style="margin:0">@csrf<button class="btn" type="submit">Keluar</button></form>
@else
<a class="btn" href="{{ route('login') }}">Masuk</a>
<a class="btn primary" href="{{ route('register') }}">Daftar</a>
@endauth
</span>
</div>
</div></nav>
<script>(function(){var t=document.getElementById('navToggle'),l=document.getElementById('navLinks');if(t&&l){t.addEventListener('click',function(){l.classList.toggle('open');});}})();</script>
<main class="container">
@if(session('status'))<div class="card ok">{{ session('status') }}</div>@endif
@if($errors->any())<div class="card err"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@yield('content')
</main>
<footer class="footer">Blog demo lab lokal — dilindungi <b>Pasak (Bulwark) WAF</b>@if(Route::has('pasak.dashboard.overview')) · <a href="{{ route('pasak.dashboard.overview') }}">Dasbor keamanan</a>@endif</footer>
@include('layouts._vuln-sheet')
</body>
</html>
