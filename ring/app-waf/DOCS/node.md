# Peran Node.js — Konsol Uji WAF (app-waf)

Node.js (v24) adalah **runtime satu-satunya** yang menghidupkan konsol ini.
Perannya tiga lapis:

## 1. Dev-server + build (Vite 7)

- `pnpm dev` → Vite melayani React di `http://localhost:5175` dengan HMR.
- `pnpm build` → `tsc --noEmit && vite build` (cek tipe strict dulu, baru
  bundle ke `dist/`).
- Tanpa Node, tidak ada yang bisa menjalankan/membangun frontend.

## 2. Proxy pengujian sisi server (`server/proxy.js`)

Ini alasan arsitektur memakai Node, bukan sekadar "server statis":

| Kemampuan | Kenapa harus Node sisi server |
| --- | --- |
| Header IP virtual (`CF-Connecting-IP`, `X-Forwarded-For`, `X-Real-IP`, `X-Device-Id`) | Browser **melarang** JS menyetelnya (forbidden headers) — hanya kode server yang boleh |
| Path mentah (`../../etc/passwd`) | `node:http` mengirim apa adanya; `fetch`/browser menormalisasi URL |
| Multipart sungguhan | Membangun `multipart/form-data` byte-per-byte untuk uji upload webshell nyata |
| Preview korban | `GET /api/preview-victim`: fetch sebagai IP penyerang + injeksi `<base href>`, bebas `X-Frame-Options` |

## 3. Batasan peran (yang BUKAN kerjaan Node)

- ❌ Bukan database — tidak menyimpan apa pun (state di `localStorage` browser).
- ❌ Bukan WAF / bukan target — targetnya Laravel (`:8000`/`:8001`).
- ❌ Bukan otomasi headless — untuk uji regresi massal, fuzzing, dan
  pengukuran latensi presisi, lihat `phyton3.md` (Python).

Ringkasan satu kalimat: **Node menghidupkan UI sekaligus menjadi "tangan"
yang boleh memalsukan header — sesuatu yang JavaScript browser dilarang
melakukannya.**
