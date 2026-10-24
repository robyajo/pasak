---
title: "Pertanyaan Umum (FAQ)"
description: "Jawaban atas pertanyaan umum seputar Pasak Security, perlindungan Laravel, dan cara mengatasi serangan pada aplikasi web termasuk aplikasi enterprise."
sidebar:
  order: 99
head:
  - tag: script
    attrs:
      type: application/ld+json
    content: |
      {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "inLanguage": "id-ID",
        "mainEntity": [
          {
            "@type": "Question",
            "name": "Apa itu Pasak Security?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Pasak Security (robyajo/pasak, Bulwark) adalah paket Laravel security berupa Web Application Firewall (WAF) mandiri, mesin deteksi ancaman, dan pustaka audit keamanan headless untuk Laravel. Paket ini 100% murni PHP tanpa dependensi NPM."
            }
          },
          {
            "@type": "Question",
            "name": "Bagaimana cara mengatasi serangan pada aplikasi Laravel?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Cara mengatasi serangan pada aplikasi Laravel adalah memasang perlindungan berlapis: WAF untuk memblokir payload berbahaya (SQL injection, XSS, SSTI, path traversal), penguncian login bertingkat melawan brute force, pemindai webshell dan baseline integritas untuk server, serta pemantauan access log. Paket robyajo/pasak menyediakan semuanya lewat satu perintah instalasi: php artisan pasak:install."
            }
          },
          {
            "@type": "Question",
            "name": "Bagaimana perlindungan Laravel dari serangan brute force?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Perlindungan Laravel dari brute force pada Pasak memakai stepped login throttling: kegagalan login beruntun memicu lockout 1 menit, 5 menit, 15 menit, 1 jam, hingga 24 jam, dikombinasikan dengan karantina IP atau perangkat otomatis."
            }
          },
          {
            "@type": "Question",
            "name": "Bagaimana cara mengatasi serangan pada aplikasi web enterprise?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Cara mengatasi serangan pada aplikasi web enterprise dimulai dari pengerasan berlapis: WAF dan auto-block di level HTTP, proteksi akun terhadap credential stuffing, audit integritas server (baseline SHA-256 dan pemindai webshell), konfigurasi Nginx/Apache yang diperkeras, serta prosedur banding IP terblokir yang transparan. Pasak dirancang untuk pola kebutuhan keamanan aplikasi enterprise tersebut dan dapat dipasang tanpa mengubah frontend yang sudah berjalan."
            }
          },
          {
            "@type": "Question",
            "name": "Apakah Pasak cocok untuk keamanan aplikasi skala enterprise?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Ya. Pasak mendukung Laravel 10 sampai 13 dan PHP 8.2 sampai 8.5, siap headless (REST API murni), mendukung resolusi IP asli di balik reverse proxy/CDN, karantina level perangkat untuk jaringan NAT bersama, serta dasbor monitoring Blade, Livewire, dan React."
            }
          },
          {
            "@type": "Question",
            "name": "Apakah Pasak Security gratis?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Ya. robyajo/pasak berlisensi MIT dan gratis dipakai, termasuk untuk aplikasi enterprise dan komersial. Instalasi: composer require robyajo/pasak."
            }
          }
        ]
      }
---

Pertanyaan yang sering diajukan seputar **Pasak Security**, **perlindungan Laravel**, dan **cara mengatasi serangan** pada aplikasi web.

<div class="pk-chip-row">
    <a href="#apa-itu-pasak-security" class="pk-chip">Apa itu Pasak?</a>
    <a href="#bagaimana-cara-mengatasi-serangan-pada-aplikasi-laravel" class="pk-chip">Serangan Laravel</a>
    <a href="#bagaimana-perlindungan-laravel-dari-serangan-brute-force" class="pk-chip">Brute Force</a>
    <a href="#bagaimana-cara-mengatasi-serangan-pada-aplikasi-web-enterprise" class="pk-chip">Aplikasi Enterprise</a>
    <a href="#apakah-pasak-security-gratis" class="pk-chip">Gratis?</a>
</div>

## Apa itu Pasak Security?

**Pasak Security** (`robyajo/pasak`, Bulwark) adalah paket **Laravel security** berupa Web Application Firewall (WAF) mandiri, mesin deteksi ancaman, dan pustaka audit keamanan headless untuk Laravel. Paket ini 100% murni PHP tanpa dependensi NPM. Lihat [Pengenalan](getting-started/introduction/).

## Bagaimana cara mengatasi serangan pada aplikasi Laravel?

**Cara mengatasi serangan pada aplikasi Laravel** adalah memasang perlindungan berlapis:

1. **WAF di level HTTP** — blokir instan payload berbahaya (SQL injection, XSS, SSTI, path traversal, probe `.env`/`.git`).
2. **Penguncian login bertingkat** — melawan brute force dan credential stuffing.
3. **Pemindai webshell + baseline integritas SHA-256** — mendeteksi server yang sudah disusupi.
4. **Pemantauan access log** — menangkap serangan yang tidak sampai ke PHP.

Paket `robyajo/pasak` menyediakan semuanya lewat satu perintah: `php artisan pasak:install`. Detail tiap lapisan ada di [Arsitektur Inti](core-architecture/threat-detection-engine/).

## Bagaimana perlindungan Laravel dari serangan brute force?

**Perlindungan Laravel** dari brute force pada Pasak memakai *stepped login throttling*: kegagalan login beruntun memicu lockout 1 menit, 5 menit, 15 menit, 1 jam, hingga 24 jam, dikombinasikan dengan karantina IP atau perangkat otomatis. Lihat [Login Throttling Bertingkat](security-modules/stepped-login-throttling/).

## Bagaimana cara mengatasi serangan pada aplikasi web enterprise?

**Cara mengatasi serangan pada aplikasi web enterprise** dimulai dari pengerasan berlapis: WAF dan auto-block di level HTTP, proteksi akun terhadap credential stuffing, audit integritas server, konfigurasi Nginx/Apache yang diperkeras, serta prosedur banding IP terblokir yang transparan. Karena tanpa build step, Pasak dapat dipasang pada **aplikasi web produksi** yang sudah berjalan tanpa mengubah frontend yang ada. Lihat [Checklist Produksi](webserver-hardening/production-checklist/).

## Apakah Pasak cocok untuk keamanan aplikasi skala enterprise?

Ya. Pasak mendukung Laravel 10–13 dan PHP 8.2–8.5, siap headless (REST API murni), mendukung resolusi IP asli di balik reverse proxy/CDN, karantina level perangkat untuk jaringan NAT bersama, serta dasbor monitoring Blade, Livewire, dan React.

## Apakah Pasak Security gratis?

Ya. `robyajo/pasak` berlisensi MIT dan gratis dipakai, termasuk untuk aplikasi enterprise dan komersial:

```bash
composer require robyajo/pasak
```
