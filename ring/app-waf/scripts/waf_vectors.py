"""Muat katalog vektor serangan langsung dari src/lib/vectors.ts.

Satu sumber kebenaran: dataset Python selalu selaras dengan katalog UI
tanpa duplikasi manual. Hanya memakai regex + stdlib.
"""

from __future__ import annotations

import json
import re
from pathlib import Path

VECTORS_TS = Path(__file__).resolve().parent.parent / "src" / "lib" / "vectors.ts"

_ITEM_RE = re.compile(r"\{\s*label:\s*", re.MULTILINE)


def _str_field(chunk: str, name: str) -> str | None:
    m = re.search(rf"{name}:\s*\"((?:[^\"\\]|\\.)*)\"", chunk)
    if not m:
        return None
    try:
        return json.loads(f'"{m.group(1)}"')
    except json.JSONDecodeError:
        return m.group(1)


def _upload_field(chunk: str) -> dict | None:
    m = re.search(r"upload:\s*\{([^}]*)\}", chunk)
    if not m:
        return None
    body = m.group(1)
    return {
        "field": _str_field(body, "field") or "file",
        "filename": _str_field(body, "filename") or "shell.php",
        "content": _str_field(body, "content") or "",
        "content_type": _str_field(body, "content_type") or "application/octet-stream",
    }


def load_vectors() -> list[dict]:
    """Kembalikan [{group, label, path, method, userAgent, payload, upload}]."""
    text = VECTORS_TS.read_text(encoding="utf-8")
    vectors: list[dict] = []
    # Belah per grup berdasarkan penanda group: "...".
    parts = re.split(r'group:\s*"([^"]+)"', text)
    # parts[0] = prolog, lalu pasangan (nama_grup, isi_grup).
    for i in range(1, len(parts), 2):
        group = parts[i]
        body = parts[i + 1]
        # Potong sebelum grup berikutnya sudah ditangani split; ambil
        # item sampai akhir blok items (cukup andalkan pola label/path).
        for m in re.finditer(r'label:\s*"((?:[^"\\]|\\.)*)"', body):
            label = json.loads(f'"{m.group(1)}"')
            # Ambil chunk item: dari label sampai 'advice:' berikutnya.
            advice_at = body.find("advice:", m.start())
            chunk = body[m.start(): advice_at if advice_at != -1 else m.start() + 2000]
            path = _str_field(chunk, "path")
            if path is None:
                continue
            vectors.append(
                {
                    "group": group,
                    "label": label,
                    "path": path,
                    "method": (_str_field(chunk, "method") or "GET").upper(),
                    "userAgent": _str_field(chunk, "userAgent"),
                    "payload": _str_field(chunk, "payload"),
                    "upload": _upload_field(chunk),
                }
            )
    return vectors


if __name__ == "__main__":
    vecs = load_vectors()
    groups: dict[str, int] = {}
    for v in vecs:
        groups[v["group"]] = groups.get(v["group"], 0) + 1
    print(f"total: {len(vecs)} vektor")
    for g, n in groups.items():
        print(f"  {n:3d}x  {g}")
