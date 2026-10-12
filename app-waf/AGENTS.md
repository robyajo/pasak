# Agent Guidelines for `app-waf` (WAF Test Console)

## 1. Project Overview

`app-waf/` adalah **konsol uji lokal satu halaman** (React 19 + Vite 7 + Tailwind CSS v4)
untuk memverifikasi WAF [`robyajo/pasak`](../) (Bulwark) — **bukan bagian dari paket
rilis** (tidak ikut ke Packagist; tidak ada ketergantungan Composer).

- **Sifat**: alat pengujian lokal saja. **Jangan pernah mengekspos ke internet publik**
  (dapat mengirim request ke URL apa pun dengan header IP palsu).
- **Prinsip**: satu perintah (`pnpm dev`), tanpa server PHP terpisah, tanpa database.
- **Manajer paket**: `pnpm` (lockfile: `pnpm-lock.yaml`). Jangan memakai `npm install`
  (akan membuat `package-lock.json` yang konflik). Build esbuild harus diizinkan
  via `pnpm-workspace.yaml` (`allowBuilds: esbuild`, `@tailwindcss/oxide`).

## 2. Running

```bash
cd app-waf
pnpm install     # wajib setelah clone; verifikasi node_modules/esbuild ada
pnpm dev         # http://localhost:5175 — biarkan terminal terbuka
pnpm build       # verifikasi produksi (output dist/, diabaikan git)
```

Siapkan target, mis. `laravel-test/laravel-none` (`php artisan serve` → `:8000`),
dengan `trustProxies(at: ['127.0.0.1', '::1'])` agar header IP virtual terbaca
(`$request->ip()` lokal selalu `127.0.0.1` yang di-whitelist). Jangan pakai `'*'`.

## 3. Architecture

```text
app-waf/
├── vite.config.js              # plugin React + Tailwind + proxy /api/proxy
├── server/proxy.js             # proxy sisi server (POST /api/proxy)
└── src/
    ├── App.jsx                 # tipis: susun komponen + analisis surface sekali
    ├── hooks/useWafConsole.js  # SELURUH state + logika jaringan (klien hanya UI)
    ├── components/             # RequestForm, ResultPanel, HistoryTable, ...
    │   └── attack/             # tab Pengujian Serangan (tidak menyentuh result tab 1)
    └── lib/
        ├── vectors.js          # KATALOG SERANGAN (14 grup, ~91 vektor) + UA presets
        ├── surface.js          # analyzeSurface: body respons → temuan serang
        ├── ip.js               # generator IP publik acak
        └── http.js             # statusTone: status → warna badge
```

### 3.1. Kenapa proxy sisi server (`server/proxy.js`)

Browser melarang JS menyetel *forbidden headers* (`CF-Connecting-IP`,
`X-Forwarded-For`, `X-Real-IP`). Frontend mengirim `POST /api/proxy`, proxy
meneruskan via `node:http`/`node:https` **langsung** (bukan `fetch`) agar path
mentah (`../../etc/passwd`) tidak dinormalisasi, sambil menyuntikkan 4 header
IP virtual + membangun `multipart/form-data` sungguhan untuk uji upload.

Kontrak `POST /api/proxy` (JSON): `target, path, ip, method, user_agent,
payload, content_type, upload{field,filename,content,content_type}`.
Respons: `{success, ip, url, base_url, method, status, status_text,
blocked(403/429), duration_ms, size, truncated, content_type,
request_headers, response_headers, body}`.

Batasan proxy: body request ≤ 5MB, respons ditampung ≤ 2MB / tampil ≤ 200KB,
timeout 15 dtk, guard SSRF minimal (tolak `169.254.169.254`,
`metadata.google.internal`, skema non-http/https).

### 3.2. Katalog serangan (`src/lib/vectors.js`)

Setiap grup memetakan 1:1 ke signature `config/pasak.php` (instant_block ×10,
rules ×7, sensitive_paths, scanner_agents). Format vektor:
`{label, path, method, userAgent?, payload?, upload?, advice{reason, expect, steps?}}`.
Menambah signature baru di paket → **tambah grup/vektor di sini + build**.

### 3.3. Aturan data & UI

- `useWafConsole()` pemilik tunggal state (`form, result, history, tab, loading,
  progress, previewOpen, activeVector`); komponen hanya presentasi via props.
- `send()` menulis `result`/`history`; `sendRaw()` untuk tab serangan (isolasi).
- Guard anti-klik-ganda: `loading || progress !== ""`.
- `HistoryTable` menyimpan `{time, ip, path, method, status, blocked, ms}` + ekspor JSON.
- Iframe pratinjau: `sandbox` tanpa `allow-same-origin` + injeksi `<base href>`.

## 4. Conventions & Safety

- Bahasa UI & komentar: Indonesia. Pesan error jaringan harus menyebut
  "`pnpm dev` masih berjalan".
- Target bawaan `http://localhost:8000`; jangan commit URL/target pribadi
  (form tersimpan di `localStorage`, bukan di repo).
- Setiap perubahan vektor/proxy: verifikasi `pnpm build` sukses + kirim 1 vektor
  ke target lokal (403 untuk instant-block, IP acak baru tiap kirim).
- Jangan menambahkan dependensi backend/framework — konsol ini harus tetap
  file-statis + satu middleware dev-server.
