# Struktur & Teknologi — Konsol Uji WAF (app-waf)

## 1. Bingkai teknologi

| Lapisan | Teknologi | Versi | Peran |
| --- | --- | --- | --- |
| Runtime | Node.js | v24 | Menjalankan dev-server + proxy sisi server |
| Manajer paket | pnpm | v11 | Satu-satunya yang dipakai (`pnpm-lock.yaml`) |
| Bundler/dev-server | Vite | v7 | `pnpm dev` (:5175), build produksi, middleware proxy |
| UI | React | v19 | Satu halaman, komponen fungsional + hooks |
| Bahasa | TypeScript | strict (`tsc --noEmit` bagian dari build) | Seluruh `src/` (`.ts`/`.tsx`); `server/proxy.js` tetap JS |
| Styling | Tailwind CSS | v4 | Utility-first; komponen `@/components/ui` (shadcn/base-ui) |
| Ikon | lucide-react | v1 | Seluruh ikon UI |
| State lab | Zustand | v5 (+ `persist`) | `src/stores/lab.ts`: target, IP penyerang, ID perangkat |
| Target lab | Laravel 13 / PHP 8.5 | — | `pasak-no` (:8000, rentan) & `pasak-yes` (:8001, ber-WAF) |

Alur data: **React → `POST /api/proxy` (Vite middleware, `server/proxy.js`,
`node:http` langsung) → target Laravel** dengan header IP/perangkat virtual.
Iframe preview lewat `GET /api/preview-victim` (fetch sisi server + injeksi
`<base href>`). Tanpa database, tanpa backend framework.

## 2. Peta struktur → teknologi

```text
server/proxy.js          Node http/https murni (tanpa fetch agar path mentah
                         tidak dinormalisasi) + multipart builder manual
src/hooks/               React hooks (useWafConsole: state + fetch)
src/stores/lab.ts        Zustand + persist (localStorage)
src/lib/                 TypeScript murni (katalog, analisis, generator IP)
src/components/ui/       shadcn/base-ui + Tailwind v4 + CVA
```

## 3. Roadmap otomasi Python (ringkasan)

Konsol hari ini manual via browser. Lima pekerjaan berikut dirancang sebagai
**skrip Python headless** (tanpa GUI) yang menembak endpoint lab yang sama —
murni alat uji, bukan bagian konsol:

| # | Nama | Inti fungsi | Yang diverifikasi |
| --- | --- | --- | --- |
| 1 | **Uji regresi vektor** | Kirim 91+ katalog vektor sekuensial/paralel | Target ber-WAF → 403; tanpa WAF → beda respons; cocok untuk cek pra-push |
| 2 | **Concurrency & rate limit** | `asyncio` + `httpx`: mis. 50 request / 2 detik | Throttle login & karantina IP/perangkat tanpa race condition |
| 3 | **Fuzzing struktur input** | Mutasi header, chunk encoding, query aneh | Parser WAF fail-secure (tanpa 500/hang/ReDoS) |
| 4 | **Deviasi waktu/latensi** | Ukur round-trip presisi ms per payload | Overhead inspeksi < 5–10 ms; tak ada regex yang degradasi |
| 5 | **Integritas header & spoofing** | Variasi `X-Forwarded-For`/`X-Real-IP`/device (privat, acak, multi-hop) | Resolver IP middleware tak terkecoh header palsu |

Prinsip bersama: tembak `http://localhost:8000` (rentan, kontrol) vs
`http://localhost:8001` (ber-WAF, perlakuan), bandingkan status + body —
hasilnya melengkapi (bukan mengganti) laporan JSON konsol.
