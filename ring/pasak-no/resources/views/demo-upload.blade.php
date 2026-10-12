<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Demo Upload Rentan — Bukti Webshell</title>
<style>body{font-family:system-ui,sans-serif;background:#0b0f14;color:#e6edf3;margin:0}.wrap{max-width:44rem;margin:0 auto;padding:2rem}a{color:#60a5fa}code{background:#1f2937;padding:.1rem .4rem;border-radius:.25rem}.card{background:#111827;border:1px solid #7f1d1d;border-radius:.75rem;padding:1.25rem;margin-bottom:1rem}li{margin:.3rem 0}</style>
</head>
<body>
<div class="wrap">
  <h1>📤 Demo Upload Rentan (sengaja tanpa validasi)</h1>
  <div class="card">
    <b>⚠️ Endpoint ini SENGAJA rentan</b> untuk keperluan audit: menerima <code>shell.php.jpg</code> berisi <code>&lt;?php</code> tanpa penolakan.
    Setelah Pasak + <code>pasak.block</code> aktif, request yang sama harus <b>403</b> dan tidak sampai ke sini.
  </div>
  <form method="POST" action="/upload" enctype="multipart/form-data">
    @csrf
    <input type="file" name="file" required>
    <button>Unggah</button>
  </form>
  <h2>Bukti: {{ count($files) }} file masuk ({{ request()->getHost() }})</h2>
  <ul>
  @forelse($files as $f)
    <li><a href="{{ $f['url'] }}" target="_blank">{{ $f['name'] }}</a> ({{ $f['size'] }} byte) — <a href="{{ $f['url'] }}?cmd=id" target="_blank">simulasi ?cmd=id</a></li>
  @empty
    <li>Belum ada file. Kirim dari app-waf tab “Tanpa Pasak = Jebol” → kartu Upload shell.php.jpg.</li>
  @endforelse
  </ul>
  <p><a href="/">← Kembali ke beranda</a></p>
</div>
</body>
</html>
