#!/usr/bin/env bash
#
# start.sh — pintu masuk tunggal konsol uji WAF (app-waf) & Strix AI Pentest.
#
# Saat dijalankan:
#   (1) verifikasi perkakas (Python, pnpm, uv) & siapkan lingkungan Strix AI (strix/)
#   (2) jalankan harness Python (waf_probe.py) agar ada hasil uji terbaru di report/
#   (3) jalankan pnpm dev (konsol web) di foreground — Ctrl+C untuk berhenti.
#
# Cara pakai:
#   ./start.sh                                  # probe bawaan + strix + pnpm dev
#   ./start.sh --plain http://127.0.0.1:8000 --waf http://127.0.0.1:8001 --spoof --fuzz
#   ./start.sh --no-probe                       # langsung pnpm dev, tanpa probe WAF
#   ./start.sh --no-strix                       # lewati inisialisasi lingkungan Strix
#
# Mode local vs production diatur via ENV:
#   local (bawaan):
#     ./start.sh
#   production:
#     PLAIN_URL=https://rentan.contoh.id WAF_URL=https://waf.contoh.id ./start.sh --spoof

set -euo pipefail

cd "$(dirname "$0")"

SKIP_PROBE=0
SKIP_STRIX=0
PROBE_ARGS=()

for arg in "$@"; do
    if [ "$arg" = "--no-probe" ]; then
        SKIP_PROBE=1
    elif [ "$arg" = "--no-strix" ]; then
        SKIP_STRIX=1
    else
        PROBE_ARGS+=("$arg")
    fi
done

# Pastikan path strix dan local bin masuk PATH
export PATH="$PWD/strix/.venv/bin:$HOME/.local/bin:$PATH"

echo "== [1/3] Verifikasi perkakas & lingkungan Strix AI =="
command -v python3 >/dev/null || { echo "✗ python3 tidak ditemukan"; exit 1; }
command -v pnpm >/dev/null || { echo "✗ pnpm tidak ditemukan"; exit 1; }
echo "  python3: $(python3 --version 2>&1)"
echo "  pnpm:    $(pnpm --version 2>&1)"

if [ ! -d "node_modules" ]; then
    echo "  node_modules hilang — menjalankan pnpm install..."
    pnpm install
fi

# Persiapan modul Strix AI otonom yang terintegrasi di strix/
if [ "$SKIP_STRIX" -eq 1 ]; then
    echo "  Inisialisasi Strix dilewati (--no-strix)"
elif [ -d "strix" ]; then
    # 1. Pastikan uv tersedia
    if ! command -v uv >/dev/null 2>&1 && [ ! -x "$HOME/.local/bin/uv" ]; then
        echo "  uv tidak ditemukan — memasang uv untuk Strix..."
        curl -LsSf https://astral.sh/uv/install.sh | sh || true
        export PATH="$HOME/.local/bin:$PATH"
    fi

    UV_BIN="$(command -v uv 2>/dev/null || echo "$HOME/.local/bin/uv")"

    # 2. Pastikan virtualenv strix/.venv terpasang
    if [ ! -f "strix/.venv/bin/strix" ] && [ -x "$UV_BIN" ]; then
        echo "  Menyiapkan dependensi Strix AI via uv sync..."
        (cd strix && "$UV_BIN" sync) || echo "  ⚠ uv sync gagal, periksa Python 3.12+"
    fi

    # 3. Lapor status Strix CLI
    if [ -x "strix/.venv/bin/strix" ]; then
        echo "  strix:   $(strix/.venv/bin/strix --version 2>&1) (lokal: strix/.venv/)"
    elif command -v strix >/dev/null 2>&1; then
        echo "  strix:   $(strix --version 2>&1) (sistem)"
    else
        echo "  ⚠ Strix CLI belum terpasang di strix/.venv/bin/strix"
    fi

    # 4. Status Docker Daemon
    if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
        echo "  docker:  $(docker --version 2>&1 | head -n 1) (daemon aktif)"
    else
        echo "  docker:  ⚠ daemon belum aktif / centang WSL Integration di Docker Desktop"
    fi
fi

if [ "$SKIP_PROBE" -eq 1 ]; then
    echo "== [2/3] Probe Python dilewati (--no-probe) =="
else
    echo "== [2/3] Menjalankan harness Python (waf_probe.py) =="
    if [ "${#PROBE_ARGS[@]}" -eq 0 ]; then
        PROBE_ARGS=(
            --plain "${PLAIN_URL:-http://127.0.0.1:8000}"
            --waf "${WAF_URL:-http://127.0.0.1:8001}"
            --spoof --fuzz
        )
    fi
    echo "  opsi: ${PROBE_ARGS[*]}"
    python3 scripts/waf_probe.py "${PROBE_ARGS[@]}" || {
        echo "  ⚠ probe menemukan TEMUAN (bukan error fatal) — lanjut ke pnpm dev."
    }
fi

echo "== [3/3] Menjalankan konsol web WAF & Strix (pnpm dev) — Ctrl+C untuk berhenti =="
exec pnpm dev
