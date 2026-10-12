# Rilis 2026-10-12 — Fondasi & lab dua sisi

## Lab pengujian (`ring/`)
- `ring/app-waf/` — konsol uji (pindahan utuh berikut semua kerja sebelumnya).
- `ring/pasak-no/` (`:8000`) — Laravel murni + harness demo: `trustProxies`,
  middleware `LogWafHits`, endpoint `/upload` sengaja rentan, beranda
  "Status Keamanan — TIDAK AMAN" + Monitor Serangan Masuk live.
- `ring/pasak-yes/` (`:8001`) — Pasak terinstall via path-repo
  (`robyajo/pasak:@dev`), `pasak:install --lang=id`, migrate, mode blokir
  aktif (`INSTANT_BLOCK`, `BLOCK_SUSPICIOUS`, `AUTO_BLOCK`).
- `/ring` masuk `.gitignore` + `export-ignore` (tak ikut rilis paket).

## Tab "⚠️ Tanpa Pasak = Jebol" (awal)
- 8 rekomendasi serangan 100% lolos tanpa Pasak + badge LOLOS/DIBLOKIR.
- Kolom URL target (default `http://localhost:8000`), badge method GET/POST.
- Tombol "Buktikan" per kartu: dampak + bukti dari body respons asli.
- Panel verifikasi skor X/8 + saran tindak lanjut.

## Demo & konten (pasak-no/pasak-yes)
- Seeder user (`admin@demo.test`/`password` + 5 factory) + CRUD kontak
  lengkap (migrasi, model, controller, Blade, avatar upload 2MB).
- Endpoint `/shell/{name}` (lab saja): eksekusi berkas unggahan (?cmd=)
  untuk bukti RCE — terverifikasi `uid=1000…`, curi `.env`, dump 6 users.

## Verifikasi akhir hari
- Kontras penuh: XSS/traversal/AKIA/wso/upload → `:8000` lolos & masuk disk,
  `:8001` semua **403**.
