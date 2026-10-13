# DOCS — Konsol Uji WAF (app-waf)

Konsol uji lokal satu halaman (React 19 + Vite 7 + Tailwind v4) untuk
membuktikan WAF `robyajo/pasak` (Bulwark) bekerja — dan membuktikan aplikasi
**tanpa** WAF jebol. Alat pengujian saja: **jangan pernah diekspos ke internet
publik** (bisa mengirim request ke URL apa pun dengan header IP palsu).

- Manajer paket: `pnpm` (jangan `npm install`). Perintah: `pnpm dev`
  (http://localhost:5175), `pnpm build` (= `tsc --noEmit && vite build`).
- Tanpa database, tanpa backend framework — hanya file statis + satu
  middleware dev-server (`server/proxy.js`).
- Stack UI: komponen `@/components/ui` (shadcn/base-ui), ikon `lucide-react`,
  state identitas lab via **Zustand** (`src/stores/lab.ts`).

## 1. Untuk apa

1. **Audit Tanpa-Pasak (tab ⚠️ Tanpa Pasak = Jebol)** — 4 tahap berurutan
   (baseline → recon → eksploitasi → webshell/RCE) yang membuktikan aplikasi
   tanpa WAF benar-benar jebol, lengkap dengan vonis + laporan JSON.
2. **Uji Dengan-Pasak (tab Konsol Uji)** — kirim 91+ vektor serangan katalog
   ke aplikasi ber-Pasak dan pastikan semuanya 403, plus laporan perbandingan.
3. **Edukasi rantai hacker** — tiap kartu serangan punya metode eksekusi nyata,
   rantai eskalasi (probe ORDER BY otomatis, analisis refleksi XSS, bukti
   evaluasi SSTI, cek delay blind), intel + payload lanjutan yang bisa
   langsung diuji dari dalam sheet.
4. **Preview korban realistis** — iframe dimuat *sebagai* IP + perangkat
   penyerang, sehingga halaman blokir 403 ikut terlihat saat identitas itu
   diblokir.

Target lab bawaan: `pasak-no` (`http://localhost:8000`, tanpa WAF) dan
`pasak-yes` (`http://localhost:8001`, dengan Pasak).

## 2. Struktur

```text
app-waf/
├── vite.config.js          # plugin proxy WAF (dev + preview server)
├── server/proxy.js         # POST /api/proxy, GET /api/preview-victim
└── src/
    ├── App.tsx             # tipis: tab + susun komponen
    ├── hooks/useWafConsole.ts  # state + logika jaringan tab Konsol
    ├── stores/lab.ts       # Zustand: target, IP penyerang, ID perangkat
    ├── lib/
    │   ├── vectors.ts      # katalog serangan (14 grup, ~91 vektor)
    │   ├── noPasak.ts      # 9 rekomendasi + 4 tahap audit Tanpa-Pasak
    │   ├── vulnInfo.ts     # info kerentanan, metode hacker, rantai eskalasi
    │   ├── surface.ts      # analyzeSurface → fingerprinting
    │   ├── ip.ts           # generator IP publik acak
    │   └── http.ts         # status → warna badge
    └── components/
        ├── RequestForm.tsx     # form kirim manual + bulk test
        ├── ResultPanel.tsx     # hasil + temuan surface
        ├── HistoryTable.tsx    # riwayat kiriman + ekspor JSON
        ├── ConsoleReport.tsx   # laporan Dengan-Pasak (diblokir vs lolos)
        └── attack/
            ├── AttackTab.tsx       # pengujian per temuan surface
            ├── NoPasakTab.tsx      # audit 4 tahap + sheet + laporan
            └── sheet/              # MethodSheet: aksi, hasil, rantai, remedi
```

## 3. Yang bisa dilakukan per tab

### 3.1. Konsol Uji (Dengan-Pasak)

- Kirim request manual (method, path, UA, payload, upload multipart sungguhan).
- Terapkan vektor dari katalog, kirim massal (bulk test N IP acak).
- Lihat temuan fingerprinting otomatis dari body respons.
- **Laporan Dengan-Pasak**: ringkasan diblokir vs lolos + vonis
  (TERLINDUNGI / SEBAGIAN LOLOS / JEBOL) + fingerprinting, preview accordion,
  salin & unduh JSON.

### 3.2. Pengujian Serangan

- Tembak langsung tiap temuan fingerprinting (form/param/endpoint/upload).
- Badge jumlah temuan di judul tab.

### 3.3. Tanpa Pasak = Jebol (audit 4 tahap)

- **Tahap 1 Baseline** — titik acuan respons normal.
- **Tahap 2 Recon** — scanner UA, probe `.env`, path traversal (tahap
  terkunci sampai tahap sebelumnya semua lolos/tidak-403).
- **Tahap 3 Eksploitasi** — XSS, SQLi, SSTI, PHP injection + metode hacker
  yang bisa dieksekusi (tombol ▶ per metode → hasil di Sheet kanan).
- **Tahap 4 Webshell** — tanam `shell.php.jpg`, verifikasi file publik,
  kartu penyerang (perintah bebas + preset curi data), vonis RCE.
- **Rantai eskalasi** tiap kartu: probe ORDER BY 1–8 otomatis (simpulkan
  jumlah kolom + payload UNION siap salin), analisis token MENTAH/ter-encode/
  hilang (XSS), vonis TEREVALUASI (SSTI), cek delay SLEEP (blind), cuplikan
  bukti, intel hacker, dan kotak **AKSI UJI BERIKUTNYA** (salin payload +
  🧪 Uji langsung → hasil tampil di panel Hasil eksekusi).
- **Preview Korban (live)** — iframe via proxy sebagai IP + perangkat
  penyerang; tombol cek status blokir; ikut menampilkan halaman 403 bila
  identitas itu diblokir.
- **Laporan Tanpa-Pasak** — LOLOS_TERBUKTI vs tanpa-bukti vs aman, metode,
  rantai, webshell/RCE, pengecualian tahap; salin & unduh JSON.
- Mode production: aksi rantai/payload/metode minta konfirmasi per tindakan;
  RCE (/shell) khusus lab lokal.

## 4. Identitas lab (Zustand)

Satu sumber (`waf-lab-store`, persist `target` + `deviceId`):

| State | Perilaku |
| --- | --- |
| `target` | URL target, dua arah dengan form konsol |
| `attackerIp` | Selalu terisi; acak baru tiap halaman dibuka (tidak persist) |
| `deviceId` | `lab-xxxxxxxx`, stabil; dadu = putar (simulasi ganti perangkat) |

Semua kiriman membawa `X-Device-Id` + `X-Local-Ip` yang sama dengan preview —
blokir `device-scope` terlihat konsisten di serangan maupun preview.

## 5. Kontrak proxy

- `POST /api/proxy` (JSON): `target, path, ip, method, user_agent, payload,
  content_type, upload{field,filename,content,content_type}, device_id,
  local_ip`. IP kosong = acak. Respons: `{success, ip, url, method, status,
  blocked, duration_ms, size, body, ...}`. Batas: body request ≤ 5MB, respons
  ≤ 2MB (tampil ≤ 200KB), timeout 15 dtk, tolak metadata cloud + non-http(s).
- `GET /api/preview-victim?target=&page=&ip=&device=&localip=` — HTML target
  diambil sisi server sebagai identitas penyerang + injeksi `<base href>`.

## 6. Alur uji tipikal (banding)

1. `php artisan serve` pasak-no (`:8000`) + pasak-yes (`:8001`).
2. Tab Tanpa-Pasak → target `:8000` → jalankan tahap 1–4 → vonis JEBOL → unduh JSON.
3. Tab Konsol Uji → target `:8001` → kirim katalog/bulk → semua 403 → laporan TERLINDUNGI.
4. Bandingkan kedua JSON: bukti sebelum/sesudah WAF.

## 7. Batasan

- Konsol ini **tidak menyentuh database dan tidak login** ke target; semua
  "pembuktian" dibaca dari respons HTTP.
- Preview adalah render ulang via proxy (JS target berjalan; cookie/sesi
  browser asli tidak dipakai).
- Audit production: upload hanya sebagai bukti 403, tanpa RCE, wajib dalam
  cakupan izin tertulis.
