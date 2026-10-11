# Changelog

All notable changes to `robyajo/pasak` will be documented in this file.

## [Unreleased]

### Added

- **Varian stub rute per-driver** (`stubs/routes/pasak-blade/livewire/react.php.stub`): `pasak:install` kini menulis `routes/pasak.php` hanya berisi rute stack terpilih (varian gabungan untuk `both`; file dilewati untuk `none`/`--api-only`). Stub gabungan `pasak.php.stub` tetap dipakai untuk `both` dan sinkronisasi `pasak:upgrade --sync-routes`.

### Fixed

- **`pasak:install` strict by choice**: installer kini menghapus direktori dashboard dari stack yang TIDAK dipilih (`syncDashboardChoice`), sehingga hasil instalasi hanya berisi pilihan user — `plain`/`livewire` hanya menyisakan `resources/views/pages/pasak`, `react` hanya `resources/js/pages+components/pasak`, `none`/`--api-only` menghapus keduanya.
- **Stub `routes/pasak.php` menghormati `dashboard.driver`**: cabang Livewire kini mensyaratkan `driver` bernilai `livewire`/`all`/`both` (sebelumnya hanya `class_exists(Livewire)`, sehingga app Blade yang kebetulan memasang Livewire jatuh ke rute Livewire walau memilih plain).
- **Ringkasan akhir instalasi ganda untuk plain**: baris duplikat "Dashboard Blade: ..." dihapus, tersisa satu baris "Dashboard Blade murni: ...".
- **`pasak:uninstall` membersihkan blok `$middleware->append([])` yang kosong** di `bootstrap/app.php` (regex lama hanya cocok pola panah ganda).

### Verified

- Suite **136 passed (577 assertions)** termasuk 3 test baru strict-by-choice (plain menghapus sisa React, react menghapus sisa Blade, api-only menghapus keduanya) + asersi uninstall tanpa sisa `->append`.

## [1.1.5] - 2026-10-11

### Added

- **Plain-Blade monitoring dashboard (no Livewire/Inertia required)**: new `stubs/blade/pages/pasak/` views (standalone layout + Overview, Logs, Blocked IPs, Server, Sessions, Appeals, Settings) published with `php artisan vendor:publish --tag=plain-views` or `php artisan pasak:install --with-plain` / `--with-blade`. Dashboard controllers now dual-render via `RespondsWithDashboard`: driver `blade` (new default) serves plain views, `react` serves Inertia, with graceful fallback when a stack is missing.
- **Dashboard route file for plain Blade** (`routes/pasak-dashboard-blade.php`) covering all CRUD endpoints with the same `pasak.dashboard.*` names.
- Installer wizard choice for plain Blade (auto-default when Livewire is absent), `--with-livewire` flag, and `--stack` values `blade` / `livewire`.

### Changed

- **BREAKING (minor, pre-1.2)**: `--with-blade` now means plain Blade (was Livewire alias); use `--with-livewire` / `--with-dashboard` for the single-file Livewire version. `--with-both` and `--stack=both` now pair plain Blade + TSX.
- Dashboard `driver` default is now `blade`; `livewire` only serves the Livewire routes.
- Fixed the `routes/pasak.php` stub fallback which rendered non-existent `pages.security.*` views; it now wires the dashboard controllers directly. Default dashboard prefix in stubs corrected to `pasak`.
- `pasak:upgrade` blade sync follows the host stack (Livewire tag vs `plain-views`).

### Verified

- All 7 plain-Blade pages render HTTP 200 with working mutations on a real `laravel new` app; suite at **133 passed (566 assertions)**.

## [1.1.4] - 2026-10-11

### Fixed

- **Dashboard styling gaps**: added 5 missing CSS classes to the Livewire layout (`sec-badge-warning`, `sec-card-body`, `sec-card-desc`, `sec-content`, `sec-label`) plus scope-selector styles; replaced emoji nav icons with inline SVGs on Blade and React.
- **React dashboard**: `pasak-nav.tsx` no longer hardcodes `/security` (prefix derived at runtime); added missing `sec-header/title/subtitle`, `sec-alert*`, `sec-card-desc`, `sec-badge-warning` styles; fixed `ui.tsx` prop types (`id`, `onKeyDown`, `title`, `warning`) — `tsc --noEmit` and `vite build` pass.
- **Dashboard controllers** rendered `Inertia::render('security/*')` while pages live at `js/pages/pasak/*` — corrected to `pasak/*`.
- **`pasak:uninstall` left migration records behind**, so reinstalls reported "Nothing to migrate" with missing tables (dashboard 500s). Uninstall now deletes its migration rows; legacy `security_*` tables are also dropped.

### Verified

- Render-tested on real Livewire + React apps (login, all pages 200, block-IP mutation persisted).

## [1.1.3] - 2026-10-11

### Fixed

- `PasakService::VERSION` was stale (`2.0.8`), making `pasak:upgrade` report a phantom version and silencing update notices. Synced to the release line; `push.sh` now rewrites the constant to the new tag on every push.
- Installer/uninstaller normalize edited host files (single trailing newline, collapsed blank lines) so they stay Pint-clean; `laravel-test/` excluded from `pint.json`.
- Install guide curl probe documented the localhost whitelist exclusion (`PASAK_IP_WHITELIST`).

## [1.1.2] - 2026-10-11

### Changed

- Internal maintenance commit (no user-facing changes).

## [1.1.1] - 2026-10-11

### Added

- **Interactive install wizard** (`php artisan pasak:install`): language choice (ID default / EN, `--lang`), API-only mode (`--api-only`: core + security, no pages or web dashboard routes), starterkit picker (Livewire / React / both / none, skipped for API-only), environment readiness check (PHP, Laravel, DB, `.env`), optional immediate `php artisan migrate`, and numbered next steps each with a docs link to `https://security-dev.pekanbaru.go.id/`.
- New `--with-tsx`, `--with-both`, `--stack` handling unified with the wizard.

## [1.1.0] - 2026-10-11

### Fixed

- Quoted Mermaid node labels containing `()` (`User::logins()`, `Gate::define(...)`) which broke diagram rendering in the docs portal and Starlight site.

## [1.0.10] - 2026-10-11

### Changed

- Docs: last `security.activity` alias references renamed to `pasak.activity`.

## [1.0.9] - 2026-10-11

### Changed

- Docs: stale middleware aliases, `manage-security-monitor` gate, and `security.*` route names updated to `pasak.*` across guides.

## [1.0.8] - 2026-10-11

### Fixed

- **BREAKING (minor, pre-1.1)**: `pasak:upgrade` no longer force-overwrites customized host routes/views; `--sync-routes` / `--sync-views` back up to `*.backup-*` first.
- Fixed installer writing `require security.php` (non-existent file, fatal on hosts) instead of `require pasak.php`, plus duplicate-require guard.
- New `PasakLifecycleEdgeTest` (10 simulations): fresh/partial/legacy hosts, Laravel 10 Kernel style, combined-trait removal, upgrade backup behavior.

## [1.0.7] - 2026-10-11

### Added

- **New `php artisan pasak:uninstall` command**: removes all package traces from host apps without breaking them — drops only Pasak tables, removes published routes/config/views/migrations, detaches middleware and the User trait, strips `PASAK_*` from `.env`, restores `.htaccess` from backup, and runs `composer remove`. Supports `--force`, `--keep-tables`, `--keep-env`.

## [1.0.6] - 2026-10-11

### Changed

- Auto-generated web-docs files (`changelog.md`, `src/data/package.json`) are untracked + gitignored so `git pull` never conflicts with local build output.

## [1.0.5] - 2026-10-11

### Added

- Root `finis.sh` wrapper forwarding to `web-docs/finis.sh` so docs build/deploy runs from the repo root without changing directories.

## [1.0.4] - 2026-10-11

### Changed

- `push.sh` now regenerates `documents/index.html` and runs the web-docs build (`finis.sh --no-pull --no-deploy`) before every commit (`--no-docs-build` to skip).

## [1.0.3] - 2026-10-11

### Changed

- Docs: web-docs site titles, SEO keywords, tracker defaults, and integration-guide examples renamed to Pasak.

## [1.0.2] - 2026-10-11

### Changed

- `push.sh` version policy: patch numbers cap at `.10` then roll over to the next minor (`v1.0.10` → `v1.1.0`); major bumps are never automatic — only via explicit `-t vX.0.0`.

## [1.0.1] - 2026-10-11

### Changed

- `push.sh` auto-tags every push with the recommended version (opt out with `--no-tag`); Dependabot config ignores major updates to protect the CI matrix.

## [1.0.0] - 2026-10-11

### Changed

- **BREAKING — package renamed to `robyajo/pasak`**: namespace `Internal\SecurityMonitor` → `Internal\Pasak`; `SecurityMonitorService` → `PasakService`; `SecurityMonitorServiceProvider` → `PasakServiceProvider`; facade `SecurityMonitor` → `Pasak`. Backward-compatible class aliases ship in `src/compat.php`.
- **BREAKING — `pasak` naming across the stack**: `config/security.php` → `config/pasak.php` with `PASAK_*` env vars (legacy `SECURITY_*` still work as fallback); Artisan `security:*` → `pasak:*`; middleware aliases `pasak.*`; gate `manage-pasak`; single consolidated migration `create_pasak_tables` with `pasak_logs` / `pasak_settings` tables (legacy tables auto-renamed with data preserved).

## [2.1.0] - 2026-10-09

### Added

- **Hardened Apache 2 VirtualHost Configuration (`stubs/apache2.conf.stub`)**:
  - Menyediakan template VirtualHost Apache 2 hardened siap pakai untuk server Ubuntu / Debian / CentOS.
  - Tag vendor publish baru: `php artisan vendor:publish --tag=security-apache` (menghasilkan berkas `apache2.conf` di root aplikasi host).
  - Terintegrasi penuh ke dalam perintah instalasi `php artisan security:install` dan grup tag `--tag=security-all`.
  - Opsi CLI baru: `--without-apache` pada `php artisan security:install` untuk melewati publikasi `apache2.conf`.
  - Fitur pengamanan VirtualHost mencakup:
    - Mitigasi Slowloris DoS via `mod_reqtimeout` (`header=15-30,MinRate=500 body=15,MinRate=500`).
    - Buffer request body (5MB) dan header buffer (`LimitRequestFieldSize 32768`) untuk mencegah DoS dan error token JWT/Cookie besar.
    - Penolakan HTTP method berisiko (`TRACE`, `TRACK`) untuk mitigasi Cross-Site Tracing (XST).
    - Header keamanan lengkap: `X-Frame-Options`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, dan penyembunyian `X-Powered-By`.
    - Resolusi IP asli via `mod_remoteip` (`X-Forwarded-For` / `CF-Connecting-IP`).
    - Restriksi eksekusi PHP: hanya `/index.php` yang diizinkan dieksekusi, semua berkas `.php` lain ditolak dengan 403 Forbidden.
    - Pencegahan eksekusi berkas double extension (`.php.jpg`, `.phtml.zip`, dll.).
    - Sandboxing folder `/storage/` dengan `Content-Security-Policy: sandbox` untuk mencegah eksekusi polyglot/SVG berbahaya.
    - Integrasi PHP-FPM aman melalui `mod_proxy_fcgi`.

## [2.0.10] - 2026-10-09

### Changed

- **Hardening Template Nginx (`stubs/nginx.conf.stub`)**:
  - Peningkatan FastCGI buffer: `fastcgi_buffers 16 16k;`, `fastcgi_buffer_size 32k;`, `fastcgi_busy_buffers_size 64k;` untuk mencegah 502 Bad Gateway saat respons besar.
  - Penambahan buffer header klien (`client_header_buffer_size 16k;`, `large_client_header_buffers 4 32k;`).
  - Penambahan timeout mitigasi Slowloris DoS (`client_body_timeout 15s;`, `client_header_timeout 15s;`, `send_timeout 15s;`).
  - Restriksi HTTP method (`GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS`).
  - Blueprint pemulihan real IP dari reverse proxy / Cloudflare / Docker.

## [2.0.9] - 2026-10-05

### Fixed

- **Kompatibilitas CI & Test Matrix L10 - L13**:
  - Menambahkan dependensi `guzzlehttp/guzzle: ^7.8|^8.0` pada `composer.json` untuk menjamin ketersediaan PSR-7 Response saat pengujian `Http::fake()` dan fitur `VersionCheckService` pada lingkungan Laravel 10.
  - Memperbaiki analisis statis PHPStan pada evaluasi tipe dinamis `LoginThrottleService` dan `SecurityInstallCommand`.
  - Mengoptimalkan assertion command options pada `SecurityInstallAutoInjectTest`.

## [2.0.8] - 2026-10-05

### Added

- **Pemeriksaan Versi Otomatis & Notifikasi Upgrade di Terminal (`php artisan serve` & `composer run dev`)**:
  - Secara otomatis memeriksa apakah terdapat versi rilis terbaru di Packagist/GitHub saat pengembang menjalankan `php artisan serve` atau `composer run dev` (`artisan dev`).
  - Menampilkan notifikasi visual di terminal konsol yang elegan dan informatif jika versi baru telah dirilis, lengkap dengan saran perintah upgrade.
  - Pemeriksaan dirancang sangat ringan, non-blocking (timeout 2 detik), dan di-cache selama 1 jam (`SECURITY_VERSION_CHECK_CACHE_TTL=3600`) sehingga tidak pernah memperlambat atau mengganggu startup server.
  - Dapat diaktifkan/dinonaktifkan melalui konfigurasi `security.version_check.enabled` atau variabel `.env`: `SECURITY_VERSION_CHECK_ENABLED=true`.

- **Perintah Baru `php artisan security:upgrade`**:
  - Perintah artisan terpadu untuk memeriksa, memperbarui, dan menyinkronkan seluruh komponen package:
    - `php artisan security:upgrade`: Memperbarui package via Composer (`composer update robyajo/pasak`), menerapkan migrasi database terbaru (`php artisan migrate`), menyinkronkan rute kustom (`routes/security.php` & `routes/security-api.php`), menyinkronkan tampilan dashboard monitoring (Blade/Livewire & React/TSX), serta membersihkan cache framework.
    - Opsi `--check`: Hanya memeriksa status versi tanpa menjalankan proses pembaruan.
    - Opsi `--force`: Memaksa pembaruan dan sinkronisasi aset meskipun sudah di versi terbaru.
    - Opsi `--no-composer`: Melewati pembaruan Composer (hanya sinkronisasi aset lokal).
    - Opsi `--no-migrate`: Melewati eksekusi migrasi database.
    - Opsi `--sync-routes` & `--sync-views`: Memaksa pembaruan berkas rute dan tampilan dashboard.

- **Integrasi Informasi Versi pada Dashboard Pengaturan & REST API**:
  - Halaman Pengaturan Keamanan (`/security/settings`) pada Livewire dan React kini menampilkan status versi aktif dan banner notifikasi pembaruan secara visual.
  - Endpoint REST API `GET /api/security/settings` menyertakan metadata `version` (`current`, `latest`, `update_available`).

## [2.0.7] - 2026-10-05

### Added

- **Interactive Security Settings Page & Dynamic Configuration (Livewire, React, & REST API)**:
  - Added dedicated Security Settings dashboard page accessible via `/security/settings` in both Livewire (`pages/security/settings.blade.php`) and React (`pages/security/settings.tsx`).
  - Added Settings link (⚙️) to the sidebar navigation in both Blade and React dashboard layouts.
  - Interactive radio cards allowing administrators to configure blocking scope with clear visual guidance:
    - **Isolasi Perangkat Saja (`device`)** *(Default / Rekomendasi)*: Only quarantines the offending device based on Device ID / Fingerprint / LAN IP, keeping innocent users sharing the same WiFi or NAT router completely safe.
    - **Seluruh IP Router Publik (`ip`)**: Quarantines the entire public router IP address.
  - Dynamic configuration controls for:
    - Automatic blocking scope (`auto_block_scope`: `device` or `ip`)
    - Zero tolerance instant blocking scope (`instant_block_scope`: `device` or `ip`)
    - Threshold count (`auto_block_threshold`)
    - Accumulation window in minutes (`auto_block_window`)
    - Quarantine duration in hours (`auto_block_duration`)
    - Zero tolerance instant duration (`instant_block_duration`)
    - HTTP 403 block enforcement master toggle (`block_enforcement`)
  - Dynamic multi-layer persistence: `SecuritySetting` database model/migration, high-performance in-memory and Cache layer, with automatic `.env` synchronization.
  - Headless REST API endpoints:
    - `GET /api/security/settings` (retrieves current configuration)
    - `POST /api/security/settings` (updates and applies settings with zero downtime)
  - Added comprehensive feature tests in `tests/Feature/SecuritySettingsTest.php`.

## [2.0.6] - 2026-10-05

### Added

- **Device-Scoped Automatic & Instant Blocking (WiFi & NAT Router Isolation)**:
  - Enforced device-level isolation for both threshold-based automatic blocking (`autoBlockIfNeeded`) and zero-tolerance instant blocking (`blockImmediately`), ensuring that only the specific attacking device is quarantined rather than blocking the entire public router IP or office/cafe WiFi network.
  - Innocent users and colleagues sharing the same NAT public IP address can continue accessing the application without being affected or receiving HTTP 403 Forbidden.
  - Added configurable blocking scopes in `config/security.php` and `stubs/env.stub`:
    - `SECURITY_AUTO_BLOCK_SCOPE=device` (options: `device` or `ip`, default: `device`).
    - `SECURITY_INSTANT_BLOCK_SCOPE=device` (options: `device` or `ip`, default: `device`).
  - Deterministic client device fingerprinting (`generateDeviceFingerprint()`): automatically synthesizes client identifiers (`dev_*`) from User-Agent, language, and client platform hints when custom headers (`X-Device-Id`, `X-Client-Id`) are not explicitly sent.
  - Persistent device cookie attachment: `BlockIpAddress` middleware attaches an `app_device_id` cookie to responses (including 403 Forbidden response pages) to guarantee seamless device tracking across subsequent visits.
  - Updated `LogFailedLoginAttempt` and `LoginThrottleService` to track and pass device IDs and private LAN IPs discovered via WebRTC to brute-force lockout evaluations.
  - Added end-to-end feature tests in `tests/Feature/DeviceLevelBlockingTest.php` verifying that two clients on the identical public router IP (`REMOTE_ADDR`) are isolated so that the attacker receives 403 Forbidden while the innocent device receives 200 OK.

## [2.0.5] - 2026-10-05

### Added

- **Automated API Setup & Customizable Route Files (`routes/security.php` & `routes/security-api.php`)**:
  - Automatically checks and executes `php artisan install:api` during `security:install` if API routes are not yet initialized in Laravel 11/12/13 host applications.
  - Generates `routes/security-api.php` for headless REST API endpoints and wires it into `routes/api.php` (`require __DIR__.'/security-api.php';`).
  - Generates `routes/security.php` for web dashboard routes and wires it into `routes/web.php` (`require __DIR__.'/security.php';`).
  - Web routes in `routes/security.php` render directly to views (`pages::security.*` / `resources/views/pages/security/*` for Livewire/Blade or Inertia controllers for React) so developers have full control to customize URL prefixes, middleware, layout wrappers, and custom pages.
  - Added `security-routes`, `security-routes-web`, and `security-routes-api` publication tags to `php artisan vendor:publish`.
  - Added `--without-routes` and `--without-api` flags to `php artisan security:install`.
  - Smart route loading in `PasakServiceProvider`: prioritized host route files if customized and prevents route duplication with host `routes/web.php` and `routes/api.php`.

## [2.0.4] - 2026-10-05

### Added

- **Pure Vanilla CSS Architecture for Dashboard Views**:
  - Migrated both Blade (Livewire) and React (TSX) dashboard stubs to pure Vanilla CSS.
  - Eliminated external UI library dependencies (removed Livewire Flux UI, Shadcn UI, `@/lib/utils`, and `sonner`).
  - Added standalone `security.css` stylesheet and zero-dependency `ui.tsx` helper components (`Card`, `Button`, `Badge`, `Input`, `Label`).
  - Unified themeable CSS Custom Properties (`--sec-*`) with built-in automatic dark mode (`prefers-color-scheme: dark` and `.dark` / `[data-theme="dark"]`).
  - 100% responsive layout across mobile, tablet, and desktop viewports with accessible modal dialogs and pure CSS trend chart bars.

## [2.0.3] - 2026-10-05

### Added

- **Automated Host Setup in `security:install`**:
  - Automatically detects and injects the `HasSecurityRelations` trait and import into `app/Models/User.php`.
  - Automatically registers WAF middlewares (`BlockIpAddress` and `DetectSecurityThreats`) in `bootstrap/app.php` (Laravel 11 & 12) or `app/Http/Kernel.php` (Laravel 10).
  - Both injections are idempotent and preserve existing code formatting and PHPDoc tags.
  - Added `--without-user-trait` and `--without-middleware` flags to bypass automatic registration if needed.

## [2.0.2] - 2026-10-04

### Added

- **Blade & TSX Starter Kit Tags**:
  - Added `--with-blade`, `--with-tsx`, and `--with-all` flags to `php artisan security:install` alongside interactive stack selection.
  - Added `starterkit-blade`, `starterkit-tsx`, `starterkit-all`, `security-dashboard-blade`, `security-dashboard-tsx`, and `security-dashboard-all` publication tags.
  - Enforced authentication and login requirement across all `/security` monitoring routes.

## [2.0.1] - 2026-10-03

### Changed

- Refinements to post-2.0.0 headless architecture and route bindings.

## [2.0.0] - 2026-10-03

### Changed

- **BREAKING — version corrected to a major.** Removing the CAPTCHA subsystem
  deletes public API (`CaptchaService`, `ValidCaptcha`, `CaptchaApiController`),
  routes, config keys, and `CAPTCHA_*` environment variables, so it is a
  breaking change. The same removal was briefly tagged `1.1.4`; `2.0.0` is the
  canonical release. See the `1.1.4` entry below for the full list of removals.

## [1.1.4] - 2026-10-03

### Removed

- **CAPTCHA subsystem removed.** The zero-dependency SVG CAPTCHA has been
  dropped to keep the package focused on WAF/threat protection. Deleted:
  `CaptchaService`, `ValidCaptcha` rule, `CaptchaApiController`, the
  `/api/security/captcha` endpoints, the `security.captcha` config block, and
  the `CAPTCHA_*` environment variables. Login brute-force protection is still
  provided by the multi-tier stepped login lockout.
## [1.1.3] - 2026-10-03

### Added

- **Log4Shell / JNDI detection** (`log4shell_jndi`): new zero-tolerance
  instant-block signature that detects `${jndi:...}` payloads in any request
  part (User-Agent, query string, body, headers), including the common
  obfuscations `${${lower:j}ndi:...}` and `${j${lower:n}di:...}` as well as
  URL-encoded forms such as `%24%7Bjndi%3A...`. Previously these payloads were
  neither detected nor logged, so a Log4Shell probe passed straight through.

### Changed

- README: the install command now uses the explicit stable constraint
  (`composer require robyajo/pasak:^1.1`) and warns against
  `@dev`, which forces the unreleased `dev-main` branch instead of a tagged
  release.

## [1.1.2] - 2026-10-02

### Changed

- README: installation guidance refresh (explicit stable constraint and the
  `@dev` warning).

## [1.1.1] - 2026-10-02

### Changed

- Distribution: the Astro documentation site (`web/`) and the `documents/`
  folder are now excluded from the Composer/Packagist archive via
  `export-ignore` and `archive.exclude`. The previous `/DOCS` entry was
  case-sensitive and never matched the lowercase `documents/` folder, so the
  documentation was shipped to consumers by accident.

### Added

- Official documentation site (Astro + Starlight) maintained in a separate
  repository (`robyajo/web-laravel-security`). It is not part of the package
  distribution.

## [1.1.0] - 2026-10-02

### Added

- **Livewire Starter Kit monitoring dashboard** (optional): publish six
  single-file Livewire pages (Overview, Security Logs, Blocked IPs, Server
  Audit, User Sessions, Unblock Appeals) with
  `php artisan vendor:publish --tag=starterkit-livewire`. The dashboard is
  disabled by default and served under `/security` behind the
  `web` + `auth` + `security.admin` middleware.
- **React (Inertia) Starter Kit monitoring dashboard** (optional): publish six
  server-rendered Inertia + React pages and shared components with
  `php artisan vendor:publish --tag=starterkit-react`. Backed by the new
  `Internal\Pasak\Http\Controllers\Dashboard\*` controllers, so no
  separate API token is required. Enable with `SECURITY_DASHBOARD_DRIVER=react`.
- `--with-dashboard` and `--with-react-dashboard` options on
  `php artisan security:install`.
- `documents/generate.php`: regenerates the `documents/index.html` portal
  (Tailwind CSS Play CDN + marked.js + Mermaid.js) from the markdown chapters.

### Changed

- The `dashboard` configuration block gained a `driver` option
  (`livewire` | `react`) and the new `SECURITY_DASHBOARD_DRIVER` environment
  variable. Both starter-kit dashboards share the same route prefix and
  authorization middleware.
- The documentation portal `documents/index.html` now uses the Tailwind CSS
  Play CDN instead of hand-written CSS, and its content is generated from the
  markdown chapters so it never drifts from `documents/`.

### Documentation

- Documented the Livewire and React dashboards in the README, the installation
  guide, and the frontend integration guides, including the new `.env`
  variables (`SECURITY_DASHBOARD_ENABLED`, `SECURITY_DASHBOARD_DRIVER`,
  `SECURITY_DASHBOARD_PREFIX`).

## [1.0.12] - 2026-10-02

### Fixed

- **PHP 8.2 / 8.3 compatibility (critical)**: `UserLoginService` chained a
  method call directly off a `new` expression
  (`new $model()->newQuery()`). That syntax only parses from PHP 8.4, so on
  PHP 8.2/8.3 it raised a `ParseError` that failed every PHP 8.2/8.3 test-matrix
  job as well as the Pint job. It now uses `$model::query()`, which parses and
  formats identically on every supported PHP version.
- PHPStan: the `view()->exists('errors.blocked')` suppression now matches both
  the `true` and `false` evaluation, since the result depends on whether the
  host application has published the view.

## [1.0.11] - 2026-10-02

### Fixed

- CI: `laravel/pint` and `larastan/larastan` are no longer `require-dev`
  dependencies. Pint requires PHP 8.3 and Larastan 3 requires Laravel 11+, which
  broke the PHP 8.2 / Laravel 10 matrix jobs. Both tools are now installed only
  in their dedicated CI jobs; run `composer dev:tools` locally to use them.
- PHPStan: replaced the environment-dependent `view()->exists()` ignore with an
  identifier-scoped ignore for `src/Http/Middleware/BlockIpAddress.php`.

## [1.0.10] - 2026-10-02

### Changed

- The application audit now reads `PUBLIC_API_KEY` and `TRUSTED_PROXIES` via
  `config('security.server_scan.*')` instead of calling `env()`
  inside a service, and the weak-key placeholder list no longer references an
  app-specific value.

### Fixed

- PHPStan: added `tests/*`-scoped ignores for Pest's magic `$this` /
  `TestCall` so IDE analysis of the test suite stays quiet, plus
  `reportUnmatchedIgnoredErrors: false`.

## [1.0.9] - 2026-10-02

### Added

- Static analysis setup: Larastan + PHPStan (`phpstan.neon.dist`,
  `phpstan-baseline.neon`), a `composer analyse` script, and a dedicated
  "Static Analysis" CI job.
- Regression tests covering the CAPTCHA endpoint, login recording, session
  logout, trusted IP storage, admin auto-unblock, and server scan with admins.

### Fixed

- `pushMiddleware()` was called on the `Illuminate\Contracts\Http\Kernel`
  interface; middleware auto-registration now narrows to the concrete
  Foundation kernel before calling it.
- `CaptchaApiController` called the protected `CaptchaService::render()`; the
  public `/captcha` endpoint now uses `generate()` and verifies with the correct
  signature.
- Several listeners and services referenced a non-existent `User` class
  (`RecordUserLogin`, `ResetLoginAttempts`, `UserLoginService::trustIp()`,
  `UserLoginService::getRealtimeActiveUsers()`, and
  `ServerSecurityService::accountChecks()`), which silently disabled login
  recording, admin auto-unblock, and the 2FA server audit. All now resolve the
  configured user model dynamically.
- Added the missing `UserLoginService::logoutSession()` method used by the
  admin session endpoint.
- Corrected model relationship PHPDoc that referenced a non-existent
  `User` class, and used `getAuthIdentifier()` where the authenticated user
  contract is in play.

## [1.0.8] - 2026-10-02

### Added

- Publishable default 403 page `resources/views/errors/blocked.blade.php`
  (`vendor:publish --tag=security-views`), including a self-contained appeal
  form wired to the public unblock-ticket endpoint. Integrated into
  `security:install` with a new `--without-views` option.
- Laravel Pint configuration (`pint.json`), `composer format` / `composer lint`
  scripts, and a dedicated "Code Style" CI job.

### Changed

- Applied Laravel Pint code style across the source and test suites.

## [1.0.7] - 2026-10-02

### Added

- Presentation deck for the package under `paparan/`
  (`Laravel-Security-Monitor-Bulwark.pptx`, 20 slides) with a reproducible
  generator (`paparan/generate.php`). Excluded from the Composer distribution.

## [1.0.4] - 2026-10-02

### Changed

- Exclude development-only assets (`.agents/`, `AGENTS.md`, `DOCS/`, `.github/`,
  `push.sh`) from the Composer distribution archive via `.gitattributes`
  `export-ignore` rules and the `archive.exclude` Composer setting, so
  `composer require` installs only the runtime package.

## [1.0.0] - 2026-10-01

### Added

- **Self-Hosted WAF & Threat Engine**:
  - Zero-tolerance instant block signatures (null byte, double extensions, path traversal, webshell probes, SSTI canary, polyglot uploads).
  - Multi-tier progressive threat scoring and auto-blocking with sliding time windows.
  - ReDoS-hardened regex patterns tuned against real-world incidents.
  - Reverse proxy support (`CF-Connecting-IP`, `X-Real-IP`, `X-Forwarded-For`).
- **Device-Level Quarantine & Scope**:
  - Granular blocking via `device_id` and `local_ip` (WebRTC / device fingerprint) to avoid punishing innocent users on shared NAT/router public IPs.
  - Block scopes: `ip` (entire router) vs `device` (specific client device).
- **Public Appeal & Ticket Submissions**:
  - Public headless REST API for unblock appeal ticket submission and verification.
  - Admin approval/rejection endpoints with automatic IP/device quarantine release.
- **Multi-Tier Stepped Login Protection**:
  - Stepped progressive lockouts (1m -> 5m -> 15m -> 1h -> 24h) preventing brute force and credential stuffing attacks.
  - Automatic failed login security event logging.
- **Zero-Dependency SVG CAPTCHA**:
  - Pure SVG vector-matrix challenge generator without GD or Imagick PHP extension requirements.
  - Cryptographically secure one-time stateless session tokens.
- **Server Integrity & Security Scanner**:
  - SHA-256 baseline creation and change verification.
  - Suspicious file & webshell scanner with safe admin deletion capabilities (traversal protected, vital files protected).
  - Server configuration audit (PHP version, debug mode, HTTPS cookies, Fortify 2FA).
- **Apache / Nginx Access Log Scanner**:
  - Streaming log parser to detect web attacks rejected before reaching Laravel.
  - Auto-import and zero-tolerance IP blocking from raw server logs.
- **Headless REST API Architecture**:
  - 100% decoupled from any specific frontend (React/Inertia/Blade/Livewire/Mobile).
  - Pure JSON endpoints for logs, blocked IPs, server scans, sessions, appeals, and captcha.
- **Enterprise Extensibility**:
  - Configurable user model (`config('security.user_model')`).
  - Configurable table names (`config('security.table_names.*')`).
  - `HasSecurityRelations` model trait for seamless user relationship bindings.
  - Laravel 10, 11, 12, and 13 compatibility.
