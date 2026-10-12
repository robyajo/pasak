#!/usr/bin/env bash

# ==============================================================================
# Git Automated Push & Optional Tagging Script
# Repository: robyajo/pasak
#
# Setiap kali dijalankan, skrip ini SELALU melakukan pengecekan versi:
#   - Versi sekarang    : tag rilis semver terakhir (mis. v1.0.3)
#   - Versi direkomendasi: kenaikan otomatis (major/minor/patch) dihitung dari
#                          commit & perubahan yang belum di-tag
#
# Gunakan "-t <tag>" untuk memakai tag rilis tertentu, atau "--no-tag"
# untuk melewatkan pembuatan tag. Tanpa -t, versi rekomendasi dipakai otomatis.
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
    echo -e "${BOLD}${CYAN}  🛡️  Pasak — Git Push & Tagging Helper${NC}"
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

# ==============================================================================
# PENGECEKAN VERSI
# ==============================================================================

# Ambil tag rilis semver terakhir (diurutkan secara version-aware).
get_current_tag() {
    git tag -l 'v[0-9]*' --sort=-v:refname 2>/dev/null | head -n1
}

# Ambil komponen angka ke-N dari versi (tanpa prefix 'v'); default 0.
_version_part() {
    local part
    part=$(printf '%s' "$1" | cut -d. -f"$2")
    part=${part//[!0-9]/}
    echo "${part:-0}"
}

# Naikkan versi sesuai tipe bump: minor | patch.
# Aturan proyek:
#   - patch hanya sampai .10 (v1.0.9 -> v1.0.10), selebihnya rollover
#     ke minor berikutnya (v1.0.10 -> v1.1.0, v1.1.10 -> v1.2.0).
#   - major (v2.0.0, v3.0.0, ...) TIDAK PERNAH otomatis; hanya bila
#     pemilik memintanya eksplisit via "-t vX.0.0".
bump_version() {
    local version="$1"
    local type="$2"
    local major minor patch

    major=$(_version_part "$version" 1)
    minor=$(_version_part "$version" 2)
    patch=$(_version_part "$version" 3)

    case "$type" in
        minor) minor=$((minor + 1)); patch=0 ;;
        *)
            patch=$((patch + 1))
            if [ "$patch" -gt 10 ]; then
                minor=$((minor + 1)); patch=0
            fi
            ;;
    esac

    echo "${major}.${minor}.${patch}"
}

# Tentukan tipe bump dari Conventional Commits antara tag terakhir dan HEAD.
# Major TIDAK PERNAH otomatis (hanya via "-t vX.0.0" eksplisit): commit
# BREAKING CHANGE / "feat!:" diperlakukan sebagai minor.
detect_bump_type() {
    local range="$1"
    local log

    if [ -n "$range" ] && git rev-parse "$range" >/dev/null 2>&1; then
        log=$(git log "$range" --pretty=format:'%s%n%b' 2>/dev/null || true)
    else
        log=$(git log --pretty=format:'%s%n%b' 2>/dev/null || true)
    fi

    if printf '%s' "$log" | grep -qE 'BREAKING[ _-]?CHANGE|[[:alnum:]]+(\([^)]+\))?!:'; then
        echo "minor"
        return
    fi

    if printf '%s' "$log" | grep -qE '^feat(\([^)]+\))?!?:'; then
        echo "minor"
        return
    fi

    echo "patch"
}

# Hitung & tampilkan versi sekarang vs versi yang direkomendasikan.
report_versions() {
    local ahead uncommitted log_range

    CURRENT_TAG=$(get_current_tag)
    if [ -n "$CURRENT_TAG" ]; then
        CURRENT_VERSION="${CURRENT_TAG#v}"
    else
        CURRENT_VERSION="0.0.0"
    fi

    if [ -n "$CURRENT_TAG" ]; then
        ahead=$(git rev-list --count "${CURRENT_TAG}..HEAD" 2>/dev/null || echo 0)
        log_range="${CURRENT_TAG}..HEAD"
    else
        ahead=$(git rev-list --count HEAD 2>/dev/null || echo 0)
        log_range=""
    fi
    uncommitted=$(git status --porcelain | wc -l | tr -d ' ')

    if [ "$ahead" -eq 0 ] && [ "$uncommitted" -eq 0 ]; then
        BUMP_TYPE="none"
        RECOMMENDED_VERSION="$CURRENT_VERSION"
    else
        BUMP_TYPE=$(detect_bump_type "$log_range")
        RECOMMENDED_VERSION=$(bump_version "$CURRENT_VERSION" "$BUMP_TYPE")
    fi
    RECOMMENDED_TAG="v${RECOMMENDED_VERSION}"

    echo -e "${BOLD}${CYAN}----------------------------------------------------------------------${NC}"
    echo -e "${BOLD}  📦 Pengecekan Versi${NC}"
    echo -e "${BOLD}${CYAN}----------------------------------------------------------------------${NC}"

    if [ -n "$CURRENT_TAG" ]; then
        print_info "Versi sekarang       : ${BOLD}v${CURRENT_VERSION}${NC}  (tag terakhir: ${CURRENT_TAG})"
    else
        print_info "Versi sekarang       : ${BOLD}v${CURRENT_VERSION}${NC}  (belum ada tag rilis)"
    fi

    print_info "Belum di-tag         : ${BOLD}${ahead}${NC} commit, ${BOLD}${uncommitted}${NC} berkas belum di-commit"

    if [ "$BUMP_TYPE" = "none" ]; then
        print_warning "Versi direkomendasi  : ${BOLD}${RECOMMENDED_TAG}${NC} (tidak ada perubahan baru)"
    else
        print_info "Versi direkomendasi  : ${BOLD}${GREEN}${RECOMMENDED_TAG}${NC}  (${BUMP_TYPE} bump)"
    fi
    echo ""
}

# Tampilkan Bantuan
show_help() {
    print_header
    echo -e "${BOLD}PENGGUNAAN:${NC}"
    echo -e "  ./push.sh [OPSI]"
    echo -e "  ./push.sh \"Pesan commit\""
    echo -e "  ./push.sh \"Pesan commit\" \"v1.0.0\""
    echo ""
    echo -e "${BOLD}OPSI:${NC}"
    echo -e "  -m, --message <msg>   Pesan commit git"
    echo -e "  -t, --tag <tag>       Nama tag rilis (opsional, misal: v1.0.0)"
    echo -e "                        Tanpa opsi ini, versi rekomendasi dipakai otomatis"
    echo -e "                        sehingga setiap push selalu menaikkan versi"
    echo -e "  --no-tag              Lewatkan pembuatan tag rilis"
echo -e "  --no-verify           Lewatkan gerbang kualitas pre-push (Pest/PHPStan)"
echo -e "                        Hanya untuk keadaan darurat; CI tetap berjalan"
    echo -e "  --no-docs-build       Lewatkan build dokumentasi web (finis.sh)"
    echo -e "  -b, --branch <name>   Nama branch target (default: branch aktif)"
    echo -e "  -r, --remote <name>   Nama remote git (default: origin)"
    echo -e "  -h, --help            Tampilkan bantuan ini"
    echo ""
    echo -e "${BOLD}CONTOH:${NC}"
    echo -e "  ./push.sh"
    echo -e "  ./push.sh -m \"docs: update installation guide\""
    echo -e "  ./push.sh -m \"release: launch v1.0.0\" -t \"v1.0.0\""
    echo -e "  ./push.sh -m \"fix: patch bug\" --no-tag"
echo -e "  ./push.sh -m \"fix: darurat\" --no-verify --no-tag"
    echo ""
    echo -e "${BOLD}CATATAN:${NC}"
    echo -e "  Pengecekan versi (sekarang vs rekomendasi) SELALU dijalankan."
    exit 0
}

# Parsing Argumen
COMMIT_MSG=""
TAG_NAME=""
NO_TAG=0
NO_VERIFY=0
NO_DOCS_BUILD=0
REMOTE_NAME="origin"
TARGET_BRANCH=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        -m|--message)
            COMMIT_MSG="$2"
            shift 2
            ;;
        -t|--tag)
            TAG_NAME="$2"
            shift 2
            ;;
        --no-tag)
            NO_TAG=1
            shift
            ;;
        --no-verify)
            NO_VERIFY=1
            shift
            ;;
        --no-docs-build)
            NO_DOCS_BUILD=1
            shift
            ;;
        -b|--branch)
            TARGET_BRANCH="$2"
            shift 2
            ;;
        -r|--remote)
            REMOTE_NAME="$2"
            shift 2
            ;;
        -h|--help)
            show_help
            ;;
        *)
            if [ -z "$COMMIT_MSG" ]; then
                COMMIT_MSG="$1"
            elif [ -z "$TAG_NAME" ]; then
                TAG_NAME="$1"
            fi
            shift
            ;;
    esac
done

# Pastikan berada di dalam repositori git
if ! git rev-parse --is-inside-work-tree > /dev/null 2>&1; then
    print_error "Direktori saat ini bukan repositori Git!"
    exit 1
fi

print_header

# Deteksi branch saat ini jika tidak dispesifikasikan
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ -z "$TARGET_BRANCH" ]; then
    TARGET_BRANCH="$CURRENT_BRANCH"
fi

print_info "Remote target : ${BOLD}${REMOTE_NAME}${NC}"
print_info "Branch target : ${BOLD}${TARGET_BRANCH}${NC}"
echo ""

# 0. Pengecekan Versi (WAJIB, selalu dijalankan)
report_versions

# 0b. Sinkronisasi Dokumentasi (WAJIB sebelum commit agar ikut ter-commit)
echo -e "${BOLD}${CYAN}----------------------------------------------------------------------${NC}"
echo -e "${BOLD}  📚 Sinkronisasi Dokumentasi${NC}"
echo -e "${BOLD}${CYAN}----------------------------------------------------------------------${NC}"
if command -v php >/dev/null 2>&1 && [ -f "documents/generate.php" ]; then
    print_info "Regenerasi portal documents/index.html..."
    php documents/generate.php > /dev/null 2>&1 \
        && print_success "documents/index.html mutakhir." \
        || print_warning "Gagal regenerasi documents/index.html; dilanjutkan tanpa itu."
else
    print_warning "PHP / documents/generate.php tidak tersedia; portal docs dilewati."
fi
if [ "$NO_DOCS_BUILD" -eq 1 ]; then
    print_info "Melewatkan build dokumentasi web (--no-docs-build)."
elif [ -f "web-docs/finis.sh" ]; then
    # --no-pull: hindari git pull (baru saja akan push); --no-deploy: hanya build dist/.
    print_info "Menjalankan build dokumentasi web (web-docs/finis.sh --no-pull --no-deploy)..."
    bash web-docs/finis.sh --no-pull --no-deploy > /dev/null 2>&1 \
        && print_success "Build dokumentasi web selesai." \
        || print_warning "Build dokumentasi web gagal; push tetap dilanjutkan."
else
    print_warning "web-docs/finis.sh tidak ditemukan; build docs web dilewati."
fi
echo ""

# 0c. Selaraskan konstanta VERSION paket dengan tag yang akan dibuat,
#     agar VersionCheckService tidak pernah melaporkan versi basi.
PENDING_TAG=""
if [ "$TAG_NAME" != "auto" ] && [ -n "$TAG_NAME" ]; then
    PENDING_TAG="$TAG_NAME"
elif [ "$BUMP_TYPE" != "none" ]; then
    PENDING_TAG="$RECOMMENDED_TAG"
fi
if [ -n "$PENDING_TAG" ] && [ -f "src/Services/PasakService.php" ]; then
    PENDING_VERSION="${PENDING_TAG#v}"
    if grep -q "public const VERSION = '${PENDING_VERSION}';" src/Services/PasakService.php 2>/dev/null; then
        print_info "Konstanta VERSION sudah selaras (${PENDING_VERSION})."
    else
        sed -i '' "s/public const VERSION = '[^']*';/public const VERSION = '${PENDING_VERSION}';/" src/Services/PasakService.php 2>/dev/null \
        || sed -i "s/public const VERSION = '[^']*';/public const VERSION = '${PENDING_VERSION}';/" src/Services/PasakService.php
        print_success "Konstanta VERSION diselaraskan ke ${PENDING_VERSION}."
    fi
fi

# 0d. Gerbang kualitas pre-push (Pest wajib hijau; PHPStan bila tersedia).
#     Gagal = push dibatalkan. Lewati hanya untuk darurat via --no-verify.
echo -e "${BOLD}${CYAN}----------------------------------------------------------------------${NC}"
echo -e "${BOLD}  ✅ Gerbang Kualitas Pre-Push${NC}"
echo -e "${BOLD}${CYAN}----------------------------------------------------------------------${NC}"
if [ "$NO_VERIFY" -eq 1 ]; then
    print_warning "Gerbang kualitas dilewati (--no-verify). CI tetap berjalan di remote."
else
    if [ -x "vendor/bin/pest" ]; then
        print_info "Menjalankan Pest..."
        PEST_OUT=$(./vendor/bin/pest 2>&1)
        PEST_CODE=$?
        printf '%s\n' "$PEST_OUT" | tail -5
        if [ "$PEST_CODE" -ne 0 ]; then
            print_error "Pest gagal; push dibatalkan. Perbaiki dulu atau gunakan --no-verify untuk darurat."
            exit 1
        fi
        print_success "Pest hijau."
    else
        print_warning "vendor/bin/pest tidak ditemukan; gerbang Pest dilewati (jalankan composer install dulu)."
    fi
    if [ -x "vendor/bin/phpstan" ]; then
        print_info "Menjalankan PHPStan..."
        PHPSTAN_OUT=$(./vendor/bin/phpstan analyse --no-progress 2>&1)
        PHPSTAN_CODE=$?
        printf '%s\n' "$PHPSTAN_OUT" | tail -5
        if [ "$PHPSTAN_CODE" -ne 0 ]; then
            print_error "PHPStan gagal; push dibatalkan. Perbaiki dulu atau gunakan --no-verify untuk darurat."
            exit 1
        fi
        print_success "PHPStan hijau."
    else
        print_info "PHPStan tidak terpasang; gerbang PHPStan dilewati (CI tetap memeriksanya)."
    fi
fi
echo ""

# 1. Periksa Status Perubahan File
STATUS_OUTPUT=$(git status --porcelain)

if [ -n "$STATUS_OUTPUT" ]; then
    echo -e "${BOLD}Perubahan berkas yang terdeteksi:${NC}"
    git status -s
    echo ""

    # Jika pesan commit belum diberikan lewat argumen, minta input interaktif
    if [ -z "$COMMIT_MSG" ]; then
        if [ -t 0 ]; then
            echo -ne "${BOLD}Masukkan pesan commit${NC} (default: 'chore: update repository files'): "
            read -r INPUT_MSG
            if [ -n "$INPUT_MSG" ]; then
                COMMIT_MSG="$INPUT_MSG"
            else
                COMMIT_MSG="chore: update repository files"
            fi
        else
            COMMIT_MSG="chore: update repository files"
        fi
    fi

    # Lakukan git add & commit
    echo ""
    print_info "Menambahkan perubahan ke staging (git add -A)..."
    git add -A

    print_info "Membuat commit: \"${COMMIT_MSG}\"..."
    git commit -m "$COMMIT_MSG"
    print_success "Perubahan berhasil di-commit."
else
    print_info "Tidak ada perubahan berkas yang belum di-commit."
fi

# 2. Push ke Remote Git
echo ""
print_info "Mendorong commit ke ${REMOTE_NAME}/${TARGET_BRANCH}..."
git push "$REMOTE_NAME" "$TARGET_BRANCH"
print_success "Commit berhasil di-push ke ${REMOTE_NAME}/${TARGET_BRANCH}!"

# 3. Penanganan Tag (OTOMATIS: setiap push menaikkan versi)
echo ""

# Kata kunci lama 'auto' tetap didukung dan sama dengan perilaku default.
if [ "$TAG_NAME" = "auto" ]; then
    TAG_NAME=""
fi

if [ "$NO_TAG" -eq 1 ]; then
    print_info "Melewatkan pembuatan tag (--no-tag)."
elif [ -z "$TAG_NAME" ]; then
    if [ "$BUMP_TYPE" = "none" ]; then
        print_info "Tidak ada perubahan baru sejak ${CURRENT_TAG:-v0.0.0}; tag versi tidak dibuat."
    else
        TAG_NAME="$RECOMMENDED_TAG"
        print_info "Otomatis menaikkan versi: ${BOLD}${GREEN}${TAG_NAME}${NC} (${BUMP_TYPE} bump)."
    fi
fi

if [ -n "$TAG_NAME" ] && [ "$NO_TAG" -eq 0 ]; then
    # Kebijakan CHANGELOG-first: peringatkan bila tidak ada entri baru sejak tag terakhir.
    if [ -n "$CURRENT_TAG" ] && [ -f "CHANGELOG.md" ]; then
        if git diff --quiet "${CURRENT_TAG}..HEAD" -- CHANGELOG.md 2>/dev/null \
            && git diff --quiet -- CHANGELOG.md 2>/dev/null; then
            print_warning "CHANGELOG.md tidak berubah sejak ${CURRENT_TAG}; pertimbangkan mencatat perubahan sebelum merilis ${TAG_NAME}."
        fi
    fi
    # Jika tag sudah dipakai (lokal maupun remote), naikkan patch
    # terus sampai menemukan nomor versi yang bebas.
    while git rev-parse "$TAG_NAME" >/dev/null 2>&1 \
        || git ls-remote --tags "$REMOTE_NAME" "refs/tags/${TAG_NAME}" 2>/dev/null | grep -q .; do
        print_warning "Tag '${TAG_NAME}' sudah ada; menaikkan patch..."
        RECOMMENDED_VERSION=$(bump_version "${TAG_NAME#v}" "patch")
        TAG_NAME="v${RECOMMENDED_VERSION}"
    done

    print_info "Membuat annotated tag: ${TAG_NAME}..."
    git tag -a "$TAG_NAME" -m "Release $TAG_NAME"

    print_info "Mendorong tag ${TAG_NAME} ke ${REMOTE_NAME}..."
    git push "$REMOTE_NAME" "$TAG_NAME"
    print_success "Tag '${TAG_NAME}' berhasil dibuat dan di-push ke ${REMOTE_NAME}!"

    echo ""
    print_info "🚀 Paket versi ${TAG_NAME} kini siap dideteksi secara otomatis oleh Packagist!"
    echo -e "   Periksa katalog rilis di: ${BLUE}https://packagist.org/packages/robyajo/pasak${NC}"
fi

echo ""
echo -e "${GREEN}======================================================================${NC}"
echo -e "${BOLD}${GREEN}  ✨ Selesai! Seluruh proses Git berhasil dieksekusi dengan lancar.${NC}"
echo -e "${GREEN}======================================================================${NC}"
