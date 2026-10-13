# Peran Python3 — Konsol Uji WAF (app-waf)

Python3 **belum dipakai** konsol hari ini (semua berjalan di Node + browser).
Perannya adalah **otomasi headless masa depan**: skrip tanpa GUI yang menembak
endpoint lab yang sama (`:8000` rentan vs `:8001` ber-WAF) untuk lima pekerjaan
yang tidak akurat/nyaman lewat sekali-klik browser:

## 1. Uji regresi vektor (pengganti klik manual)

- Kirim 91+ katalog vektor sekuensial/paralel (`requests`/`httpx`).
- Verifikasi: ber-WAF → 403, tanpa WAF → respons beda. Dijalankan sebelum
  push perubahan kode WAF — jaring pengaman regresi.

## 2. Concurrency & rate limiting

- `asyncio` + `httpx`: mis. 50 request / 2 detik ke endpoint login.
- Verifikasi: stepped throttling + karantina IP/perangkat naik tingkat dengan
  benar, tanpa race condition.

## 3. Fuzzing struktur input

- Mutasi header, chunked encoding, query string aneh, karakter non-standar.
- Verifikasi: parser WAF fail-secure — tanpa HTTP 500, hang, atau ReDoS.

## 4. Deviasi waktu / latensi

- Ukur round-trip presisi milidetik (`time.perf_counter`) per payload.
- Verifikasi: overhead inspeksi < 5–10 ms; tak ada regex yang degradasi
  performa pada payload tertentu (termasuk payload SLEEP blind).

## 5. Integritas header & spoofing

- Variasi `X-Forwarded-For` / `X-Real-IP` / device (IP privat, acak, multi-hop).
- Verifikasi: resolver IP middleware membaca klien yang sah dan tak terkecoh
  header palsu dari pihak tak tepercaya.

## Implementasi: `scripts/waf_probe.py` (sudah jalan)

Harness stdlib-only (tanpa `pip install`): `waf_vectors.py` memuat 91 vektor
langsung dari `src/lib/vectors.ts`, `waf_probe.py` menembakkannya paralel.

```bash
python3 scripts/waf_probe.py --plain http://127.0.0.1:8000 --waf http://127.0.0.1:8001
python3 scripts/waf_probe.py --waf http://127.0.0.1:8001 --jobs 16 --spoof --fuzz
python3 scripts/waf_probe.py --waf http://127.0.0.1:8001 --only-group ssti
```

Metodologi kunci: **IP + device unik per vektor** (auto-block satu vektor tak
mencemari lain), **dua tembakan identitas sama** (ke-1 = instant-block, ke-2 =
menangkap auto-block), ekspektasi sadar taksonomi (instant → wajib 403;
rules → monitor-by-design). Keluar kode 1 bila ada temuan + laporan
**`report/laporan-waf-*.json`**.

## Pintu masuk: `./start.sh`

```bash
./start.sh                                     # probe bawaan + pnpm dev
./start.sh --waf http://127.0.0.1:8001 --only-group ssti
./start.sh --no-probe                          # langsung pnpm dev
```

Alur: verifikasi perkakas → `waf_probe.py` (hasil ke `report/`) → `pnpm dev`
di foreground. Probe butuh kedua target Laravel hidup (`:8000` + `:8001`).

Hasil nyata pertama (88/91): `ffuf UA` lolos (signature tak kenal UA asli
ffuf), `/.htaccess` bocor statis via `artisan serve`, dan satu celah poison
UA — plus bukti resolver IP solid (0 spoof bypass) dan fuzz 4/4 fail-secure.

## Pembagian peran vs Node

| Aspek | Node (ada sekarang) | Python3 (roadmap) |
| --- | --- | --- |
| Interaksi | Manual via browser, visual, edukatif | Otomatis headless, berulang |
| Kecepatan kirim | Satu per klik | Ratusan/detik paralel |
| Presisi waktu | ms kasar (dev) | Presisi profiler |
| Laporan | JSON via UI (salin/unduh) | JSON/CI, assert pass-fail |
| Spoofing header | Ya (via proxy Node) | Ya (langsung via `httpx`) |

Ringkasan satu kalimat: **Node untuk melihat dan membuktikan di depan mata;
Python untuk menguji berulang-ulang tanpa menatap layar.**
