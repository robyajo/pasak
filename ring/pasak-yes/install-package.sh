#!/usr/bin/env bash
#
# install-package.sh — pasang (ulang) robyajo/pasak ke app uji ini untuk pengujian.
#
# Konteks: composer memakai path-repository (symlink ke ../../), sehingga
# `composer require` tidak mengunduh apa pun dari packagist, lalu wizard
# `pasak:install` mempublikasikan seluruh aset (config, migrasi, rute,
# middleware, trait User, .env, .htaccess, dashboard Blade).
#
# Cara pakai:
#   ./install-package.sh                   # instal penuh + dashboard + migrasi
#   ./install-package.sh --api-only        # varian API-only (diteruskan ke wizard)
#   ./install-package.sh --without-views   # contoh flag wizard lain (diteruskan)
#
# Pasangan: ./uninstall-package.sh (bersihkan total) — siklus uji:
#   ./uninstall-package.sh && ./install-package.sh

set -euo pipefail

cd "$(dirname "$0")"

echo "== [1/4] Memasang paket via Composer (path-repository, lokal) =="
if composer show robyajo/pasak >/dev/null 2>&1; then
    echo "-- paket sudah terpasang, lanjut ke wizard instalasi."
else
    composer require "robyajo/pasak:@dev" --no-interaction
fi

echo "== [2/4] Menjalankan wizard pasak:install =="
php artisan pasak:install \
    --force \
    --migrate \
    --lang=id \
    --with-dashboard \
    --no-interaction \
    "$@"

echo "== [3/4] Migrasi database =="
php artisan migrate --force

echo "== [4/4] Membersihkan cache framework =="
php artisan optimize:clear

echo "OK: Pasak terpasang. Cek cepat:"
php artisan route:list --path=pasak 2>/dev/null | head -5 || true
