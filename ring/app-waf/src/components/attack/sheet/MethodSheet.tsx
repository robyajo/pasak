import { SkullIcon } from "lucide-react";
import { Separator } from "@/components/ui/separator";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from "@/components/ui/sheet";
import { VULN_INFO, type ChainStep } from "@/lib/vulnInfo";
import type { BusyState, MethodRun, SheetMethodState } from "./types";
import MethodActionSection from "./MethodActionSection";
import MethodResultSection from "./MethodResultSection";
import MethodChainSection from "./MethodChainSection";
import MethodRemediationSection from "./MethodRemediationSection";

export interface MethodSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    method: SheetMethodState | null;
    run: MethodRun | null;
    busy: BusyState;
    url: string;
    copiedIdx: number | null;
    chainRuns: Record<string, { status?: number; ok: boolean; note: string }>;
    onCopyJson: (index: number, text: string) => void;
    onRunChainStep: (step: ChainStep) => void;
}

export default function MethodSheet({
    open,
    onOpenChange,
    method,
    run,
    busy,
    url,
    copiedIdx,
    chainRuns,
    onCopyJson,
    onRunChainStep,
}: MethodSheetProps) {
    const vulnInfo = method !== null ? VULN_INFO[method.vuln] : undefined;
    const chain = vulnInfo?.chain;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex w-[94vw] flex-col gap-0 p-0 sm:max-w-[50vw]">
                <SheetHeader className="shrink-0 border-b p-5 pb-4">
                    <SheetTitle className="flex items-center gap-2 pr-8 text-base">
                        <SkullIcon data-icon="inline-start" />
                        {method?.title ?? "Eksekusi metode"}
                    </SheetTitle>
                    <SheetDescription>
                        Aksi hacker yang sedang dieksekusi ke{" "}
                        <span className="font-mono">{url || "target"}</span>
                    </SheetDescription>
                </SheetHeader>

                <ScrollArea className="min-h-0 flex-1">
                    <div className="flex flex-col gap-4 p-5">
                        {method !== null && (
                            <>
                                <MethodActionSection method={method} />
                                <Separator />
                            </>
                        )}

                        <MethodResultSection
                            run={run}
                            copiedIdx={copiedIdx}
                            onCopyJson={onCopyJson}
                        />

                        {chain !== undefined && chain.length > 0 && (
                            <>
                                <Separator />
                                <MethodChainSection
                                    chain={chain}
                                    chainRuns={chainRuns}
                                    busy={busy}
                                    onRunChainStep={onRunChainStep}
                                />
                            </>
                        )}

                        {vulnInfo !== undefined && (
                            <>
                                <Separator />
                                <MethodRemediationSection
                                    vulnName={vulnInfo.vuln}
                                    fix={vulnInfo.fix}
                                />
                            </>
                        )}
                    </div>
                </ScrollArea>
            </SheetContent>
        </Sheet>
    );
}
