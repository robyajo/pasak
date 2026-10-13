import { useState } from "react";
import { CopyIcon, DownloadIcon } from "lucide-react";
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from "@/components/ui/accordion";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type { HistoryEntry } from "@/hooks/useWafConsole";
import type { SurfaceAnalysis } from "@/lib/surface";

interface ConsoleReportProps {
    history: HistoryEntry[];
    target: string;
    surface: SurfaceAnalysis;
}

const isBlocked = (e: HistoryEntry): boolean =>
    e.blocked || e.status === 403 || e.status === 429;

/**
 * Laporan internal pengujian DENGAN Pasak (tab konsol): ringkasan upaya
 * DIBLOKIR vs LOLOS + vonis, preview accordion, salin & unduh JSON.
 */
export default function ConsoleReport({
    history,
    target,
    surface,
}: ConsoleReportProps) {
    const [copied, setCopied] = useState(false);

    if (history.length === 0) {
        return null;
    }

    const diblokir = history.filter(isBlocked);
    const lolos = history.filter((e) => !isBlocked(e));
    const vonis =
        lolos.length === 0
            ? "TERLINDUNGI ✅ (semua serangan ditolak)"
            : diblokir.length === 0
              ? "JEBOL ❌ (tak satu pun ditolak)"
              : "SEBAGIAN LOLOS ⚠️";
    const report = {
        judul: "Laporan pengujian Dengan-Pasak",
        target,
        waktu: new Date().toISOString(),
        ringkasan: {
            total_upaya: history.length,
            diblokir: diblokir.length,
            lolos: lolos.length,
            persen_diblokir: Math.round((diblokir.length / history.length) * 100),
            vonis,
        },
        daftar_diblokir: diblokir,
        daftar_lolos: lolos,
        semua_upaya: history,
        fingerprinting: {
            ringkasan: surface.summary,
            temuan: surface.findings.map((f) => ({
                jenis: f.kind,
                judul: f.title,
                target: f.target,
                detail: f.detail,
                risiko: f.risks,
                rekomendasi: f.recommendation,
                contoh_payload: f.payloads,
            })),
        },
    };
    const json = JSON.stringify(report, null, 2);

    const copy = async (): Promise<void> => {
        try {
            await navigator.clipboard.writeText(json);
        } catch {
            const area = document.createElement("textarea");
            area.value = json;
            document.body.appendChild(area);
            area.select();
            document.execCommand("copy");
            document.body.removeChild(area);
        }
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1500);
    };

    const download = (): void => {
        const blob = new Blob([json], { type: "application/json" });
        const stamp = new Date()
            .toISOString()
            .slice(0, 16)
            .replace(/[-:T]/g, "");
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = `laporan-dengan-pasak-${stamp}.json`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm">
                    📋 Laporan Dengan-Pasak — {vonis}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Accordion>
                    <AccordionItem value="report">
                        <AccordionTrigger className="text-xs">
                            Preview + salin JSON ({diblokir.length} diblokir /{" "}
                            {lolos.length} lolos / {surface.summary.total}{" "}
                            temuan fingerprinting)
                        </AccordionTrigger>
                        <AccordionContent>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={copy}
                                >
                                    <CopyIcon data-icon="inline-start" />
                                    {copied ? "Disalin ✓" : "Salin JSON laporan"}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={download}
                                >
                                    <DownloadIcon data-icon="inline-start" />
                                    Unduh .json
                                </Button>
                            </div>
                            <pre className="mt-2 max-h-96 overflow-auto rounded bg-black p-2 font-mono text-[11px] whitespace-pre-wrap text-green-300">
                                {json}
                            </pre>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>
            </CardContent>
        </Card>
    );
}
