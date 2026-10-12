import type { NoPasakRecommendation } from "@/lib/noPasak";
import type { ChainStep } from "@/lib/vulnInfo";

export type ProofGrade = "proven" | "passed" | "blocked";

export type BusyState = number | string | null;

export interface MethodRun {
    label: string;
    status?: number;
    ok: boolean;
    grade: ProofGrade;
    output: string;
    json: string;
}

export interface SheetMethodState {
    title: string;
    desc: string;
    request: string;
    vuln: string;
}

export interface PendingMethodState {
    cardIndex: number;
    item: NoPasakRecommendation;
    mi: number;
    label: string;
}

export type { ChainStep };
