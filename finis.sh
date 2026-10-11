#!/usr/bin/env bash

# ==============================================================================
# Root wrapper for Pasak Docs VPS Build & Deploy Helper.
#
# Meneruskan seluruh argumen ke web-docs/finis.sh sehingga skrip dapat
# dijalankan dari root repositori tanpa harus pindah direktori:
#   ./finis.sh [OPSI]
# ==============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

exec bash "$SCRIPT_DIR/web-docs/finis.sh" "$@"
