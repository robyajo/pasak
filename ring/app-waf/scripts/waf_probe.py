#!/usr/bin/env python3
"""Harness uji WAF headless (stdlib only, tanpa pip install).

Menembak katalog vektor (scripts/waf_vectors.py, 91+ dari src/lib/vectors.ts)
ke target RENTAN vs target ber-WAF lalu membandingkan:

    python3 scripts/waf_probe.py --plain http://127.0.0.1:8000 --waf http://127.0.0.1:8001
    python3 scripts/waf_probe.py --waf http://127.0.0.1:8001 --jobs 16 --spoof --fuzz
    python3 scripts/waf_probe.py --waf http://127.0.0.1:8001 --only-group ssti --out laporan.json

Ekspektasi regresi: target ber-WAF menjawab 403/429 untuk tiap vektor serang.
IP acak per request agar auto-block satu vektor tidak mencemari vektor lain.
Keluar kode 1 bila ada yang lolos (bukan 403/429) atau ada HTTP 500 (fail-secure).
"""

from __future__ import annotations

import argparse
import concurrent.futures as futures
import datetime as dt
import json
import random
import re
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from waf_vectors import load_vectors  # noqa: E402

DEVICE_RUN = f"py-probe-{random.randrange(16**6):06x}"
LOCAL_IP = "192.168.1.60"
DEFAULT_UA = "Mozilla/5.0 (WAF-Probe)"
TIMEOUT = 15


def device_id() -> str:
    # Identitas perangkat UNIK per request agar auto-block satu vektor
    # tidak mencemari hasil vektor lain (isolasi sinyal penuh).
    return f"{DEVICE_RUN}-{random.randrange(16**4):04x}"


# Grup bertanda (id) yang wajib 403 pada request PERTAMA (instant-block).
# Grup lain (rules/sensitive_path) hanya mencatat pada konfigurasi bawaan
# (monitoring saja) — dinilai dari request KEDUA untuk menangkap auto-block.
INSTANT_GROUP_IDS = {
    "ssti",
    "php_injection",
    "log4shell_jndi",
    "config_probe",
    "secret_exposure",
    "webshell_path",
    "scanner_ua",
    "webshell_upload",
}

# Penanda halaman aplikasi (vs berkas statis yang bocor).
APP_MARKER = "Beranda"


def group_id(vector: dict) -> str:
    m = re.search(r"\(([^)]+)\)\s*$", vector.get("group", ""))
    return m.group(1) if m else ""


# Variasi header forwarding untuk uji spoofing resolver IP.
SPOOF_VARIANTS: list[dict] = [
    {"nama": "multi-hop", "X-Forwarded-For": "1.2.3.4, 10.0.0.1, 192.168.0.1"},
    {"nama": "ip-privat", "X-Forwarded-For": "192.168.1.1"},
    {"nama": "x-real-ip-acak", "X-Real-IP": "8.8.8.8"},
]

# Mutasi struktur untuk uji fail-secure (tak boleh ada yang 500).
FUZZ_CASES: list[dict] = [
    {"nama": "path-sangat-panjang", "path": "/" + "A" * 2000},
    {"nama": "encoded-traversal", "path": "/%2e%2e/%2e%2e/etc/passwd"},
    {"nama": "null-byte", "path": "/?f=shell.php%00.jpg"},
    {"nama": "query-aneh", "path": "/?%ff%fe=%00"},
]


def random_public_ip() -> str:
    while True:
        a = random.randint(1, 223)
        b = random.randint(0, 255)
        if a == 10 or a == 127 or (a == 172 and 16 <= b <= 31):
            continue
        if (a == 192 and b == 168) or (a == 169 and b == 254) or a >= 224:
            continue
        return f"{a}.{b}.{random.randint(0, 255)}.{random.randint(1, 254)}"


def build_multipart(upload: dict) -> tuple[bytes, str]:
    boundary = f"----ProbeBoundary{random.randrange(16**12):012x}"
    body = (
        f"--{boundary}\r\n"
        f'Content-Disposition: form-data; name="file"; filename="{upload["filename"]}"\r\n'
        f"Content-Type: {upload['content_type']}\r\n\r\n"
        f"{upload['content']}\r\n"
        f"--{boundary}--\r\n"
    ).encode()
    return body, f"multipart/form-data; boundary={boundary}"


def send(
    base: str,
    vector: dict,
    ip: str,
    dev: str | None = None,
    extra_headers: dict | None = None,
) -> dict:
    """Kirim satu vektor, kembalikan {status, ms, body_snippet, error}."""
    url = base.rstrip("/") + vector["path"]
    method = vector.get("method", "GET")
    data: bytes | None = None
    headers = {
        "CF-Connecting-IP": ip,
        "True-Client-IP": ip,
        "X-Real-IP": ip,
        "X-Forwarded-For": ip,
        "X-Device-Id": dev or device_id(),
        "X-Local-Ip": LOCAL_IP,
        "User-Agent": vector.get("userAgent") or DEFAULT_UA,
        "Accept": "text/html,application/json;q=0.9,*/*;q=0.8",
    }
    if extra_headers:
        headers.update(extra_headers)
    if vector.get("upload"):
        data, ctype = build_multipart(vector["upload"])
        headers["Content-Type"] = ctype
    elif method in ("POST", "PUT", "PATCH") and vector.get("payload"):
        data = vector["payload"].encode()
        headers["Content-Type"] = "application/x-www-form-urlencoded"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    t0 = time.perf_counter()
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT) as res:
            body = res.read(4096).decode("utf-8", "replace")
            return {
                "status": res.status,
                "ms": round((time.perf_counter() - t0) * 1000, 1),
                "body": body[:200].replace("\n", " "),
                "error": None,
            }
    except urllib.error.HTTPError as e:
        try:
            body = e.read(4096).decode("utf-8", "replace")
        except Exception:
            body = ""
        return {
            "status": e.code,
            "ms": round((time.perf_counter() - t0) * 1000, 1),
            "body": body[:200].replace("\n", " "),
            "error": None,
        }
    except Exception as e:  # jaringan mati / timeout
        return {
            "status": None,
            "ms": round((time.perf_counter() - t0) * 1000, 1),
            "body": "",
            "error": str(e)[:160],
        }


def probe_vector(args, vector: dict) -> dict:
    gid = group_id(vector)
    instant = gid in INSTANT_GROUP_IDS
    blocked_1 = False
    out: dict = {
        "group": vector["group"],
        "label": vector["label"],
        "request": f"{vector['method']} {vector['path']}",
        "ekspektasi": "403-instan" if instant else "catat-tanpa-500",
    }
    if args.plain:
        out["plain"] = send(args.plain, vector, random_public_ip())
    if args.waf:
        # Dua tembakan identitas SAMA: ke-1 uji instant-block, ke-2 menangkap
        # auto-block (aturan rules/sensitive_path yang baru memblokir setelah
        # ambang tercapai).
        ip, dev = random_public_ip(), device_id()
        first = send(args.waf, vector, ip, dev)
        second = send(args.waf, vector, ip, dev)
        out["waf_1"] = first
        out["waf_2"] = second
        blocked_1 = first["status"] in (403, 429)
        blocked_2 = second["status"] in (403, 429)
        out["server_error"] = first["status"] == 500 or second["status"] == 500
        if instant:
            if (
                vector.get("upload")
                and first["status"] == 404
                and second["status"] == 404
            ):
                # Endpoint upload tak ada di target ini — tak bisa dinilai.
                out["verdict"] = "SKIP-tanpa-rute"
            else:
                out["verdict"] = "OK-diblokir" if blocked_1 else classify_miss(second)
        elif blocked_1 or blocked_2:
            out["verdict"] = "OK-diblokir-auto"
        elif out["server_error"]:
            out["verdict"] = "GAGAL-500"
        else:
            out["verdict"] = "OK-monitor"
    if args.spoof and args.waf:
        # Spoof hanya dinilai untuk grup instan (rules memang 200 by design),
        # dan hanya FAIL bila baseline diblokir tapi versi spoof lolos
        # (artinya resolver terkecoh).
        out["spoof"] = []
        out["spoof_baseline_diblokir"] = bool(instant and blocked_1)
        if instant:
            for v in SPOOF_VARIANTS:
                extra = {k: val for k, val in v.items() if k != "nama"}
                r = send(args.waf, vector, random_public_ip(), device_id(), extra)
                out["spoof"].append(
                    {
                        "variasi": v["nama"],
                        "status": r["status"],
                        "tetap_diblokir": r["status"] in (403, 429),
                    }
                )
    return out


def classify_miss(second: dict) -> str:
    """Bedakan signature tak cocok vs berkas statis yang bocor."""
    if second["status"] == 200 and APP_MARKER not in (second.get("body") or ""):
        return "GAGAL-bocor-statis"
    return "GAGAL-lolos"


def preflight(base: str, nama: str) -> bool:
    """Cek cepat (4 dtk): target hidup sebelum ratusan request dikirim."""
    try:
        req = urllib.request.Request(
            base.rstrip("/") + "/", headers={"User-Agent": DEFAULT_UA}
        )
        with urllib.request.urlopen(req, timeout=4) as res:
            print(f"  {nama}: {base} hidup (HTTP {res.status})")
            return True
    except urllib.error.HTTPError as e:  # 403/404 = server hidup
        print(f"  {nama}: {base} hidup (HTTP {e.code})")
        return True
    except Exception as e:
        print(f"  {nama}: {base} MATI ({str(e)[:100]})")
        return False


def run_fuzz(args) -> list[dict]:
    rows = []
    for case in FUZZ_CASES:
        r = send(args.waf, {"path": case["path"], "method": "GET"}, random_public_ip())
        rows.append(
            {
                "nama": case["nama"],
                "status": r["status"],
                "ms": r["ms"],
                "fail_secure": r["status"] != 500 and r["error"] is None,
                "error": r["error"],
            }
        )
    return rows


def main() -> int:
    ap = argparse.ArgumentParser(description="Harness uji WAF headless.")
    ap.add_argument("--plain", default="", help="Target rentan (kontrol)")
    ap.add_argument("--waf", default="", help="Target ber-WAF")
    ap.add_argument("--jobs", type=int, default=16, help="Paralelisme")
    ap.add_argument("--only-group", default="", help="Filter substring nama grup")
    ap.add_argument(
        "--spoof", action="store_true", help="Uji variasi header forwarding"
    )
    ap.add_argument(
        "--fuzz", action="store_true", help="Uji mutasi fail-secure (butuh --waf)"
    )
    ap.add_argument(
        "--latency-warn", type=float, default=100.0, help="Ambang ms peringatan"
    )
    ap.add_argument(
        "--out",
        default="",
        help="Tulis laporan JSON (bawaan: report/laporan-waf-*.json)",
    )
    args = ap.parse_args()

    if not args.plain and not args.waf:
        ap.error("isi --plain dan/atau --waf")
    if (args.spoof or args.fuzz) and not args.waf:
        ap.error("--spoof/--fuzz butuh --waf")

    vectors = load_vectors()
    if args.only_group:
        key = args.only_group.lower()
        vectors = [v for v in vectors if key in v["group"].lower()]
    print(f"vektor: {len(vectors)} | jobs: {args.jobs} | device-run: {DEVICE_RUN}")

    print("preflight target:")
    targets_ok = True
    if args.plain:
        targets_ok = preflight(args.plain, "rentan") and targets_ok
    if args.waf:
        targets_ok = preflight(args.waf, "WAF") and targets_ok
    if not targets_ok:
        print("BATAL: target ada yang mati/tak terjangkau.")
        print("  local      → pasak-no :8000 (kontrol) + pasak-yes :8001 (ber-Pasak)")
        print("  production → cek domain + izin audit (atau override via")
        print(
            "                PLAIN_URL=... WAF_URL=... ./start.sh / flag --plain/--waf)"
        )
        return 2

    results: list[dict] = []
    done = 0
    with futures.ThreadPoolExecutor(max_workers=args.jobs) as pool:
        for row in pool.map(lambda v: probe_vector(args, v), vectors):
            results.append(row)
            done += 1
            if done % 10 == 0 or done == len(vectors):
                print(f"  ... {done}/{len(vectors)} vektor", flush=True)

    fuzz_rows = run_fuzz(args) if args.fuzz else []

    gagal_verdict = [r for r in results if (r.get("verdict") or "").startswith("GAGAL")]
    err500 = [r for r in results if r.get("server_error")]
    spoof_bocor = [
        {"vektor": f"{r['group']} :: {r['label']}", **s}
        for r in results
        if r.get("spoof_baseline_diblokir")
        for s in r.get("spoof", [])
        if not s["tetap_diblokir"]
    ]
    fuzz_gagal = [f for f in fuzz_rows if not f["fail_secure"]]
    lat_max = max(
        (
            [
                r["waf_1"]["ms"]
                for r in results
                if "waf_1" in r and r["waf_1"]["ms"] is not None
            ]
            or [0]
        )
    )
    monitor = [r for r in results if r.get("verdict") == "OK-monitor"]

    gagal = bool(gagal_verdict or spoof_bocor or fuzz_gagal)

    print(
        f"\nhasil WAF: {len(results) - len(gagal_verdict)}/{len(results)} sesuai ekspektasi",
        end="",
    )
    if gagal_verdict:
        print("  ← TEMUAN:")
        for r in gagal_verdict:
            print(
                f"  ❌ [{r['group']}] {r['label']}: {r['verdict']} "
                f"(1:{r['waf_1']['status']} 2:{r['waf_2']['status']})"
            )
    else:
        print("  ✅ semua sesuai ekspektasi")
    if monitor:
        print(
            f"  📝 {len(monitor)}x rules/monitor (200/404 tercatat, tanpa 500) — by design"
        )
    if err500:
        print(f"  🔥 {len(err500)}x HTTP 500 (tidak fail-secure!)")
    if spoof_bocor:
        print(f"  🕵️ {len(spoof_bocor)}x spoof lolos resolver")
    if fuzz_rows:
        ok = len(fuzz_rows) - len(fuzz_gagal)
        print(f"  fuzz fail-secure: {ok}/{len(fuzz_rows)} OK", end="")
        print("" if fuzz_gagal else "  ✅")
        for f in fuzz_gagal:
            print(f"  ❌ fuzz {f['nama']}: HTTP {f['status']} {f['error'] or ''}")
    print(f"  latensi maks: {lat_max} ms", end="")
    print("  ⚠️ melebihi ambang" if lat_max > args.latency_warn else "")

    out = args.out or (
        f"report/laporan-waf-{dt.datetime.now().strftime('%Y%m%d-%H%M%S')}.json"
    )
    Path(out).parent.mkdir(parents=True, exist_ok=True)
    Path(out).write_text(
        json.dumps(
            {
                "waktu": dt.datetime.now().isoformat(),
                "target_rentan": args.plain or None,
                "target_waf": args.waf or None,
                "device_run": DEVICE_RUN,
                "ringkasan": {
                    "total": len(results),
                    "temuan": [
                        f"{r['group']} :: {r['label']} :: {r['verdict']}"
                        for r in gagal_verdict
                    ],
                    "http_500": len(err500),
                    "spoof_bocor": len(spoof_bocor),
                    "fuzz_gagal": len(fuzz_gagal),
                    "monitor_by_design": len(monitor),
                    "latensi_maks_ms": lat_max,
                },
                "vektor": results,
                "fuzz": fuzz_rows,
            },
            indent=2,
            ensure_ascii=False,
        ),
        encoding="utf-8",
    )
    print(f"laporan: {out}")
    return 1 if gagal else 0


if __name__ == "__main__":
    raise SystemExit(main())
