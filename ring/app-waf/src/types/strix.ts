export type StrixScanMode = "quick" | "standard" | "deep";

export type LlmProvider =
    "gemini" | "openai" | "openrouter" | "opencode" | "custom";

export type StrixJobStatus =
    | "idle"
    | "starting"
    | "running"
    | "stopping"
    | "stopped"
    | "completed"
    | "findings"
    | "failed"
    | "interrupted";

export interface StrixProbeInfo {
    installed: boolean;
    path: string;
    version: string | null;
    message: string | null;
}

export interface DockerProbeInfo {
    installed: boolean;
    running: boolean;
    version: string | null;
    sandbox_ready?: boolean;
    message: string | null;
}

export interface ProviderKeyStatus {
    configured: boolean;
    is_placeholder: boolean;
}

export interface StrixConfigInfo {
    env_file: string;
    llm: string | null;
    provider?: LlmProvider;
    has_api_key: boolean;
    is_placeholder_key?: boolean;
    providers?: {
        gemini: ProviderKeyStatus;
        openai: ProviderKeyStatus;
        openrouter: ProviderKeyStatus;
    };
    binary: string;
}

export interface StrixLogLine {
    seq: number;
    stream: "stdout" | "stderr" | "system";
    text: string;
    at: string;
}

export type StrixLogEntry = StrixLogLine;

export interface StrixJob {
    id: string;
    target: string;
    mode: StrixScanMode;
    budget: number;
    instruction: string;
    status: StrixJobStatus;
    started_at: string;
    finished_at: string | null;
    duration_ms: number | null;
    exit_code: number | null;
    run_name: string | null;
    error: string | null;
    dropped_logs: number;
    next_after: number;
    logs?: StrixLogLine[];
}

export interface StrixStatus {
    success: boolean;
    checked_at: string;
    strix: StrixProbeInfo;
    docker: DockerProbeInfo;
    config: StrixConfigInfo;
    runs_dir: string;
    run_count: number;
    finding_count: number;
    active: StrixJob | null;
}

export interface StrixRunSummary {
    name: string;
    target: string | null;
    scan_mode: string | null;
    status: string;
    start_time: string | null;
    end_time: string | null;
    finished: boolean;
    findings: number;
    severity_counts: {
        critical: number;
        high: number;
        medium: number;
        low: number;
        info: number;
    };
    updated_at: string;
}

export interface StrixVulnerability {
    id: string;
    title?: string;
    name?: string;
    severity?:
        "critical" | "high" | "medium" | "low" | "info" | "unknown" | string;
    description?: string;
    endpoint?: string;
    url?: string;
    reproduction?: string;
    poc?: string;
    markdown?: string;
    solution?: string;
    remediation?: string;
    [key: string]: unknown;
}

export interface StrixRunDetail {
    run: {
        name: string;
        target?: string | null;
        scan_mode?: string | null;
        status?: string;
        start_time?: string | null;
        end_time?: string | null;
        finished?: boolean;
        [key: string]: unknown;
    };
    vulnerabilities: StrixVulnerability[];
    severity_counts: {
        critical: number;
        high: number;
        medium: number;
        low: number;
        info: number;
    };
    report: string | null;
    record?: Record<string, unknown> | null;
}
