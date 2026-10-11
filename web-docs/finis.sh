#!/usr/bin/env bash

# ==============================================================================
# VPS Build & Auto-Deploy Helper for Laravel Security Monitor Docs (Astro)
# Repository: robyajo/web-laravel-security
#
# Masalah yang diselesaikan skrip ini:
#   Saat 'pnpm run build' dijalankan di VPS, skrip 'sync-package.mjs' memodifikasi:
#     - src/content/docs/changelog.md
#     - src/data/package.json
#   Akibatnya, saat rilis baru di-push dan 'git pull' dijalankan di VPS, Git error:
#     "error: Your local changes would be overwritten by merge"
#
# Skrip ini secara otomatis:
#   1. Membersihkan perubahan lokal pada berkas hasil auto-generate (conflict-free).
#   2. Menarik (git pull) pembaruan kode terbaru dari origin/main.
#   3. Memasang dependensi (pnpm install) bila diperlukan.
#   4. Menyinkronkan ulang CHANGELOG.md & versi Packagist terbaru secara bersih.
#   5. Melakukan kompilasi 'pnpm run build' (Astro statis + indeks Pagefind).
#   6. (Opsional) Menyalin hasil build dist/ ke direktori Nginx & reload web server.
# ==============================================================================

set -e

# Warna Terminal
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Helper Functions
print_header() {
    echo -e "${BLUE}======================================================================${NC}"
    echo -e "${BOLD}${CYAN}  🚀 Laravel Security Monitor Docs — VPS Build & Deploy Helper${NC}"
    echo -e "${BLUE}======================================================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${CYAN}ℹ $1${NC}"
}

# Tampilkan Bantuan
show_help() {
    print_header
    echo -e "${BOLD}PENGGUNAAN:${NC}"
    echo -e "  ./finis.sh [OPSI]"
    echo ""
    echo -e "${BOLD}OPSI:${NC}"
    echo -e "  -b, --branch <nama>    Nama branch git target (default: branch aktif atau main)"
    echo -e "  -d, --deploy <path>    Direktori web server tujuan (default: /var/www/laravel-security-monitor-docs)"
    echo -e "  --no-pull              Lewati tahap git pull (hanya sync & build lokal)"
    echo -e "  --no-deploy            Lewati penyalinan ke direktori web server (hanya build dist/)"
    echo -e "  --hard-reset           Reset keras seluruh repositori lokal sebelum pull (git reset --hard)"
    echo -e "  -h, --help             Tampilkan bantuan ini"
    echo ""
    echo -e "${BOLD}CONTOH:${NC}"
    echo -e "  ./finis.sh"
    echo -e "  ./finis.sh --deploy /var/www/html/docs"
    echo -e "  ./finis.sh --no-deploy"
    echo -e "  ./finis.sh --hard-reset"
    exit 0
}

# Direktori kerja saat ini
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Pastikan berada di dalam folder Astro web
if [ ! -f "astro.config.mjs" ] || [ ! -f "package.json" ]; then
    print_error "Skrip ini harus dijalankan di dalam folder web dokumentasi Astro (tempat astro.config.mjs berada)!"
    exit 1
fi

# Parsing Argumen
BRANCH_NAME=""
TARGET_DIR=""
SKIP_PULL=false
SKIP_DEPLOY=true
HARD_RESET=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        -b|--branch)
            BRANCH_NAME="$2"
            shift 2
            ;;
        -d|--deploy)
            TARGET_DIR="$2"
            SKIP_DEPLOY=false
            shift 2
            ;;
        --no-pull)
            SKIP_PULL=true
            shift
            ;;
        --no-deploy)
            SKIP_DEPLOY=true
            shift
            ;;
        --hard-reset)
            HARD_RESET=true
            shift
            ;;
        -h|--help)
            show_help
            ;;
        *)
            print_warning "Argumen tidak dikenal: $1"
            shift
            ;;
    esac
done

print_header

# Deteksi branch saat ini jika tidak dispesifikasikan
if git rev-parse --is-inside-work-tree > /dev/null 2>&1; then
    CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "main")
    TARGET_BRANCH="${BRANCH_NAME:-$CURRENT_BRANCH}"
    IS_GIT_REPO=true
else
    IS_GIT_REPO=false
    SKIP_PULL=true
fi

# ==============================================================================
# LANGKAH 1: BERSIHKAN FILE GENERATE LOKAL & GIT PULL
# ==============================================================================
if [ "$IS_GIT_REPO" = true ] && [ "$SKIP_PULL" = false ]; then
    echo -e "${BOLD}${CYAN}[1/5] Memeriksa & Mengamankan Repositori Git...${NC}"

    if [ "$HARD_RESET" = true ]; then
        print_warning "Melakukan reset keras ke status HEAD terakhir (git reset --hard)..."
        git reset --hard HEAD
        git clean -fd -e node_modules -e dist
    else
        # Kembalikan file auto-generate yang sering menimbulkan konflik saat pull
        print_info "Mengembalikan berkas auto-generate agar git pull tidak bentrok..."
        git checkout -- src/content/docs/changelog.md 2>/dev/null || true
        git checkout -- src/data/package.json 2>/dev/null || true
    fi

    # Lakukan git pull
    print_info "Menjalankan git pull origin ${BOLD}${TARGET_BRANCH}${NC}..."
    if git pull origin "$TARGET_BRANCH"; then
        print_success "Pembaruan kode berhasil diambil dari remote!"
    else
        print_warning "Git pull mengalami kendala. Mencoba stashing darurat..."
        git stash --include-untracked
        git pull origin "$TARGET_BRANCH"
        print_success "Pembaruan berhasil diambil setelah stashing."
    fi
    echo ""
else
    echo -e "${BOLD}${CYAN}[1/5] Melewatkan git pull (mode --no-pull atau bukan direktori git)...${NC}"
    echo ""
fi

# ==============================================================================
# LANGKAH 2: PERIKSA & INSTAL DEPENDENSI (PNPM)
# ==============================================================================
echo -e "${BOLD}${CYAN}[2/5] Memeriksa Dependensi Node.js (pnpm)...${NC}"

# Pastikan pnpm tersedia sebelum melanjutkan.
if ! command -v pnpm >/dev/null 2>&1; then
    print_error "pnpm tidak ditemukan. Aktifkan dengan 'corepack enable' atau 'npm install -g pnpm'."
    exit 1
fi

if [ ! -d "node_modules" ]; then
    print_info "Folder node_modules tidak ditemukan. Menjalankan 'pnpm install --frozen-lockfile'..."
    pnpm install --frozen-lockfile || pnpm install
    print_success "Dependensi berhasil dipasang."
else
    # Cek apakah pnpm-lock.yaml lebih baru dari node_modules
    if [ "pnpm-lock.yaml" -nt "node_modules" ]; then
        print_info "Ada perubahan pada pnpm-lock.yaml. Menjalankan 'pnpm install --frozen-lockfile'..."
        pnpm install --frozen-lockfile || pnpm install
        print_success "Dependensi berhasil diperbarui."
    else
        print_success "Dependensi node_modules sudah mutakhir."
    fi
fi
echo ""

# ==============================================================================
# LANGKAH 3: SINKRONKAN ULANG CHANGELOG & METADATA PAKET
# ==============================================================================
echo -e "${BOLD}${CYAN}[3/5] Sinkronisasi Versi, Metadata & Changelog Terbaru...${NC}"
if [ -f "scripts/sync-package.mjs" ]; then
    node scripts/sync-package.mjs
    print_success "Metadata paket dan Changelog berhasil disinkronkan."
else
    print_warning "scripts/sync-package.mjs tidak ditemukan, dilewati."
fi
echo ""

# ==============================================================================
# LANGKAH 4: BUILD ASET STATIS ASTRO
# ==============================================================================
echo -e "${BOLD}${CYAN}[4/5] Mengompilasi Situs Dokumentasi Astro (pnpm run build)...${NC}"
pnpm run build
print_success "Kompilasi selesai! Hasil build tersedia di folder 'dist/'."
echo ""

# ==============================================================================
# LANGKAH 5: DEPLOY KE DIREKTORI WEB SERVER (OPSIONAL)
# ==============================================================================
echo -e "${BOLD}${CYAN}[5/5] Distribusi & Penerapan Web Server...${NC}"

CURRENT_REAL_DIR=$(pwd -P)
TARGET_REAL_DIR=""
if [ -d "$TARGET_DIR" ]; then
    TARGET_REAL_DIR=$(cd "$TARGET_DIR" && pwd -P 2>/dev/null || echo "")
fi

if [ "$SKIP_DEPLOY" = true ]; then
    print_info "Melewatkan deployment (--no-deploy aktif). Situs siap di folder './dist'."
elif [ -n "$TARGET_REAL_DIR" ] && [ "$CURRENT_REAL_DIR" = "$TARGET_REAL_DIR" ]; then
    print_info "TARGET_DIR sama dengan folder repositori saat ini. Tidak perlu rsync (mencegah penimpaan berkas sumber)."
elif [ -n "$TARGET_REAL_DIR" ] && [ "$CURRENT_REAL_DIR/dist" = "$TARGET_REAL_DIR" ]; then
    print_info "Folder dist/ saat ini sudah merupakan root web server. Tidak perlu sinkronisasi file."
elif [ -n "$TARGET_DIR" ] && ([ -d "$TARGET_DIR" ] || mkdir -p "$TARGET_DIR" 2>/dev/null); then
    print_info "Menyinkronkan isi folder dist/ ke ${BOLD}${TARGET_DIR}${NC}..."
    if command -v rsync >/dev/null 2>&1; then
        rsync -a --delete dist/ "$TARGET_DIR/"
    else
        cp -r dist/* "$TARGET_DIR/"
    fi
    print_success "Berkas berhasil disinkronkan ke $TARGET_DIR."

    # Cek & reload Nginx jika service aktif dan user memiliki akses
    if command -v nginx >/dev/null 2>&1; then
        if sudo -n systemctl reload nginx 2>/dev/null || systemctl reload nginx 2>/dev/null; then
            print_success "Web server Nginx berhasil di-reload secara otomatis."
        else
            print_info "Catatan: Jalankan 'sudo systemctl reload nginx' jika konfigurasi Nginx berubah."
        fi
    fi
else
    print_warning "Folder tujuan $TARGET_DIR tidak dapat diakses/dibuat tanpa sudo."
    print_info "Silakan salin manual: sudo rsync -a --delete dist/ $TARGET_DIR/"
fi

echo ""
echo -e "${GREEN}======================================================================${NC}"
echo -e "${BOLD}${GREEN}  ✨ Selesai! Dokumentasi berhasil di-update & di-build tanpa konflik.${NC}"
echo -e "${GREEN}======================================================================${NC}"
