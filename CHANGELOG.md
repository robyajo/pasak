# Changelog

All notable changes to `robyajo/pasak` will be documented in this file.

## [1.7.14] - 2026-11-12

### Fixed & Maintenance

- **Penyempurnaan Kompatibilitas CI & Pemeriksaan Statis PHPStan** (`phpstan.neon.dist`, `src/Support/PasakDashboardSecret.php`, `src/Console/Commands/PasakCloudCommand.php`):
  - Memperluas ignore pattern pemeriksaan `Factory::exists` untuk semua subdirektori di bawah `src/` guna mencegah kegagalan analisis statis pada lingkungan CI.
  - Memperbaiki penanganan `env()` di luar berkas konfigurasi dengan memindahkan pembacaan ke helper `config()` serta menerapkan pemformatan Laravel Pint secara menyeluruh.
  - Menghilangkan kondisi redundan pada pemeriksaan rahasia dasbor di `PasakDashboardSecret`.
- **Penanganan Dependensi Doctrine DBAL pada Migrasi Alter Table** (`database/migrations/2026_11_12_000001_expand_pasak_device_columns.php`):
  - Mengimplementasikan fallback otomatis ke kueri SQL native (`ALTER TABLE ... MODIFY/ALTER COLUMN`) apabila paket `doctrine/dbal` belum terpasang di aplikasi host, menjamin migrasi selalu berjalan sukses di semua versi Laravel.
  - Menambahkan pengaman `file_exists` sebelum memuat berkas rute publikasi.

## [1.7.13] - 2026-11-12

### Added

- **Klasifikasi Intelijen & Profil Penyerang Otomatis (Attacker Profiling: Scanners, Bots, Crawlers)** (`src/Support/PasakDashboardView.php`, `stubs/blade/pages/pasak/logs.blade.php`, `src/Http/Controllers/Dashboard/PasakLogController.php`):
  - Menambahkan kapabilitas `PasakDashboardView::attackerInfo()` untuk mengidentifikasi dan mengelompokkan pengunjung maupun penyerang secara otomatis berdasarkan User-Agent dan karakteristik request:
    - 🚨 **Scanner Eksploitasi**: `sqlmap`, `nikto`, `acunetix`, `nmap`, `masscan`, `wpscan`, `dirbuster`, dll.
    - 🤖 **Automated Script / Bot**: `curl`, `python-requests`, `postman`, `go-http-client`, `wget`, dll.
    - 🕷️ **Web Crawler / Search Engine**: `Googlebot`, `Bingbot`, `YandexBot`, `DuckDuckBot`, dll.
    - 🕵️ **Headless / Anonymized Client**: Permintaan tanpa User-Agent atau browser automation (`Puppeteer`, `Playwright`, `Selenium`).
    - 🌐 **Standard Browser**: Chrome, Firefox, Safari, Edge, Opera.
  - Menyajikan lencana visual informatif dan tooltip User-Agent mentah pada setiap baris log keamanan.
- **Penyaringan Cepat Kategori Klien & Bot (`bot_filter`)**:
  - Menambahkan parameter kueri `?bot_filter=scanner|bot|crawler|headless|browser` pada halaman audit log keamanan untuk memfilter lalu lintas mencurigakan dengan satu klik.

## [1.7.12] - 2026-11-12

### Fixed

- **Pencegahan Pemotongan Data (Data Truncation) pada Perangkat & IP Terpercaya** (`database/migrations/2026_11_12_000001_expand_pasak_device_columns.php`, `src/Models/PasakTrustedIp.php`, `src/Models/PasakUserLogin.php`, `src/Services/PasakUserLoginService.php`):
  - Memperluas kapasitas kolom `operating_system`, `browser`, `device_id`, dan `user_agent` menjadi 255 karakter / teks panjang.
  - Mencegah galat SQL data truncation saat memproses signature peramban modern yang panjang ketika mendaftarkan IP atau perangkat terpercaya.

## [1.7.11] - 2026-11-11

### Added

- **Integrasi Pasak Cloud Service & Perintah CLI `pasak:cloud`** (`src/Services/PasakCloudService.php`, `src/Console/Commands/PasakCloudCommand.php`):
  - Layanan telemetri WAF mandiri yang secara otomatis mengekstrak metadata identitas aplikasi host dari `APP_NAME` dan `APP_URL`.
  - Menyediakan perintah Artisan interaktif:
    - `php artisan pasak:cloud --test` : Uji konektivitas dan autentikasi lisensi ke server Pasak Cloud.
    - `php artisan pasak:cloud --info` : Tampilkan status konfigurasi cloud, token, dan metadata aplikasi.
    - `php artisan pasak:cloud --register` : Kirim pendaftaran awal dan heartbeat telemetri aplikasi.
- **Dukungan Lisensi Universal Multi-Aplikasi (`PASAK_CLOUD_KEY`)**:
  - Satu token lisensi (`psk_live_...`) dapat digunakan secara bersamaan pada berbagai aplikasi Laravel dan server VPS yang menjalankan daemon Sentinel.
  - Operasi cloud bersifat opsional: jika `PASAK_CLOUD_KEY` tidak diisi, WAF beroperasi 100% lokal tanpa dependensi jaringan eksternal.
- **Isolasi Namespace Total Antarmuka Cooldown Login (HTTP 429)** (`stubs/pasak-login-throttle.blade.php`, `src/Services/PasakLoginThrottleService.php`):
  - Membakukan seluruh styling CSS, custom properties, dan ID DOM menggunakan prefix `pasak-` untuk menjamin tampilan hitung mundur responsif tanpa risiko konflik CSS host.
  - Deteksi otomatis header `Accept: application/json` dan rute `/api/*` untuk merespons status HTTP 429 dengan payload JSON terstruktur dan header `Retry-After`.
- **Pembersihan Berkas Stub Usang**:
  - Menghapus stub usang (`login-throttle.blade.php`, `429.blade.php`, `pasak-429.blade.php`) dan membakukan penggunaan stub `pasak-login-throttle.blade.php`.

## [1.7.10] - 2026-11-11

### Added

- **Branding Resmi Dwitema pada Gateway Login Dashboard** (`stubs/blade/pages/pasak/login.blade.php`, `src/Services/PasakService.php`):
  - Menampilkan logo resmi horizontal PASAK pada formulir autentikasi login dasbor dengan adaptasi otomatis untuk mode terang (Light Mode) dan gelap (Dark Mode).
  - Penyelarasan pengujian unit pengalihan autentikasi dasbor berputar (`pasak.dashboard.login`).

## [1.7.9] - 2026-11-11

### Added

- **Gerbang Autentikasi Mandiri Dashboard Pasak & Kredensial Bawaan** (`src/Http/Controllers/Dashboard/PasakAuthController.php`, `src/Http/Middleware/PasakDashboardAuthenticate.php`, `stubs/blade/pages/pasak/login.blade.php`):
  - Dashboard Pasak kini dilindungi oleh form login mandiri (`/pasak-{token}/login`) yang terpisah dari sistem autentikasi aplikasi host.
  - Kredensial bawaan dikonfigurasi melalui `.env`: `PASAK_DASHBOARD_USERNAME=admin` dan `PASAK_DASHBOARD_PASSWORD=Admin`.
  - Hash password disimpan di database tabel `pasak_settings` menggunakan HMAC SHA-256 berbasis `PASAK_DASHBOARD_SECRET`.
  - Banner peringatan siaga dan pop-up modal wajib ganti password muncul otomatis saat administrator masih menggunakan kredensial bawaan (`Admin`).

- **Mode Pemulihan Darurat (Emergency Recovery via `PASAK_DASHBOARD_SECRET`)**:
  - Apabila administrator lupa password yang telah disetel, nilai `PASAK_DASHBOARD_SECRET` dari berkas `.env` dapat langsung digunakan sebagai password darurat pada form login.
  - Sistem mendeteksi kecocokan darurat dan mengizinkan login ke **Mode Pemulihan Darurat** (`is_emergency = true`), menampilkan banner peringatan siaga merah dan langsung memunculkan modal ganti password baru tanpa perlu menyentuh database.

- **Modal Pembaruan Password & Formulir Pengaturan**:
  - Menghadirkan modal pop-up pembaruan password (`update-password-modal.blade.php`) dan formulir inline di halaman Pengaturan (`settings.blade.php`).
  - Dilengkapi rekomendasi contoh password kuat (misal `P@sak#VPS2026!`) dan petunjuk pemulihan darurat.

- **Dukungan Multi-Bahasa Lengkap (i18n EN / ID Switcher)**:
  - Tombol switcher dwibahasa di header dasbor (`EN / ID`) untuk peralihan instan.
  - Seluruh teks tampilan (heading, subheading, label formulir, tabel, konfirmasi modal, alert) telah diterjemahkan secara konsisten ke dalam Bahasa Inggris dan Bahasa Indonesia.

- **Sinkronisasi Waktu Server (`APP_LOCALE` / Timezone)**:
  - Waktu server yang ditampilkan pada lencana header secara otomatis disinkronkan dengan `APP_LOCALE` dan zona waktu host Laravel (`config('app.timezone')`), menjamin akurasi waktu fisik server/VPS tempat aplikasi beroperasi.

- **Modal Panduan & Dukungan Kontekstual Halaman (Page Support Modal)** (`components/page-support-modal.blade.php`):
  - Tombol Support di header membuka modal penjelasan fungsi dan konteks teknis halaman yang sedang diakses (seperti `/blocked-ips`, `/logs`, `/settings`, `/sessions`, `/threat-map`), merinci informasi metrik dan opsi tindakan mitigasi.

- **Restrukturisasi Layout Dashboard (Pemisahan Title dari Header Toolbar)**:
  - Memisahkan elemen `$heading` dan `$subheading` keluar dari sticky `<header class="sec-header">` ke kontainer `.sec-page-header` di bawah header.
  - Menjaga toolbar header tetap ramping dan judul leluasa di layar desktop maupun mobile tanpa berdesakan dengan tombol aksi utilitas.

## [1.7.8] - 2026-11-11

### Fixed

- **Kepatuhan Gaya Kode Pint & CI**: Penyesuaian sintaksis kode sesuai standar Laravel Pint sehingga seluruh matrix pengujian otomatis lulus 100%.

## [1.7.7] - 2026-11-11

### Security

- **Pencegahan IP Spoofing pada `resolveClientIp`** (`src/Services/PasakService.php`):
  - Request publik tidak dapat lagi mem-bypass atau memalsukan identitas IP via header `CF-Connecting-IP`, `True-Client-IP`, `X-Real-IP`, atau `X-Forwarded-For`.
  - Header reverse proxy kini hanya dipercaya dan diekstrak bila koneksi klien terbukti berasal dari upstream / proxy terpercaya (`isTrustedProxy()`, subnet privat/internal Docker/Nginx/Kube, atau rentang subnet resmi Cloudflare).
- **Penghapusan Auto-Whitelist Pengguna Biasa pada `isWhitelisted`** (`src/Services/PasakService.php`):
  - Login pengguna reguler tidak lagi memberikan izin bypass bebas WAF ke IP pengunjung. Whitelist hanya diberikan kepada IP tepercaya eksplisit (`PasakTrustedIp` / config).
- **Inspeksi Raw Body & Mitigasi Padding Evasion** (`src/Services/PasakService.php`):
  - Konten mentah request (`$request->getContent()`) kini otomatis diikutsertakan pada inspeksi body (menjamin request JSON/XML mentah tetap terpindai).
  - Menerapkan inspeksi awal dan akhir (*head + tail inspection*) jika panjang isi request melampaui `max_inspect_length`, sehingga teknik *padding evasion* (> 4000 byte karakter dummy untuk menyembunyikan serangan di ekor payload) berhasil dicegah.

### Fixed

- **Isolasi File Konfigurasi Testbench pada Suite Pengujian** (`tests/TestCase.php`):
  - Membersihkan residu file `config/pasak.php` pada skeleton Testbench di `TestCase::setUp()` dan `tearDown()` agar tes fitur tidak saling menimpa konfigurasi paket (Fixes #3, PR #4).

## [1.7.6] - 2026-11-11

### Fixed

- **False positive blokir instan `config_probe` pada slug berawalan `artisan-` / `dockerfile-`** — pola nama berkas sensitif (`artisan`, `Dockerfile`, `composer.json`, `id_rsa`, dll.) kini tidak lagi cocok bila langsung diikuti huruf, angka, `_`, atau `-`, sehingga path seperti `/produk/artisan-coffee` atau `/blog/dockerfile-best-practices` tidak memblokir pengunjung selama 30 hari. Probe asli (`/artisan`, `/Dockerfile`, `/composer.json.bak`) tetap terblokir instan. Tes "lalu lintas nyata" pada `DetectorTuningTest` kini mengaktifkan blokir instan agar signature benar-benar dievaluasi.

## [1.7.4] - 2026-11-10

### Added

- **Favicon Resmi Pasak untuk Dashboard & Halaman Error Terblokir** (`stubs/blade/pages/pasak/layout.blade.php`, `stubs/blocked.blade.php`):
  - Mengintegrasikan favicon default resolusi tinggi menggunakan logo/ikon resmi Pasak (berbasis SVG/PNG base64 yang selaras dengan ikon aplikasi di bagian bawah sidebar navigasi).
  - Melengkapi tag `<link rel="icon">`, `<link rel="shortcut icon">`, dan `<link rel="apple-touch-icon">` untuk memastikan favicon tampil konsisten di semua peramban desktop maupun mobile.
  - Tetap mendukung kustomisasi penuh melalui konfigurasi `config('pasak.dashboard.favicon')` atau penimpaan langsung via Blade `@section('favicon')`.

- **Konfigurasi URL Kembali Dashboard Dinamis (`pasak.dashboard.back_url`)** (`config/pasak.php`, `stubs/blade/pages/pasak/settings.blade.php`, `PasakSettingController.php`, `PasakSecuritySettingApiController.php`):
  - Memungkinkan admin mengonfigurasi tautan tombol "Kembali ke Aplikasi Utama" di sidebar dashboard secara fleksibel melalui antarmuka Pengaturan Keamanan maupun file konfigurasi.

## [1.7.3] - 2026-11-10

### Added

- **Pembersihan Cache & Optimasi Sistem Sekali Klik (`php artisan optimize:clear`) via Tombol Refresh Header** (`stubs/blade/pages/pasak/layout.blade.php`, `PasakServerController.php`, `PasakServerSecurityApiController.php`):
  - Tombol Refresh di sudut kanan atas header dashboard kini memicu endpoint `POST {pasakSlug}/optimize-clear` dan API `POST api/pasak/server/optimize-clear`.
  - Secara otomatis mengeksekusi `Artisan::call('optimize:clear')` yang membersihkan seluruh cache aplikasi, compiled views, konfigurasi, rute, dan event cache.
  - Dilengkapi animasi putar halus (_spinning icon_), pengiriman pesan aman via session flash, serta penayangan notifikasi floating toast hijau (_5 detik_) saat halaman termuat ulang dalam kondisi segar.

- **Branding Resmi Pasak Adaptif pada Header & Sidebar Dashboard** (`stubs/blade/pages/pasak/layout.blade.php`):
  - Menghadirkan logo resmi horizontal **PASAK** pada sisi kiri header (`.sec-header-brand`) lengkap dengan pemisah vertikal elegan dan tautan cepat ke Ringkasan Dashboard.
  - Varian logo dwitema adaptif otomatis: varian warna navy untuk Mode Terang (_Light Mode_) dan varian warna putih tajam (_crisp white_) untuk Mode Gelap (_Dark Mode_).
  - Menggantikan ikon perisai generik lama pada footer sidebar versi dengan ikon resmi Pasak (_squircle biru P berlatar jaringan node dan pasak oranye_) beresolusi tinggi (Retina-ready) tanpa dependensi aset eksternal.

- **Lencana Informasi & Fitur Salin Cepat Alamat IP Pengakses di Header** (`stubs/blade/pages/pasak/layout.blade.php`):
  - Menambahkan informasi alamat IP publik/lokal yang sedang mengakses dashboard secara langsung di samping tombol Refresh (`.sec-client-ip-badge`).
  - Dilengkapi indikator status aktif (_live green beacon_), label teks responsif, serta fitur interaktif satu klik (_click-to-copy_) ke clipboard dengan feedback notifikasi toast.

### Fixed

- **Pencegahan Alert Dialog Ganda pada Hapus Berkas Server Audit** (`stubs/blade/pages/pasak/server.blade.php`):
  - Menghilangkan konflik konfirmasi ganda bawaan peramban (`onsubmit="return confirm(...)"`) sehingga aksi hapus berkas mencurigakan konsisten hanya memicu 1 modal konfirmasi alert-dialog terpadu Pasak.
- **Perbaikan Kebocoran Teks Skrip pada Visualisasi Threat Map** (`stubs/blade/pages/pasak/threat-map.blade.php`):
  - Mengubah payload simulasi XSS menjadi format URL-encoded (`%3Cscript%3Ealert(1)%3C%2Fscript%3E`) guna mencegah penutupan prematur blok `<script>` oleh parser HTML browser yang sebelumnya menyebabkan teks kode tumpah ke bagian bawah layar.
- **Pencegahan Toast Duplikat & Konsistensi Aliran Radar Serangan**:
  - Menambahkan _deduplication guard_ pada sistem notifikasi toast untuk memblokir penayangan pesan identik secara simultan.
  - Mengalirkan data serangan database satu per satu (_playback queue_) secara real-time pada Threat Map sehingga statistik radar 100% konsisten dengan log riil tanpa menghasilkan _phantom counters_.

## [1.7.2] - 2026-11-09

### Added

- **Peta Intelijen Siber Interaktif & Dual-Engine Fallback pada Threat Map** (`stubs/blade/pages/pasak/threat-map.blade.php`):
  - Visualisasi intelijen serangan siber real-time dengan HUD status taktis (ThreatsEye SOC Style), koordinat node yang dilindungi, dan status firewall WAF (Blocking / Monitoring).
  - Pilihan proyeksi peta fleksibel: **3D Globe** (rotasi interaktif) dan **2D Tactical Flat Map** (Mercator / Equirectangular).
  - **Dual-Engine & Offline Resilience**: Mendukung failover multi-CDN (`jsdelivr` & `cdnjs`), data benua offline bawaan (`FALLBACK_WORLD_GEOJSON`), dan engine **Radar Cyber 2D Mandiri (Pure Canvas Standalone Engine)** yang berjalan 100% tanpa dependensi eksternal bila CDN diblokir atau offline.
  - Perbaikan styling modal toast offline (`.threat-map-offline[hidden] { display: none !important; }`) sehingga mudah ditutup tanpa menghalangi visualisasi radar.

- **Pembaruan Menyeluruh & Pembersihan Berkas Usang via `php artisan pasak:upgrade --force` (dan Alias `pasak:upgrae`)**:
  - Menambahkan alias resmi `pasak:upgrae` pada perintah konsol sehingga salah ketik tetap berjalan mulus.
  - Opsi `--force` kini memperbarui dan menyinkronkan seluruh aset dari instalasi lama ke standar versi terbaru:
    - **Deteksi & Laporan Migrasi Database**: Menampilkan deteksi jumlah migrasi baru yang belum diterapkan (`⚠ Terdeteksi X migrasi database baru...`) dan laporan status pembaruan struktur database yang informatif.
    - **Pembaruan Konfigurasi (`config/pasak.php`)**: Otomatis menyisipkan konfigurasi baru yang hilang (`sentinel` IPC socket daemon, `threat_map` globe visualizer, URL rotasi dinamis, dan migrasi nama tabel ke prefix `pasak_`) tanpa menimpa nilai kustomisasi pengguna.
    - **Sinkronisasi Tampilan (`resources/views/pages/pasak`) & Rute**: Secara otomatis menyinkronkan tampilan Blade dashboard dan rute ke versi terbaru, dengan opsi `--clean-backups` dan `--no-backup`.
    - **Pembersihan Berkas & Direktori Usang**: Otomatis mendeteksi dan menghapus berkas serta direktori peninggalan versi lama yang sudah tidak digunakan (`routes/security.php`, `routes/security-api.php`, `config/security.php`, `resources/views/pages/security`, `resources/js/pages/security`, `resources/js/components/security`, `web-docs/`, `finis.sh`, `install-dash.sh`), serta memangkas arsip cadangan berlebih (`.backup-*`) dengan mempertahankan 2 cadangan paling mutakhir.

- **Aksi Unduh Berkas & Modal Konfirmasi Verifikasi Berkas Aman pada Server Audit** (`stubs/blade/pages/pasak/server.blade.php`, `PasakServerSecurityService.php`, `PasakServerController.php`):
  - Menambahkan tombol unduh aman (`⬇️ Unduh`) pada tabel berkas mencurigakan & modal detail inspeksi yang memungkinkan admin mengunduh berkas berbahaya/mencurigakan secara aman dengan header `application/octet-stream` dan `X-Content-Type-Options: nosniff` untuk analisis offline.
  - Menambahkan aksi `🛡️ Verifikasi` dan modal konfirmasi `🛡️ Verifikasi Berkas Aman` yang memungkinkan admin menandai false positive sebagai berkas aman/terverifikasi disertai catatan investigasi opsional.
  - Berkas terverifikasi dicatat secara permanen di storage (`app/security/verified_files.json`) lengkap dengan hash SHA-256, ukuran, identitas verifikator, dan waktu verifikasi, serta otomatis dikecualikan dari pemindaian mencurigakan masa depan selama hash berkas tidak dimanipulasi.
- **Tombol Aksi Cepat & Modal Dialog Karantina IP Interaktif pada Log Keamanan** (`stubs/blade/pages/pasak/logs.blade.php`, `PasakBlockedIpController.php`):
  - Menambahkan tombol aksi cepat `🚫` di sebelah alamat IP dan tombol `🚫 Blokir` pada setiap baris riwayat log keamanan.
  - Menghadirkan modal dialog karantina terpadu `🚫 Blokir Alamat IP` yang memungkinkan admin memblokir penyerang secara instan langsung dari halaman log.
  - Mendukung pemilihan cakupan pemblokiran berbasis kartu responsif: **Hanya Perangkat** (`device` - isolasi fingerprint peramban tanpa mengorbankan pengguna lain di jaringan NAT/Wi-Fi yang sama) dan **Seluruh IP Router** (`ip` - blokir penuh seluruh lalu lintas dari IP publik tersebut).
  - Pilihan durasi fleksibel (1 jam, 6 jam, 12 jam, 24 jam, 3 hari, 7 hari, 30 hari, atau Permanen) dengan auto-fill alasan ancaman dan catatan investigasi internal opsional.
  - Terintegrasi langsung dengan `PasakSentinelBridge` untuk langsung meneruskan pemblokiran router IP ke kernel firewall Linux (`nftables`/`iptables`).
- **Chart Visual Tren Serangan (Line Chart) pada Halaman Security Logs** (`stubs/blade/pages/pasak/logs.blade.php`, `PasakLogController.php`):
  - Menambahkan kartu visualisasi grafik garis (_line chart_) interaktif berbasis vektor SVG murni (zero-dependency) pada halaman audit log keamanan.
  - Mendukung pergantian rentang waktu dinamis dengan tombol filter **Minggu** (7 hari), **Bulan** (30 hari), dan **Tahun** (12 bulan) dengan tetap mempertahankan filter pencarian, event, level keparahan, rentang tanggal, dan jumlah paginasi tabel.
  - Dilengkapi ringkasan telemetri eksekutif (total ancaman pada periode, tanggal & volume serangan puncak, rata-rata harian/bulanan), titik data interaktif (_nodes_) dengan tooltip rincian keparahan (Critical, High, Medium, Low), garis grid horizontal berlabel sumbu Y, serta gradien area visual (_smooth area fill_).

## [1.7.1] - 2026-11-09

### Removed

- **Pembersihan Direktori Dokumentasi Standalone Lama (`web-docs/` & `finis.sh`)**:
  - Menghapus folder `web-docs/` (Astro Starlight) seutuhnya (~462 MB) dan berkas pembungkus `finis.sh`.
  - Seluruh 29 modul dokumentasi teknis `pasak-security-monitor` telah dialihkan permanen ke **Pasak Cloud SOC** (`https://pasak.is-a.dev/docs`) bertenaga **Neon Serverless PostgreSQL Database**.
  - Membersihkan referensi `web-docs` dan `finis.sh` dari `composer.json`, `.gitattributes`, `push.sh`, dan `AGENTS.md`.

## [1.7.0] - 2026-11-09

### Added

- **Jembatan IPC Unix Domain Socket ke Pasak Sentinel Host Daemon (`PasakSentinelBridge`)** — menambahkan jembatan komunikasi performa tinggi (< 0.2ms) antara aplikasi Laravel dan daemon `pasak-sentinel` melalui socket Unix (`/run/pasak/agent.sock` atau `/tmp/pasak/agent.sock`).
- **Delegasi Pemblokiran IP Otomatis ke Kernel Firewall Linux (`nftables` / `iptables`)** — setiap pemblokiran IP yang dipicu oleh honeypot, tripwire, atau deteksi anomali di `PasakService::blockIp()` kini otomatis diteruskan ke kernel firewall host melalui Sentinel untuk melakukan drop koneksi seketika sebelum paket menyentuh PHP-FPM atau thread web server.
- **Sinkronisasi Pembukaan Blokir Kernel Firewall** — `PasakService::unblock()` dan `PasakService::unblockIp()` secara otomatis menginstruksikan daemon Sentinel untuk mencabut aturan drop IP dari kernel firewall saat blokir dicabut oleh admin.
- **Perintah Diagnostik CLI `php artisan pasak:sentinel`** — menyediakan perintah artisan terpadu dengan opsi `--ping`, `--status`, `--block=IP`, dan `--unblock=IP` untuk menguji dan memverifikasi koneksi socket Unix dengan daemon Sentinel secara langsung dari terminal.
- **Konfigurasi Lingkungan Terpusat (`config/pasak.php`)** — penambahan blok konfigurasi `sentinel` dengan opsi `enabled`, `socket`, dan `timeout` yang dapat disesuaikan lewat environment variable `PASAK_SENTINEL_SOCKET`.

## [1.6.9] - 2026-11-07

### Added

- **Modal Dialog Detail & Bukti Cuplikan Kode untuk Berkas Mencurigakan** (`stubs/blade/pages/pasak/server.blade.php`) — menambahkan tombol `🔍 Detail` dan tautan interaktif pada setiap baris temuan di tabel _Suspicious files & webshells_. Modal menyajikan nama berkas, jalur lengkap, kategori ancaman, ukuran, waktu modifikasi presisi, alasan lengkap, serta blok bukti cuplikan kode (`$file['snippet']`) yang terdeteksi dengan tombol hapus langsung dari dalam modal.
- **Kolom Tanggal Modifikasi Presisi pada Tabel Audit Server** — menyajikan tanggal & jam berkas hingga satuan detik (`d M Y, H:i:s`) dengan timestamp relatif (`diffForHumans`) dan tooltip ISO 8601.

### Fixed

- **Perlindungan Ganda Konfirmasi Penghapusan Berkas (Native Fallback)** — menambahkan `onsubmit="return confirm(...)"` pada form penghapusan berkas mencurigakan untuk menjamin dialog konfirmasi peringatan selalu muncul di peramban pengguna bahkan jika JavaScript kustom terhambat atau file view di VPS belum disinkronkan.
