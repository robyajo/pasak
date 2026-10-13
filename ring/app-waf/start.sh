#!/usr/bin/env bash
#
# start.sh — pintu masuk tunggal konsol uji WAF (app-waf).
#
# Saat dijalankan: (1) verifikasi perkakas, (2) jalankan harness Python
# (waf_probe.py) agar ada hasil uji terbaru di report/, lalu (3) jalankan
# pnpm dev (konsol web) di foreground — Ctrl+C untuk berhenti.
#
# Cara pakai:
#   ./start.sh                                  # probe bawaan + pnpm dev
#   ./start.sh --plain http://127.0.0.1:8000 --waf http://127.0.0.1:8001 --spoof --fuzz
#   ./start.sh --no-probe                       # langsung pnpm dev, tanpa probe
#
# Mode local vs production diatur via ENV (bukan statis):
#   local (bawaan):
#     ./start.sh
#   production (domain audit sungguhan):
#     PLAIN_URL=https://rentan.contoh.id WAF_URL=https://waf.contoh.id ./start.sh --spoof
#
# Catatan: probe butuh kedua target hidup & terjangkau:
#   local      → pasak-no :8000 (kontrol) + pasak-yes :8001 (ber-Pasak)
#   production → dua URL di atas + cakupan izin audit tertulis

set -euo pipefail

cd "$(dirname "$0")"

SKIP_PROBE=0
PROBE_ARGS=()

for arg in "$@"; do
    if [ "$arg" = "--no-probe" ]; then
        SKIP_PROBE=1
    else
        PROBE_ARGS+=("$arg")
    fi
done

echo "== [1/3] Verifikasi perkakas =="
command -v python3 >/dev/null || { echo "✗ python3 tidak ditemukan"; exit 1; }
command -v pnpm >/dev/null || { echo "✗ pnpm tidak ditemukan"; exit 1; }
echo "  python3: $(python3 --version 2>&1)"
echo "  pnpm:    $(pnpm --version 2>&1)"

if [ ! -d "node_modules" ]; then
    echo "  node_modules hilang — menjalankan pnpm install..."
    pnpm install
fi

if [ "$SKIP_PROBE" -eq 1 ]; then
    echo "== [2/3] Probe Python dilewati (--no-probe) =="
else
    echo "== [2/3] Menjalankan harness Python (waf_probe.py) =="
    if [ "${#PROBE_ARGS[@]}" -eq 0 ]; then
        # Target bawaan bisa dioverride via ENV untuk mode production.
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

echo "== [3/3] Menjalankan konsol web (pnpm dev) — Ctrl+C untuk berhenti =="
exec pnpm dev
