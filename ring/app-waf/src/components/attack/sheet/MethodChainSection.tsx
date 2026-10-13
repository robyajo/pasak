import { useState } from "react";
import { CopyIcon, PlayIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Spinner } from "@/components/ui/spinner";
import type { ChainStep } from "@/lib/vulnInfo";
import type { BusyState, ChainRun } from "./types";

interface MethodChainSectionProps {
    chain: ChainStep[];
    chainRuns: Record<string, ChainRun>;
    busy: BusyState;
    onRunChainStep: (step: ChainStep) => void;
    onTestPayload: (path: string) => void;
}

export default function MethodChainSection({
    chain,
    chainRuns,
    busy,
    onRunChainStep,
    onTestPayload,
}: MethodChainSectionProps) {
    const [copiedLabel, setCopiedLabel] = useState<string | null>(null);

    const copyNext = async (label: string, text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopiedLabel(label);
            window.setTimeout(() => {
                setCopiedLabel((prev) => (prev === label ? null : prev));
            }, 1500);
        } catch {
            // Clipboard tak tersedia — biarkan payload terlihat untuk disalin manual.
        }
    };
    return (
        <section className="flex flex-col gap-2">
            <h3 className="text-xs font-bold tracking-wide text-muted-foreground uppercase">
                ⛓️ Rantai eskalasi — lanjut sampai tujuan
            </h3>
            <ScrollArea className="max-h-96 pr-2">
                <ol className="flex flex-col gap-2.5">
                    {chain.map((step) => {
                        const stepRun = chainRuns[step.label];
                        const isStepBusy = busy === `chain-${step.label}`;
                        return (
                            <li
                                key={step.label}
                                className="rounded-lg border bg-card p-3 shadow-sm"
                            >
                                <p className="text-xs font-bold">{step.label}</p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {step.desc}
                                </p>
                                {step.intel !== undefined && (
                                    <p className="mt-1.5 rounded border border-amber-500/40 bg-amber-500/10 p-1.5 text-[11px] leading-relaxed">
                                        🎯 <b>Intel hacker:</b> {step.intel}
                                    </p>
                                )}
                                <div className="mt-1.5 rounded bg-muted/40 p-1.5 font-mono text-[11px] break-all">
                                    {step.method ?? "GET"} {step.path}
                                </div>
                                <div className="mt-2 flex items-center gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="secondary"
                                        disabled={busy !== null}
                                        title={
                                            busy !== null && !isStepBusy
                                                ? "Tunggu proses lain selesai dulu"
                                                : undefined
                                        }
                                        onClick={() => onRunChainStep(step)}
                                    >
                                        {isStepBusy ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : (
                                            <PlayIcon data-icon="inline-start" />
                                        )}
                                        {isStepBusy
                                            ? "Menjalankan..."
                                            : "▶ Jalankan langkah ini"}
                                    </Button>
                                </div>
                                {stepRun !== undefined && (
                                    <>
                                        <p
                                            className={`mt-2 font-mono text-[11px] whitespace-pre-line ${
                                                stepRun.ok
                                                    ? "font-semibold text-green-600 dark:text-green-300"
                                                    : "text-muted-foreground"
                                            }`}
                                        >
                                            {stepRun.ok ? "✅" : "→"} {stepRun.note}
                                        </p>
                                        {stepRun.next !== undefined && stepRun.next !== "" && (
                                            <div className="mt-2 rounded bg-muted/40 p-1.5">
                                                <p className="mb-1 text-[10px] font-bold text-muted-foreground">
                                                    🎯 AKSI UJI BERIKUTNYA
                                                </p>
                                                <p className="font-mono text-[11px] break-all">
                                                    {stepRun.next}
                                                </p>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="mt-1.5 h-7 text-xs"
                                                    onClick={() =>
                                                        copyNext(step.label, stepRun.next as string)
                                                    }
                                                >
                                                    <CopyIcon data-icon="inline-start" />
                                                    {copiedLabel === step.label
                                                        ? "Disalin ✓"
                                                        : "Salin payload"}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="secondary"
                                                    className="mt-1.5 ml-1.5 h-7 text-xs"
                                                    disabled={busy !== null}
                                                    title={
                                                        busy !== null
                                                            ? "Tunggu proses lain selesai dulu"
                                                            : "Masukkan payload ke request & jalankan — hasil tampil di Hasil eksekusi"
                                                    }
                                                    onClick={() =>
                                                        onTestPayload(stepRun.next as string)
                                                    }
                                                >
                                                    {busy === "test-payload" ? (
                                                        <Spinner data-icon="inline-start" />
                                                    ) : (
                                                        <PlayIcon data-icon="inline-start" />
                                                    )}
                                                    🧪 Uji langsung
                                                </Button>
                                            </div>
                                        )}
                                    </>
                                )}
                            </li>
                        );
                    })}
                </ol>
            </ScrollArea>
        </section>
    );
}
