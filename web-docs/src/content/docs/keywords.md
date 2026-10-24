---
title: "Indeks Topik & Kata Kunci"
description: "Indeks topik dokumentasi Pasak Security: perlindungan Laravel, cara mengatasi serangan siber, keamanan aplikasi enterprise, WAF, brute force, webshell, dan REST API."
sidebar:
  order: 98
---

Jelajahi dokumentasi **Pasak Security** berdasarkan topik. Setiap kata kunci mengarah ke panduan yang membahas **perlindungan Laravel** dan **cara mengatasi serangan** terkait.

<div class="pk-chip-row">
    <a href="#laravel-security--pasak-security" class="pk-chip pk-chip-sky">Laravel Security</a>
    <a href="#perlindungan-laravel--keamanan-aplikasi" class="pk-chip pk-chip-emerald">Perlindungan Laravel</a>
    <a href="#cara-mengatasi-serangan--aplikasi-enterprise" class="pk-chip pk-chip-amber">Cara Mengatasi Serangan</a>
    <a href="#integrasi--otomasi" class="pk-chip pk-chip-indigo">Integrasi &amp; Otomasi</a>
</div>

## Laravel Security & Pasak Security

- [Pengenalan Pasak Security](getting-started/introduction/) — filosofi dan arsitektur **Laravel security** 100% murni PHP.
- [Instalasi](getting-started/installation/) — memasang **Pasak Security** via Composer dan wizard `pasak:install`.
- [Konfigurasi](getting-started/configuration/) — seluruh variabel `PASAK_*`.
- [Pertanyaan Umum (FAQ)](faq/) — jawaban ringkas seputar **Pasak Security**.

## Perlindungan Laravel & Keamanan Aplikasi

- [Mesin Deteksi Ancaman](core-architecture/threat-detection-engine/) — inti **perlindungan Laravel**: blokir instan dan ambang progresif.
- [Karantina Perangkat](core-architecture/device-quarantine/) — isolasi penyerang di IP/NAT bersama.
- [Resolusi Reverse Proxy](core-architecture/reverse-proxy-resolution/) — IP klien asli di balik proxy/CDN.
- [Login Bertingkat](security-modules/stepped-login-throttling/) — lockout eksponensial anti brute force dan credential stuffing.
- [Keamanan Server & Webshell](security-modules/server-security-and-webshells/) — baseline SHA-256 dan pemindai webshell untuk **keamanan aplikasi**.
- [Sesi & Aktivitas](security-modules/session-and-activity-tracking/) — pantau pengguna aktif dan cabut sesi.

## Cara Mengatasi Serangan & Aplikasi Enterprise

- [Checklist Produksi](webserver-hardening/production-checklist/) — langkah peluncuran aman untuk **aplikasi web enterprise**.
- [Hardening Nginx](webserver-hardening/nginx-hardened-waf/) — rate limit dan eksekusi PHP ketat.
- [Hardening Apache](webserver-hardening/apache-htaccess-hardening/) — pertahanan webshell dan double extension.
- [Access Log Streaming](security-modules/access-log-streaming/) — **cara mengatasi serangan** yang tidak sampai ke PHP.
- [Tiket Banding](security-modules/appeal-tickets-system/) — prosedur unblock IP yang transparan.
- [Aturan Kustom & Whitelist](integration-guides/custom-rules-and-whitelist/) — signature dan subnet CIDR sendiri.

## Integrasi & Otomasi

- [Blade & Livewire](integration-guides/frontend-blade-livewire/) — dasbor monitoring tanpa build step.
- [React & Inertia](integration-guides/frontend-react-inertia/) — dasbor TSX modern.
- [Perintah Artisan](cli-automation/artisan-commands/) — `pasak:install`, `scan-logs`, `baseline`, dan lainnya.
- [Tugas Terjadwal](cli-automation/scheduled-tasks/) — prune log dan heartbeat.
- [REST API](api-reference/api-overview/) — endpoint publik, pengguna, dan admin.
- [Changelog](changelog/) — riwayat rilis paket.
