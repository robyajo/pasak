---
name: pasak-sandbox
description: "Use whenever installing, updating, or uninstalling the robyajo/pasak package in this test sandbox app (ring/pasak-yes). Covers the install-package.sh, update-package.sh, and uninstall-package.sh helper scripts, the full test cycle (uninstall && install), path-repository composer quirks (@dev flag), and post-run verification. Activate before running or modifying any of those scripts."
license: MIT
metadata:
  author: pasak-lab
---

# Pasak Sandbox (ring/pasak-yes)

App ini adalah sandbox pengujian paket `robyajo/pasak` (sumber: `../../`, via
Composer path-repository symlink — kode PHP paket selalu live, tanpa unduh
packagist). Tiga skrip helper mengatur siklus hidup paket. Jangan jalankan
perintah `pasak:*` / `composer require|remove` manual bila skrip sudah
mencakupnya — gunakan skrip agar alur konsisten dan terverifikasi.

## Skrip & Kapan Dipakai

| Skrip | Tugas | Kapan |
| --- | --- | --- |
| `./install-package.sh` | `composer require` + `pasak:install --force --lang=id --with-dashboard` + `migrate --force` + `optimize:clear` | Pasang awal / pasang ulang setelah uninstall |
| `./update-package.sh` | `pasak:upgrade --force --no-composer --sync-views --sync-routes` + `optimize:clear` | Sinkron aset ter-publish setelah kode paket berubah (view, rute, migrasi, config) |
| `./uninstall-package.sh` | `pasak:uninstall --force` + `php -l bootstrap/app.php` + `migrate --force` + `composer remove` (fallback) + `composer install` + `optimize:clear` | Copot total untuk uji bersih |

Siklus uji standar:

```bash
./uninstall-package.sh && ./install-package.sh
```

## Aturan Wajib

1. **Selalu teruskan `--no-interaction`** ke perintah artisan/composer di skrip
   (sandbox harus bisa jalan tanpa input).
2. **`composer require` wajib pakai `@dev`** (`composer require "robyajo/pasak:@dev"`)
   karena `minimum-stability: stable` — tanpa ini Composer menolak path-repo
   ("packages from the higher priority repository ... not installable").
3. **`update-package.sh` tidak menyentuh Composer** (`--no-composer`): kode paket
   sudah live via symlink; yang disinkron hanya aset ter-publish. Kustomisasi
   host dicadangkan otomatis (`*.backup-*`) sebelum ditimpa.
4. **`uninstall-package.sh` harus idempotent**: lewati `pasak:uninstall` bila
   perintah tidak terdaftar; `composer remove` hanya sebagai fallback (uninstaller
   sudah menjalankannya sendiri).
5. **Jangan hapus `shycron-package.sh` tanpa persetujuan user** (skrip lama,
   sudah digantikan `update-package.sh`).

## Verifikasi Setelah Jalan

- Install: `php -l bootstrap/app.php` bersih, `grep -c "^PASAK_" .env` > 0,
  `route:list --path=pasak` terdaftar, tabel `blocked_ips` ada.
- Uninstall: sisa `PASAK_*` di `.env`/`.env.example` NOL, tidak ada referensi
  `Internal\Pasak` / `HasSecurityRelations` di `routes/ config/ resources/
  app/ bootstrap/`, tidak ada sisa `*.backup-*`, app boot normal.
- Update: view ter-publish sama dengan `stubs/` paket, cache bersih
  (`view:clear` + `config:clear` tercakup `optimize:clear`).

## Data Demo Dashboard

`php artisan db:seed --class=PasakDemoSeeder` mengisi otomatis SETIAP CRUD
dashboard (log ancaman, IP blokir/allowlist, login attempt, tiket banding,
sesi, IP tepercaya). Idempoten, aman dijalankan ulang. Terdaftar di
`DatabaseSeeder` sehingga ikut `php artisan db:seed` penuh.

## Batasan

- Skrip ini KHUSUS sandbox `ring/pasak-yes`. Jangan terapkan pola
  `--sync-views/--sync-routes` (menimpa kustomisasi) ke app production sungguhan.
- Jangan ubah dependensi app (`composer.json`) tanpa persetujuan user.
