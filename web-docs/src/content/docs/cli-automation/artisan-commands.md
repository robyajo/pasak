---
title: "Referensi Perintah Artisan CLI"
description: "Paket menyertakan kumpulan perintah Artisan CLI untuk administrasi, otomasi pemindaian, dan pemeliharaan server secara mandiri."
sidebar:
  order: 1
---

Paket menyertakan kumpulan perintah Artisan CLI untuk administrasi, otomasi pemindaian, dan pemeliharaan server secara mandiri.

---

## 1. `pasak:install`

Wizard instalasi terpandu bilingual (Indonesia default / English via `--lang=en`): pilihan mode API-only (`--api-only`), tawaran dashboard Blade murni, cek kelengkapan lingkungan, tawaran migrasi langsung, dan saran langkah berikutnya beserta tautan dokumentasi.

Menginisialisasi paket, mempublikasikan konfigurasi, migrasi database, konfigurasi Nginx hardened WAF, serta menerapkan aturan **Apache `.htaccess` Hardened** di folder `public/`.

```bash
php artisan pasak:install [options]
```

### Mekanisme Pembaruan `.htaccess`:

- Jika `public/.htaccess` belum ada: installer membuat berkas baru dengan rewrite standar Laravel + blok proteksi hardening.
- Jika `public/.htaccess` sudah ada: installer membuat berkas backup otomatis (`.htaccess.backup-YYYYMMDD_HHMMSS`) dan menambahkan blok hardening keamanan di bagian bawah berkas tanpa merusak aturan kustom pengguna.
- Jika sudah terpasang: installer mendeteksi signature hardening dan tidak menduplikasi aturan.

### Mekanisme Penyematan Variabel `.env` & `.env.example`:

- Installer secara otomatis memeriksa apakah blok konfigurasi `PASAK_*` sudah ada di `.env` dan `.env.example`.
- Jika belum ada, installer menyematkan blok variabel lingkungan lengkap dengan komentar dokumentasi berbahasa Indonesia di akhir berkas.
- Opsi `--without-env` dapat digunakan untuk melewati langkah ini jika Anda ingin mengonfigurasi variabel lingkungan secara manual.

### Opsi:

- `--migrate`: Menjalankan migrasi database dan menyemai pengaturan bawaan ke `pasak_settings` secara otomatis (cocok untuk alur otomatis/CI).
- `--force`: Menimpa seluruh berkas konfigurasi, migrasi, `nginx.conf`, dan `public/.htaccess` dengan template bawaan.
- `--lang=`: Bahasa wizard (`id` | `en`).
- `--api-only`: Jalan pintas — API ya, Web tidak (inti + keamanan saja, tanpa halaman/dashboard).
- `--without-api`: Melewatkan pemasangan dukungan API.
- `--without-web`: Melewatkan pemasangan dashboard web.
- `--with-dashboard` / `--with-plain` / `--with-blade`: Dashboard Blade murni (semua aplikasi Laravel, tanpa dependensi frontend).
- `--without-nginx`: Melewatkan publikasi berkas `nginx.conf`.
- `--without-htaccess`: Melewatkan publikasi atau penambahan aturan pada `public/.htaccess`.
- `--with-htaccess`: Memaksa pembaruan berkas `public/.htaccess` dengan aturan hardening keamanan.
- `--without-env`: Melewatkan penyematan variabel konfigurasi ke berkas `.env` dan `.env.example`.

---

## 2. `pasak:scan-logs`

Memindai berkas log web server (Apache/Nginx) secara streaming, mendeteksi serangan yang ditolak di level web server, mengimpor ke tabel log keamanan, atau langsung memblokir IP penyerang zero-tolerance.

```bash
php artisan pasak:scan-logs [options]
```

### Opsi:

- `--file=*`: Berkas log yang diperiksa (dapat ditentukan lebih dari satu kali, misal: `--file=/var/log/nginx/access.log --file=/var/log/nginx/access.log.1`).
- `--ip=`: Memfilter analisis hanya untuk satu alamat IP penyerang tertentu.
- `--since=`: Membatasi baris sejak waktu tertentu (misal: `"2 days ago"` atau `"2026-10-01 00:00"`).
- `--until=`: Membatasi baris hingga waktu tertentu.
- `--min-level=`: Saring level ancaman minimal (`low`, `medium`, `high`, `critical`).
- `--top=20`: Jumlah IP penyerang teratas yang ditampilkan di tabel ringkasan.
- `--samples`: Menampilkan contoh URI request yang mencurigakan untuk setiap IP.
- `--json`: Mengeluarkan hasil analisis dalam format JSON murni (cocok untuk pipeline SIEM / alerting).
- `--import`: Menyimpan temuan ke tabel database `pasak_logs`.
- `--block`: Memblokir otomatis IP yang memicu signature zero-tolerance.
- `--dry-run`: Mode simulasi: menampilkan apa saja yang akan diblokir/diimpor tanpa mengubah data apa pun di database.

### Contoh Skenario:

```bash
# 1. Analisis cepat 24 jam terakhir tanpa ubah database
php artisan pasak:scan-logs --since="24 hours ago" --samples

# 2. Tangkap penyerang dan blokir langsung di database
php artisan pasak:scan-logs --since="6 hours ago" --import --block
```

---

## 3. `pasak:baseline`

Membuat, mengaudit, atau menghapus baseline hash SHA-256 integritas berkas aplikasi.

```bash
php artisan pasak:baseline [options]
```

### Opsi:

- _(tanpa opsi)_: Membuat baseline baru jika belum ada, atau menampilkan ringkasan baseline yang tersimpan.
- `--check`: Membandingkan kondisi berkas saat ini dengan baseline tanpa menulis apa pun ke disk (mode audit).
- `--prune`: Menghapus berkas baseline yang tersimpan di `storage/app/security-baseline.json`.
- `--force`: Membuat baseline baru dan menimpa baseline lama tanpa meminta konfirmasi interaktif.

### Contoh Output Audit:

```text
Baseline SHA-256 dibuat: 2026-10-01 10:00:00 (1.420 berkas)
Status Audit:
  - Berkas dimodifikasi : 0
  - Berkas hilang       : 0
  - Berkas baru         : 1 (public/uploads/backdoor.php) [PERINGATAN!]
```

---

## 4. `pasak:unblock-ip`

Membuka blokir alamat IP yang terkena karantina WAF. Berfungsi sebagai **Escape Hatch** darurat jika administrator tidak sengaja memblokir IP mereka sendiri.

```bash
php artisan pasak:unblock-ip [ip] [options]
```

### Argumen & Opsi:

- `ip`: Alamat IP yang ingin dibuka blokirnya (misal: `198.51.100.50`).
- `--all`: Membuka blokir seluruh IP yang sedang aktif sekaligus.
- `--list`: Menampilkan daftar seluruh IP yang sedang diblokir aktif saat ini.

### Contoh:

```bash
# Buka blokir satu IP
php artisan pasak:unblock-ip 198.51.100.50

# Lihat siapa saja yang sedang diblokir
php artisan pasak:unblock-ip --list

# Buka seluruh blokir saat insiden darurat
php artisan pasak:unblock-ip --all
```

---

## 5. `pasak:prune-logs`

Membersihkan riwayat log keamanan yang telah melewati batas retensi waktu.

```bash
php artisan pasak:prune-logs [options]
```

### Opsi:

- `--days=`: Mengesampingkan konfigurasi retensi hari (default: `PASAK_LOG_RETENTION_DAYS`, biasanya 90 hari).

### Contoh:

```bash
# Bersihkan log yang lebih tua dari 30 hari
php artisan pasak:prune-logs --days=30
```

---

## 6. `pasak:purge-injected-data`

Mendeteksi dan menghapus residu payload pentest atau injeksi yang tersimpan di dalam tabel aplikasi (misal `{{7*7}}`, `.htaccess`, `../../../public/`).

```bash
php artisan pasak:purge-injected-data [options]
```

### Opsi:

- _(tanpa `--force`)_: Menjalankan mode simulasi (hanya mendeteksi dan menampilkan daftar baris data yang tercemar tanpa menghapusnya).
- `--force`: Menghapus baris data tercemar yang ditemukan.
- `--marker=*`: Marker teks spesifik yang dicari (dapat diulang, default: canary pentest umum).
- `--model=*`: Membatasi pencarian pada model Eloquent tertentu.

## 7. `pasak:upgrade`

Memperbarui paket via Composer lalu menyinkronkan migrasi, rute kustom, dan tampilan dashboard **tanpa menimpa kustomisasi host** (sinkronisasi paksa mencadangkan dulu ke `*.backup-*`).

```bash
php artisan pasak:upgrade --check
php artisan pasak:upgrade --force --no-composer --no-migrate
```

### Opsi:

- `--check`: Hanya memeriksa versi terbaru tanpa mengubah apa pun.
- `--force`: Jalankan penuh meskipun sudah versi terbaru.
- `--no-composer`: Lewati `composer update` (sinkronisasi aset lokal saja).
- `--no-migrate`: Lewati `php artisan migrate`.
- `--sync-routes` / `--sync-views`: Timpa berkas rute/tampilan setelah mencadangkan.

## 8. `pasak:uninstall`

Mencopot seluruh jejak paket dari aplikasi host tanpa merusaknya: menghapus 7 tabel Pasak (+ record migrasinya), rute publikasi, middleware, trait User, variabel `PASAK_*`, mengembalikan `.htaccess` dari cadangan, menghapus berkas publikasi, dan menjalankan `composer remove`.

```bash
php artisan pasak:uninstall --force
```

### Opsi:

- `--force`: Jalankan tanpa konfirmasi interaktif.
- `--keep-tables`: Pertahankan tabel database.
- `--keep-env`: Pertahankan variabel `.env`.

## 9. `pasak:secret`

Membuat atau membuat ulang `PASAK_DASHBOARD_SECRET` pada berkas `.env`. Rahasia ini menandatangani token URL dashboard acak per aplikasi; `.env.example` sengaja dibiarkan kosong agar nilai rahasia tidak ikut ter-commit.

Jalankan sekali setelah deploy atau `git clone` di server baru, lalu bersihkan cache konfigurasi:

```bash
php artisan pasak:secret
php artisan config:clear
```

### Opsi:

- `--force`: Buat ulang (regenerate) meskipun secret sudah terisi. Token dashboard lama otomatis ditandatangani ulang saat pengguna membuka `/pasak` (tidak ada 404).
- `--show`: Tampilkan nilai secret aktif di keluaran terminal.
- `--path=`: Berkas env tujuan selain `.env` (mis. `.env.production`).
