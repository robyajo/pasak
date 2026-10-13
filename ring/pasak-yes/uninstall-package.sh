#!/usr/bin/env bash
#
# uninstall-package.sh — copot TOTAL robyajo/pasak dari app uji ini untuk pengujian.
#
# Menghapus: tabel DB + record migrasi Pasak, rute, middleware, trait User,
# variabel PASAK_* (.env), .htaccess Pasak, file publish (config, view,
# migrasi), file *.backup-*, lalu melepas paket via Composer.
#
# Cara pakai:
#   ./uninstall-package.sh                 # copot total + migrasi + composer install
#   ./uninstall-package.sh --keep-tables   # pertahankan tabel DB (diteruskan)
#   ./uninstall-package.sh --keep-env      # pertahankan variabel .env (diteruskan)
#
# Pasangan: ./install-package.sh (pasang ulang) — siklus uji:
#   ./uninstall-package.sh && ./install-package.sh

set -euo pipefail

cd "$(dirname "$0")"

echo "== [1/5] Menjalankan pasak:uninstall (dilewati bila paket belum terpasang) =="
if php artisan list --raw 2>/dev/null | grep -q "^pasak:uninstall"; then
    php artisan pasak:uninstall --force --no-interaction "$@"
else
    echo "-- perintah pasak:uninstall tidak ada, paket belum terpasang."
fi

echo "== [2/5] Sanity check bootstrap/app.php =="
php -l bootstrap/app.php

echo "== [3/5] Migrasi database =="
php artisan migrate --force

echo "== [4/5] Memastikan paket lepas + dependensi utuh =="
if composer show robyajo/pasak >/dev/null 2>&1; then
    composer remove robyajo/pasak --no-interaction || true
fi
composer install --no-interaction

echo "== [5/5] Membersihkan cache framework =="
php artisan optimize:clear

echo "OK: Pasak tercopot total. Sisa yang diharapkan NOL:"
grep -rc "^PASAK_" .env .env.example 2>/dev/null || true
