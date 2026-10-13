#!/usr/bin/env bash
#
# update-package.sh — sinkronisasi aset ter-publish robyajo/pasak ke app uji ini.
#
# Konteks: composer memakai path-repository (symlink ke ../../), sehingga kode
# PHP paket selalu live. Yang perlu disinkronkan hanyalah aset ter-publish:
# migrasi, rute, view dashboard Blade, dan cache framework. Itu yang dikerjakan
# perintah `pasak:upgrade` di bawah (dengan --no-composer karena tidak ada yang
# perlu diunduh dari packagist). Kustomisasi host dicadangkan otomatis
# (*.backup-*) sebelum ditimpa.
#
# Cara pakai:
#   ./update-package.sh                    # sinkron penuh + migrasi + clear cache
#   ./update-package.sh --no-migrate       # sinkron tanpa menjalankan migrasi
#
# Cron tiap 5 menit (opsional) — hasil dicatat ke log:
#   */5 * * * * cd /home/roby/PROJECT/PACKAGE/PHP/LARAVEL-SECURITY/pasak/ring/pasak-yes && ./update-package.sh >> storage/logs/pasak-sync.log 2>&1

set -euo pipefail

cd "$(dirname "$0")"

echo "== [1/2] Sinkron aset via pasak:upgrade =="
php artisan pasak:upgrade \
    --force \
    --no-composer \
    --sync-views \
    --sync-routes \
    --no-interaction \
    "$@"

echo "== [2/2] Membersihkan cache framework =="
php artisan optimize:clear

echo "OK: aset tersinkron dari paket."
