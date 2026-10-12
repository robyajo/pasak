# Rilis 2026-10-13 — TypeScript, shadcn & audit 4 tahap

## TypeScript penuh
- 26 file `src/` → `.ts`/`.tsx` strict, alias `@/*`, `tsc --noEmit` nol error.
- `pnpm build` = `tsc --noEmit && vite build`; `jsconfig.json` dilebur ke `tsconfig.json`.

## shadcn + neobrutalism.dev
- Init preset `b56mLA`, sinkron 20 komponen ke registry resmi neobrutalism.dev,
  styling teal, toggle tema gelap/terang di header (default terang, tersimpan).
- Seluruh UI (18 file) dimigrasi: Card, Badge, Button, Tabs, Table, Alert,
  Dialog, Field, ToggleGroup, Select, Sheet, Accordion, Progress, dsb.
- Varian lokal `outline/ghost/destructive/link/secondary` dipertahankan di
  `button.tsx`/`badge.tsx` (ditandai AWAS + aturan AGENTS.md).

## Audit 4 tahap "Tanpa Pasak = Jebol"
- Tahap 1 baseline → 2 recon → 3 injeksi → 4 webshell & vonis, dengan gerbang
  berurutan, stepper, skor, dan vonis final.
- Simulasi IP penyerang (acak/tetap 🎲), preview live korban di kanan
  (auto-refresh + tombol halaman + tab baru + penjelasan bila framing diblokir).
- Kartu upload: link akses langsung, verifikasi file, kartu penyerang mandiri
  (perintah bebas + paket curi data: `.env`, users, kontak).
- Akordeon per kartu: preview JSON + Copy, kerentanan/CWE/celah
  (`src/lib/vulnInfo.ts`), contoh metode hacker — tiap metode bisa
  **dieksekusi** (25+ aksi) dengan hasil + JSON di **Sheet setengah layar**
  (penjelasan aksi, output, JSON, penanganan 🛠️).
- Rantai eskalasi di Sheet (SQLi, SSTI, traversal, XSS) sampai tujuan.
- "Lanjut dengan catatan" untuk temuan infra (mis. 403 Nginx di `/.env`).

## Mode Production
- Switch Lokal ⇄ Produksi di header (dialog konfirmasi AlertDialog),
  target otomatis menyesuaikan, banner peringatan, tahap destruktif dibatasi.
- Vonis produksi mandiri ("PRODUKSI TAK MENOLAK SERANGAN ✅"), eksekusi
  metode produksi via konfirmasi per tindakan, panduan verifikasi manual SSH.

## Perbaikan & peningkatan
- Select mentah → shadcn `Select`; ikon dinormalkan `*Icon`; `Progress`
  indeterminate + `ProgressValue` render-function.
- Perbaikan CWD `DemoShellController` (`chdir(base_path())`) agar perintah
  jalan dari root aplikasi.
- Perbaikan port: preview duplikat di `:5176` dimatikan (dev `:5175` tunggal).
- Penanganan Sheet: `WrenchIcon`, penanganan per kerentanan (Pasak + aplikasi).
- AGENTS.md diselaraskan (struktur `.tsx`, aturan shadcn, AWAS overwrite).

## Verifikasi akhir
- `tsc` nol error, `pnpm build` hijau, uji live: SSTI/XSS/traversal/upload ke
  `:8000` lolos (+RCE `uid=…`), ke `:8001` **403**; produksi
  `dummy-api.lapeh.web.id`: 8/9 lolos + 1 catatan infra.
