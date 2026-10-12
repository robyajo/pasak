# 03. Panduan Upgrade & Uninstall

Panduan menaikkan versi paket pada aplikasi yang sudah terinstall, plus cara mencopot bersih bila sudah tidak dibutuhkan.

---

## 1. Upgrade Bertahap

### Tahap 1 — Cadangkan (wajib di produksi)

```bash
php artisan backup:run   # bila memakai spatie/laravel-backup, atau:
mysqldump -u root -p nama_db > backup-sebelum-upgrade.sql
```

### Tahap 2 — Perbarui paket via Composer

```bash
composer update robyajo/pasak --with-all-dependencies
```

### Tahap 3 — Sinkronisasi komponen

```bash
php artisan pasak:upgrade
```

Perintah ini, berurutan: memeriksa versi terbaru → `composer update` (bila belum) → mempublish ulang migrasi → `php artisan migrate` → memigrasi berkas lama era `security.*` ke nama `pasak` (isi kustom dipertahankan) → menyalin nilai `.env` lama ke kunci baru → menyinkronkan rute/views **tanpa menimpa kustomisasi** → `optimize:clear`.

Varian berguna:

```bash
php artisan pasak:upgrade --check                      # hanya cek versi
php artisan pasak:upgrade --no-composer --no-migrate   # sinkronisasi aset lokal saja
php artisan pasak:upgrade --sync-routes                # timpa rute setelah mencadangkan (*.backup-*)
php artisan pasak:upgrade --sync-views                 # timpa tampilan setelah mencadangkan
```

### Tahap 4 — Verifikasi

```bash
php artisan pasak:baseline          # status integritas
php artisan route:list --name=pasak # rute terdaftar
curl -I 'https://domain-anda.test/?q=%7B%7B7*7%7D%7D'  # harapannya tercatat (403 hanya bila auto/instant-block aktif)
```

---

## 2. Apa yang Berubah? (baca sebelum upgrade)

- **`CHANGELOG.md`** di root repo paket merinci setiap rilis: fitur baru, perbaikan, dan perubahan breaking.
- **Aturan versi**: patch mentok `.10` lalu rollover ke minor (`v1.2.10` → `v1.3.0`); major (`v2.0.0`, …) hanya untuk perubahan besar yang diumumkan eksplisit.
- **Instalasi lama menyesuaikan otomatis**: berkas `routes/security*.php`, folder dashboard lama, `config/security.php`, dan nilai `SECURITY_*` dimigrasi ke standar `pasak` oleh `pasak:upgrade` tanpa menghapus kustomisasi. Yang tetap manual: isi `.htaccess` aktif dan kode kustom yang memanggil nama lama.

---

## 3. Uninstall Bersih

```bash
php artisan pasak:uninstall --force
```

Mencopot seluruh jejak (8 tabel + record migrasi, rute, middleware, trait User, `PASAK_*`, `.htaccess` dikembalikan dari cadangan, berkas publikasi) lalu menjalankan `composer remove`. Opsi `--keep-tables` / `--keep-env` untuk mempertahankan data.
