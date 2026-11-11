#!/usr/bin/env bash
#
# reinstall-package.sh — Hapus total instalasi lama dan pasang ulang Pasak.
#
# Saat dijalankan, skrip akan selalu menanyakan target pengujian:
#   - local : Path repository (symlink live ../../)
#   - prod  : Unduh rilis resmi dari Packagist

set -euo pipefail

cd "$(dirname "$0")"

clear 2>/dev/null || true

echo "========================================================"
echo "  PILIHAN TARGET PENGUJIAN PASAK"
echo "========================================================"
echo "  1) local  — Path repository (symlink live ../../)"
echo "              Kode di pasak/src langsung aktif seketika"
echo "              tanpa perlu commit / push / tunggu Packagist."
echo ""
echo "  2) prod   — Unduh rilis resmi dari Packagist"
echo "              Menguji paket publik seperti pengguna umum."
echo "========================================================"
printf "Pilih mode pengujian (local / prod) [default: local]: "
read -r USER_INPUT || USER_INPUT="local"

# Normalisasi input (lowercase dan trim)
USER_INPUT="$(echo "${USER_INPUT:-local}" | tr '[:upper:]' '[:lower:]' | xargs)"

if [ "$USER_INPUT" = "2" ] || [ "$USER_INPUT" = "prod" ] || [ "$USER_INPUT" = "production" ] || [ "$USER_INPUT" = "p" ]; then
    MODE="production"
    echo ""
    echo ">> Memilih target: [PRODUCTION] Packagist"
else
    MODE="local"
    echo ""
    echo ">> Memilih target: [LOKAL] Symlink Live (../../)"
fi

echo "========================================================"
echo ""

echo "== [1/5] Mencopot instalasi lama (Clean Uninstall) =="

# Jalankan pasak:uninstall bila command tersedia
if php artisan list --raw 2>/dev/null | grep -q "^pasak:uninstall"; then
    php artisan pasak:uninstall --force --no-interaction || true
fi

# Pastikan seluruh berkas publikasi dan sisa backup dibersihkan total
rm -f config/pasak.php config/security.php
rm -f routes/pasak.php routes/pasak-api.php

# Bersihkan baris penyertaan rute yatim di routes/web.php dan routes/api.php
php -r '
foreach (["routes/web.php", "routes/api.php"] as $f) {
    if (file_exists($f)) {
        $c = file_get_contents($f);
        $c = preg_replace("/^[ \t]*(?:require|require_once|include|include_once)\s+__DIR__\s*\.\s*[\x27\"]\/(?:pasak|pasak-api|security|security-api)\.php[\x27\"]\s*;[ \t]*\r?\n?/m", "", $c);
        file_put_contents($f, $c);
    }
}
' 2>/dev/null || true
rm -f resources/views/errors/blocked.blade.php
rm -rf resources/views/pages/pasak
rm -f nginx.conf apache2.conf
rm -rf .agents/skills/pasak

# Hapus sisa migrasi pasak
rm -f database/migrations/*_pasak_*.php database/migrations/*_prefix_pasak_*.php database/migrations/*_security_*.php 2>/dev/null || true

# Bersihkan sisa berkas backup jika ada
find routes resources/views database -name "*backup-*" -exec rm -rf {} + 2>/dev/null || true

echo "== [2/5] Membersihkan tabel database Pasak =="

# Drop tabel-tabel pasak & security dan bersihkan dari tabel migrations
php -r "
try {
    require __DIR__ . '/vendor/autoload.php';
    \$app = require_once __DIR__ . '/bootstrap/app.php';
    \$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
    \$kernel->bootstrap();

    \$tables = [
        'pasak_logs', 'pasak_blocked_ips', 'pasak_allowed_ips', 'pasak_settings',
        'pasak_user_logins', 'pasak_trusted_ips', 'pasak_baselines', 'pasak_tickets',
        'security_logs', 'security_blocked_ips', 'security_allowed_ips', 'security_settings',
        'security_user_logins', 'security_trusted_ips', 'security_baselines', 'security_tickets'
    ];

    foreach (\$tables as \$table) {
        Illuminate\Support\Facades\Schema::dropIfExists(\$table);
    }

    if (Illuminate\Support\Facades\Schema::hasTable('migrations')) {
        Illuminate\Support\Facades\DB::table('migrations')
            ->where('migration', 'like', '%pasak%')
            ->orWhere('migration', 'like', '%security%')
            ->delete();
    }
    echo \"  ✓ Tabel dan rekaman migrasi Pasak dibersihkan dari database.\n\";
} catch (\Throwable \$e) {
    echo \"  - Catatan DB: \" . \$e->getMessage() . \"\n\";
}
" || true

echo "== [3/5] Menyiapkan paket Composer =="

if [ "$MODE" = "local" ]; then
    echo "  -> Mengonfigurasi path-repository (symlink live ../../)..."
    php -r '
    $file = "composer.json";
    $c = json_decode(file_get_contents($file), true);
    $c["repositories"] = [[
        "name" => "pasak-path",
        "type" => "path",
        "url" => "../../",
        "options" => [
            "symlink" => true
        ]
    ]];
    file_put_contents($file, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    '
    composer require "robyajo/pasak:@dev" --no-interaction
else
    echo "  -> Menghapus path-repository dan mengunduh dari Packagist..."
    php -r '
    $file = "composer.json";
    $c = json_decode(file_get_contents($file), true);
    unset($c["repositories"]);
    file_put_contents($file, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    '
    composer clear-cache
    composer require robyajo/pasak --no-interaction
fi

echo "== [4/5] Memasang instalasi Pasak baru (pasak:install) =="

php artisan pasak:install \
    --force \
    --with-dashboard \
    --migrate \
    --lang=id \
    --no-interaction \
    "$@"

echo "== [5/5] Membersihkan cache framework (Optimize Clear) =="

php artisan optimize:clear

echo ""
echo "========================================================"
if [ "$MODE" = "local" ]; then
    echo "  SUKSES: Pasak LOKAL terpasang aktif via SYMLINK (../../)!"
    echo "  Setiap perubahan di pasak/src langsung aktif seketika."
else
    echo "  SUKSES: Pasak PRODUCTION terpasang aktif dari PACKAGIST!"
fi
echo "========================================================"
echo "  Cek cepat rute dasbor:"
php artisan route:list --path=pasak 2>/dev/null | head -6 || true
echo "========================================================"
