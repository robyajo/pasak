import { CopyIcon } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Spinner } from "@/components/ui/spinner";
import type { MethodRun } from "./types";

interface MethodResultSectionProps {
    run: MethodRun | null;
    copiedIdx: number | null;
    onCopyJson: (index: number, text: string) => void;
}

export default function MethodResultSection({
    run,
    copiedIdx,
    onCopyJson,
}: MethodResultSectionProps) {
    return (
        <section
            id="method-result"
            className="flex min-h-0 flex-col gap-2"
        >
            <h3 className="text-xs font-bold tracking-wide text-muted-foreground uppercase">
                Hasil eksekusi
            </h3>
            {run === null ? (
                <div className="flex items-center gap-2 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    <Spinner data-icon="inline-start" /> Mengirim request…
                </div>
            ) : (
                <div className="flex flex-col gap-3">
                    <div>
                        <Badge
                            variant={
                                run.grade === "proven"
                                    ? "destructive"
                                    : run.grade === "passed"
                                      ? "outline"
                                      : "secondary"
                            }
                        >
                            {run.grade === "proven"
                                ? `✅ TERBUKTI${run.status !== undefined ? ` (${run.status})` : ""}`
                                : run.grade === "passed"
                                  ? `⚠️ LOLOS TANPA BUKTI${run.status !== undefined ? ` (${run.status})` : ""} — bukan keberhasilan hacker, lanjutkan rantai`
                                  : "⛔ DITOLAK"}
                        </Badge>
                    </div>

                    <div className="rounded-lg border bg-black p-3 text-green-300">
                        <p className="mb-1 text-[11px] font-bold text-muted-foreground">
                            OUTPUT
                        </p>
                        <ScrollArea className="max-h-64 rounded">
                            <pre className="p-1 font-mono text-xs whitespace-pre-wrap break-all">
                                {run.output.slice(0, 1200)}
                            </pre>
                        </ScrollArea>
                    </div>

                    <div className="rounded-lg border bg-black p-3 text-green-300/80">
                        <div className="mb-1 flex items-center justify-between gap-2">
                            <p className="text-[11px] font-bold text-muted-foreground">
                                JSON
                            </p>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-7 text-xs"
                                onClick={() => onCopyJson(-1, run.json)}
                            >
                                <CopyIcon data-icon="inline-start" />
                                {copiedIdx === -1 ? "Disalin ✓" : "Copy JSON"}
                            </Button>
                        </div>
                        <ScrollArea className="max-h-64 rounded">
                            <pre className="p-1 font-mono text-[11px] whitespace-pre-wrap break-all">
                                {run.json}
                            </pre>
                        </ScrollArea>
                    </div>
                </div>
            )}
        </section>
    );
}
