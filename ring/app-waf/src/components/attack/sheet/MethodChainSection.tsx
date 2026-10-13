import { PlayIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Spinner } from "@/components/ui/spinner";
import type { ChainStep } from "@/lib/vulnInfo";
import type { BusyState } from "./types";

interface MethodChainSectionProps {
    chain: ChainStep[];
    chainRuns: Record<string, { status?: number; ok: boolean; note: string }>;
    busy: BusyState;
    onRunChainStep: (step: ChainStep) => void;
}

export default function MethodChainSection({
    chain,
    chainRuns,
    busy,
    onRunChainStep,
}: MethodChainSectionProps) {
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
                                    <p
                                        className={`mt-2 font-mono text-[11px] ${
                                            stepRun.ok
                                                ? "font-semibold text-green-600 dark:text-green-300"
                                                : "text-muted-foreground"
                                        }`}
                                    >
                                        {stepRun.ok ? "✅" : "→"} {stepRun.note}
                                    </p>
                                )}
                            </li>
                        );
                    })}
                </ol>
            </ScrollArea>
        </section>
    );
}
