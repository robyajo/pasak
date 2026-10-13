import type { NoPasakRecommendation, NoPasakStage } from "@/lib/noPasak";
import type { ProxyResult, SendRawFn } from "@/hooks/useWafConsole";
import type { BusyState, MethodRun } from "../sheet";

export interface FileCheck {
    fileUrl: string;
    status?: number;
    ok: boolean;
    snippet: string;
}

export interface ExecResult {
    cmd: string;
    status?: number;
    ok: boolean;
    output: string;
}

export interface AccessLinksProps {
    url: string;
    body?: string;
}

export interface NoPasakTabProps {
    sendRaw: SendRawFn;
    target: string;
    onTargetChange?: (v: string) => void;
    mode?: "local" | "production";
}

export interface RecommendationCardProps {
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

export interface AttackerCardProps {
    uploadIdx: number;
    execCmd: string;
    onExecCmdChange: (cmd: string) => void;
    execResult: ExecResult | null;
    busy: BusyState;
    onExploitShell: (uploadIdx: number) => void;
    onExploitShellWith: (uploadIdx: number, rawCmd: string) => void;
}

export interface LivePreviewCardProps {
    previewUrl: string;
    previewSrc: string;
    previewPage: string;
    previewTick: number;
    onPageChange: (page: string) => void;
    onRefresh: () => void;
    sendRaw: SendRawFn;
    attackerIp: string;
    browser: string;
    deviceId: string;
    busy: BusyState;
    target: string;
}

export interface ConfigStepperCardProps {
    url: string;
    mode: "local" | "production";
    target: string;
    onTargetChange?: (v: string) => void;
    activeStage: number;
    onSelectStage: (id: number) => void;
    stages: NoPasakStage[];
    isStagePassed: (stageId: number) => boolean;
    isStageUnlocked: (stageId: number) => boolean;
    waived: Record<number, string>;
    doneCount: number;
    totalCount: number;
    passedCount: number;
    pct: number;
    allPassed: boolean;
    fileCheck: FileCheck | null;
    busy: BusyState;
    copiedIdx: number | null;
    onCopyReportJson: () => void;
    onDownloadReport: () => void;
    reportJson: string;
}

export interface ActiveStageHeaderProps {
    stage: NoPasakStage;
    busy: BusyState;
    onFireStage: () => void;
    isPassed: boolean;
    canAdvance: boolean;
    onAdvance: () => void;
    canWaive: boolean;
    waiverNote: string | undefined;
    onWaive: (note: string) => void;
}
