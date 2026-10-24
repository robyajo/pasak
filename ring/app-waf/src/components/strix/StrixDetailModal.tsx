import { useState } from "react";
import {
    AlertCircleIcon,
    CalendarIcon,
    CheckCircle2Icon,
    CopyCheckIcon,
    CopyIcon,
    DownloadIcon,
    ExternalLinkIcon,
    FileTextIcon,
    FlameIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    TargetIcon,
    XCircleIcon,
} from "lucide-react";
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from "@/components/ui/accordion";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import type { StrixRunDetail, StrixVulnerability } from "@/types/strix";

interface StrixDetailModalProps {
    detail: StrixRunDetail | null;
    open: boolean;
    onClose: () => void;
}

export function StrixDetailModal({
    detail,
    open,
    onClose,
}: StrixDetailModalProps) {
    const [tab, setTab] = useState<"findings" | "report" | "raw">("findings");
    const [copied, setCopied] = useState(false);

    if (!detail) return null;

    const { run, vulnerabilities = [], severity_counts, report } = detail;

    const handleCopyReport = () => {
        if (!report) return;
        navigator.clipboard.writeText(report);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handleDownloadReport = () => {
        if (!report) return;
        const blob = new Blob([report], { type: "text/markdown;charset=utf-8" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `strix-report-${run.name}.md`;
        a.click();
        URL.revokeObjectURL(url);
    };

    const getSeverityBadge = (sev?: string) => {
        const s = (sev || "low").toLowerCase();
        switch (s) {
            case "critical":
                return <Badge variant="destructive" className="font-bold">CRITICAL</Badge>;
            case "high":
                return <Badge variant="destructive" className="bg-orange-600">HIGH</Badge>;
            case "medium":
                return <Badge variant="secondary" className="text-amber-500 font-semibold border-amber-500/30">MEDIUM</Badge>;
            default:
                return <Badge variant="outline" className="text-blue-500 font-semibold">LOW</Badge>;
        }
    };

    return (
        <Dialog open={open} onOpenChange={(val) => !val && onClose()}>
            <DialogContent className="max-w-4xl max-h-[90vh] flex flex-col p-6">
                <DialogHeader className="border-b pb-3 shrink-0">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <ShieldAlertIcon className="size-5 text-primary" />
                            <div>
                                <DialogTitle className="text-lg font-bold flex items-center gap-2">
                                    Laporan Audit: <code>{run.name}</code>
                                </DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground flex items-center gap-2 mt-0.5">
                                    <span className="flex items-center gap-1">
                                        <TargetIcon className="size-3 text-primary" /> {run.target}
                                    </span>
                                    <span>•</span>
                                    <span className="uppercase font-mono text-[10px]">
                                        Mode: {run.scan_mode || "quick"}
                                    </span>
                                </DialogDescription>
                            </div>
                        </div>

                        {/* Severity breakdown pills */}
                        <div className="flex items-center gap-1.5">
                            {severity_counts?.critical ? (
                                <Badge variant="destructive" className="text-xs">
                                    🔴 {severity_counts.critical} Critical
                                </Badge>
                            ) : null}
                            {severity_counts?.high ? (
                                <Badge variant="destructive" className="bg-orange-600 text-xs">
                                    🟠 {severity_counts.high} High
                                </Badge>
                            ) : null}
                            {severity_counts?.medium ? (
                                <Badge variant="secondary" className="text-amber-500 text-xs">
                                    🟡 {severity_counts.medium} Medium
                                </Badge>
                            ) : null}
                            {severity_counts?.low ? (
                                <Badge variant="outline" className="text-blue-500 text-xs">
                                    🔵 {severity_counts.low} Low
                                </Badge>
                            ) : null}
                            {vulnerabilities.length === 0 && (
                                <Badge variant="default" className="bg-emerald-600 text-xs">
                                    ✅ 0 Temuan (Aman)
                                </Badge>
                            )}
                        </div>
                    </div>
                </DialogHeader>

                {/* Sub-nav tabs */}
                <div className="flex items-center justify-between border-b pb-2 pt-1 shrink-0">
                    <Tabs value={tab} onValueChange={(v) => setTab(v as typeof tab)}>
                        <TabsList variant="line">
                            <TabsTrigger value="findings" className="text-xs">
                                Kerentanan ({vulnerabilities.length})
                            </TabsTrigger>
                            <TabsTrigger value="report" className="text-xs">
                                Laporan Lengkap (.md)
                            </TabsTrigger>
                            <TabsTrigger value="raw" className="text-xs">
                                Raw Data (JSON)
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>

                    {tab === "report" && (
                        <div className="flex items-center gap-1.5">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={handleCopyReport}
                                className="h-7 text-xs"
                            >
                                {copied ? (
                                    <>
                                        <CopyCheckIcon className="size-3 mr-1 text-emerald-500" />
                                        Tersalin
                                    </>
                                ) : (
                                    <>
                                        <CopyIcon className="size-3 mr-1" />
                                        Salin Markdown
                                    </>
                                )}
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={handleDownloadReport}
                                className="h-7 text-xs"
                            >
                                <DownloadIcon className="size-3 mr-1" />
                                Unduh .md
                            </Button>
                        </div>
                    )}
                </div>

                {/* Main Content Area */}
                <div className="flex-1 overflow-y-auto py-2">
                    {tab === "findings" && (
                        <div>
                            {vulnerabilities.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-16 text-center text-muted-foreground gap-2">
                                    <ShieldCheckIcon className="size-10 text-emerald-500" />
                                    <h3 className="text-sm font-bold text-foreground">
                                        Tidak Ada Kerentanan Terdeteksi
                                    </h3>
                                    <p className="text-xs max-w-md">
                                        Strix tidak menemukan celah keamanan yang berhasil dieksploitasi pada target selama sesi audit ini.
                                    </p>
                                </div>
                            ) : (
                                <Accordion className="flex flex-col gap-2">
                                    {vulnerabilities.map((vuln, index) => {
                                        const title = vuln.title || vuln.name || `Temuan #${index + 1}`;
                                        return (
                                            <AccordionItem
                                                key={vuln.id || index}
                                                value={`vuln-${index}`}
                                                className="border rounded-lg px-4 py-1 bg-card/60"
                                            >
                                                <AccordionTrigger className="hover:no-underline py-2.5">
                                                    <div className="flex flex-wrap items-center gap-2.5 text-left">
                                                        {getSeverityBadge(vuln.severity)}
                                                        <span className="font-semibold text-xs text-foreground">
                                                            {title}
                                                        </span>
                                                        {vuln.endpoint || vuln.url ? (
                                                            <span className="font-mono text-[11px] text-muted-foreground bg-muted px-1.5 py-0.5 rounded">
                                                                {vuln.endpoint || vuln.url}
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </AccordionTrigger>
                                                <AccordionContent className="pt-2 pb-4 text-xs flex flex-col gap-3">
                                                    {vuln.description && (
                                                        <div>
                                                            <div className="font-semibold text-muted-foreground mb-1">
                                                                Deskripsi Celah:
                                                            </div>
                                                            <p className="leading-relaxed whitespace-pre-line">
                                                                {vuln.description}
                                                            </p>
                                                        </div>
                                                    )}

                                                    {vuln.reproduction && (
                                                        <div>
                                                            <div className="font-semibold text-muted-foreground mb-1">
                                                                Langkah Reproduksi:
                                                            </div>
                                                            <div className="bg-muted p-2.5 rounded font-mono text-[11px] whitespace-pre-wrap">
                                                                {vuln.reproduction}
                                                            </div>
                                                        </div>
                                                    )}

                                                    {vuln.poc && (
                                                        <div>
                                                            <div className="font-semibold text-muted-foreground mb-1">
                                                                Proof of Concept (PoC):
                                                            </div>
                                                            <div className="bg-zinc-950 text-zinc-100 p-2.5 rounded font-mono text-[11px] overflow-x-auto">
                                                                {vuln.poc}
                                                            </div>
                                                        </div>
                                                    )}

                                                    {vuln.markdown && !vuln.description && (
                                                        <div className="bg-muted/50 p-3 rounded font-mono text-[11px] whitespace-pre-wrap leading-relaxed">
                                                            {vuln.markdown}
                                                        </div>
                                                    )}

                                                    {(vuln.solution || vuln.remediation) && (
                                                        <div className="border-t pt-2 border-border/60">
                                                            <div className="font-semibold text-emerald-500 mb-1">
                                                                Rekomendasi Perbaikan:
                                                            </div>
                                                            <p className="leading-relaxed">
                                                                {vuln.solution || vuln.remediation}
                                                            </p>
                                                        </div>
                                                    )}
                                                </AccordionContent>
                                            </AccordionItem>
                                        );
                                    })}
                                </Accordion>
                            )}
                        </div>
                    )}

                    {tab === "report" && (
                        <div className="p-3 bg-card border rounded-lg">
                            {report ? (
                                <pre className="font-mono text-xs whitespace-pre-wrap leading-relaxed select-text">
                                    {report}
                                </pre>
                            ) : (
                                <div className="text-center py-12 text-xs text-muted-foreground">
                                    File laporan (penetration_test_report.md) tidak ditemukan untuk run ini.
                                </div>
                            )}
                        </div>
                    )}

                    {tab === "raw" && (
                        <div className="p-3 bg-zinc-950 text-zinc-100 rounded-lg">
                            <pre className="font-mono text-[11px] whitespace-pre-wrap overflow-x-auto">
                                {JSON.stringify(run, null, 2)}
                            </pre>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
