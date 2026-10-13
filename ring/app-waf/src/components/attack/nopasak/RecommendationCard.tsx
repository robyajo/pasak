import {
    CopyIcon,
    FlaskConicalIcon,
    PlayIcon,
    SendIcon,
    ShieldCheckIcon,
    SkullIcon,
} from "lucide-react";
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from "@/components/ui/accordion";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
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
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import { VULN_INFO, severityVariant } from "@/lib/vulnInfo";
import { AccessLinks } from "./AccessLinks";
import { evidenceFor, isPassed, IMPACTS } from "./constants";
import type { RecommendationCardProps } from "./types";

export function RecommendationCard({
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
                        className="h-auto whitespace-normal py-1.5 text-left"
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
                        <AccessLinks url={url} body={res?.body} />
                        <Alert>
                            <ShieldCheckIcon data-icon="inline-start" />
                            <AlertTitle>Langkah pembuktian wajib:</AlertTitle>
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

                            <div className="mt-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    className="h-auto whitespace-normal py-1.5 text-left"
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

                            {fileCheck ? (
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
                                            {fileCheck.snippet.slice(0, 100)}
                                        </span>
                                    )}
                                </AlertDescription>
                            ) : (
                                isVerifyBusy && (
                                    <Skeleton className="mt-2 h-4 w-full" />
                                )
                            )}
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
                                                            className="h-auto whitespace-normal py-1.5 text-left"
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
                                                        <pre className="mt-1 max-h-32 overflow-auto whitespace-pre-wrap break-all text-green-300/80">
                                                            {run.json}
                                                        </pre>
                                                    </div>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ol>
                            </AccordionContent>
                        </AccordionItem>
                    </Accordion>
                )}
            </CardContent>
        </Card>
    );
}
