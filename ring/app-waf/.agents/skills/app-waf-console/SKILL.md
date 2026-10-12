# Skill: app-waf-console

Konsol uji WAF lokal (`app-waf/`) untuk paket `robyajo/pasak`. Aktifkan skill ini
kapan pun mengerjakan: katalog vektor serangan (`src/lib/vectors.js`), proxy
pengujian (`server/proxy.js`), analisis attack surface (`src/lib/surface.js`),
tab Konsol Uji / Pengujian Serangan, atau alur request `useWafConsole.js`.

## 1. Prinsip arsitektur (jangan dilanggar)

1. **Satu perintah**: `pnpm dev` harus cukup. Jangan menambah server PHP,
   database, atau build step di luar Vite.
2. **Logika di hook, UI tipis**: seluruh state + fetch milik
   `useWafConsole()`; komponen hanya render props. `App.jsx` hanya menyusun
   dan memanggil `analyzeSurface` sekali per `result`.
3. **Isolasi dua tab**: tab Konsol Uji memakai `send()` (menulis
   `result`/`history`); tab Pengujian Serangan HANYA via `sendRaw()` agar
   temuan tab pertama tidak berubah.
4. **Browser tidak bisa spoof IP**: header `CF-Connecting-IP` /
   `X-Forwarded-For` / `X-Real-IP` / `True-Client-IP` HANYA disuntik proxy
   (`server/proxy.js`). Jangan mencoba menyetelnya dari `fetch` frontend.
5. **Path mentah**: proxy memakai `node:http`/`node:https` langsung (bukan
   `fetch`/`URL`) agar `../`, `%00`, `....//` terkirim apa adanya.

## 2. Menambah serangan baru (vektor)

Setiap signature di `config/pasak.php` (paket) wajib punya vektor di
`src/lib/vectors.js` dengan grup bernama sesuai id signature:

```js
{
    label: "Nama tombol",
    path: "/?q=%7B%7B7*7%7D%7D",   // query/path mentah, URL-encode bila perlu
    method: "GET",                  // rekomendasi via recommendedMethod()
    // userAgent: "...",            // bila serangan via User-Agent
    // payload: "...",              // body (hanya untuk POST/PUT/PATCH/DELETE)
    // upload: { filename, content, content_type, field }, // multipart sungguhan
    advice: {
        reason: "Kenapa metode/path ini.",
        expect: "403 instant-block; atau pola log progresif.",
        // steps: ["Langkah lanjutan 1", ...],
    },
}
```

Aturan: satu vektor = satu signature; `expect` harus menyebut status/level yang
diharapkan; vektor upload WAJIB `method: "POST"` + `multipart`. Setelah menambah,
jalankan `pnpm build` dan uji 1× ke target lokal dengan IP acak.

## 3. Mengubah proxy (`server/proxy.js`)

- Kontrak request/response (lihat `app-waf/AGENTS.md` §3.1) adalah API publik
  konsol — jangan mengubah bentuk JSON tanpa memperbarui `useWafConsole.js`
  (`postProxy`, `send`, `sendRaw`) sekaligus.
- Batas aman (`TIMEOUT_MS`, `REQUEST_BODY_LIMIT`, `RAW_LIMIT`,
  `DISPLAY_LIMIT`) dan guard SSRF (`parseTarget`) tidak boleh dilonggarkan:
  alat ini bisa menembak URL apa pun.
- Seluruh handler sudah dibungkus try/catch agar dev-server tidak pernah jatuh;
  pertahankan invariant itu.

## 4. Analisis surface (`src/lib/surface.js`)

`analyzeSurface(body, { baseUrl, contentType })` menurunkan temuan
(formulir, parameter query, endpoint, titik upload, komentar HTML, pesan error)
yang kemudian diubah `attack/attackBuilder.js` menjadi request konkret.
Status `405` (bukan `403`) = router target yang menolak, bukan WAF — saran
metode dibaca dari header `Allow` / pesan "Supported methods:".

## 5. Verifikasi wajib sebelum selesai

1. `pnpm build` sukses (tanpa error transform).
2. `pnpm dev` + kirim 1 vektor instant-block (mis. SSTI `{{7*7}}`) dengan IP acak
   → target membalas `403` dan IP tercatat di `blocked_ips`.
3. Tombol tidak double-fire saat `loading`/`progress` aktif.
4. Tidak ada URL target pribadi / IP asli yang ter-commit (form hanya di
   `localStorage`).
