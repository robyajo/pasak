import { useCallback, useEffect, useRef, useState } from "react";
import type {
    StrixJob,
    StrixLogLine,
    StrixRunDetail,
    StrixRunSummary,
    StrixScanMode,
    StrixStatus,
} from "@/types/strix";

export interface StartScanInput {
    target: string;
    mode: StrixScanMode;
    budget: number;
    instruction?: string;
}

export interface SaveConfigInput {
    llm?: string;
    provider?: string;
    apiKey?: string;
    geminiApiKey?: string;
    openaiApiKey?: string;
    openrouterApiKey?: string;
    apiBase?: string;
}

export function useStrix() {
    const [status, setStatus] = useState<StrixStatus | null>(null);
    const [runs, setRuns] = useState<StrixRunSummary[]>([]);
    const [activeJob, setActiveJob] = useState<StrixJob | null>(null);
    const [logs, setLogs] = useState<StrixLogLine[]>([]);
    const [selectedRunDetail, setSelectedRunDetail] =
        useState<StrixRunDetail | null>(null);
    const [loadingDetail, setLoadingDetail] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [stopping, setStopping] = useState(false);
    const [savingConfig, setSavingConfig] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const lastSeqRef = useRef<number>(0);
    const isScanningRef = useRef<boolean>(false);

    /** Muat status kesiapan Strix, Docker, dan konfigurasi model */
    const refreshStatus = useCallback(async () => {
        try {
            const res = await fetch("/api/strix/status");
            if (!res.ok) {
                throw new Error(
                    `HTTP ${res.status}: Gagal memuat status Strix.`,
                );
            }
            const data: StrixStatus = await res.json();
            setStatus(data);
            if (data.active) {
                setActiveJob(data.active);
                if (data.active.logs && data.active.logs.length > 0) {
                    setLogs(data.active.logs);
                    lastSeqRef.current = data.active.next_after || 0;
                }
            }
        } catch (err) {
            console.error("Gagal refresh status Strix:", err);
        }
    }, []);

    /** Muat riwayat run pentest */
    const refreshRuns = useCallback(async () => {
        try {
            const res = await fetch("/api/strix/runs");
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: Gagal memuat daftar run.`);
            }
            const data = await res.json();
            if (data.success && Array.isArray(data.runs)) {
                setRuns(data.runs);
            }
        } catch (err) {
            console.error("Gagal refresh runs Strix:", err);
        }
    }, []);

    /** Muat detail laporan run tertentu */
    const loadRunDetail = useCallback(async (name: string) => {
        if (!name) return;
        setLoadingDetail(true);
        setError(null);
        try {
            const res = await fetch(
                `/api/strix/runs/${encodeURIComponent(name)}`,
            );
            if (!res.ok) {
                const body = await res.json().catch(() => ({}));
                throw new Error(body.message || `Gagal membaca run ${name}.`);
            }
            const data: StrixRunDetail = await res.json();
            setSelectedRunDetail(data);
        } catch (err) {
            setError(err instanceof Error ? err.message : String(err));
        } finally {
            setLoadingDetail(false);
        }
    }, []);

    const clearRunDetail = useCallback(() => {
        setSelectedRunDetail(null);
    }, []);

    /** Mulai scan headless baru */
    const startScan = useCallback(
        async (input: StartScanInput) => {
            setSubmitting(true);
            setError(null);
            try {
                const res = await fetch("/api/strix/scan", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(input),
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(
                        data.message || "Gagal memulai scan Strix.",
                    );
                }
                setActiveJob(data.job);
                setLogs(data.job.logs || []);
                lastSeqRef.current = data.job.next_after || 0;
                await refreshStatus();
            } catch (err) {
                const msg = err instanceof Error ? err.message : String(err);
                setError(msg);
                throw err;
            } finally {
                setSubmitting(false);
            }
        },
        [refreshStatus],
    );

    /** Hentikan scan yang sedang berjalan */
    const stopScan = useCallback(async () => {
        setStopping(true);
        setError(null);
        try {
            const res = await fetch("/api/strix/scan", {
                method: "DELETE",
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || "Gagal menghentikan scan.");
            }
            if (data.job) {
                setActiveJob((prev) => ({
                    ...(prev ?? data.job),
                    status: data.job.status,
                }));
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : String(err));
        } finally {
            setStopping(false);
        }
    }, []);

    /** Simpan konfigurasi LLM / API Key ke .env */
    const saveConfig = useCallback(
        async (params: SaveConfigInput) => {
            setSavingConfig(true);
            setError(null);
            try {
                const res = await fetch("/api/strix/config", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(params),
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(
                        data.message || "Gagal menyimpan konfigurasi.",
                    );
                }
                await refreshStatus();
                return data.config;
            } catch (err) {
                const msg = err instanceof Error ? err.message : String(err);
                setError(msg);
                throw err;
            } finally {
                setSavingConfig(false);
            }
        },
        [refreshStatus],
    );

    // Initial load
    useEffect(() => {
        refreshStatus();
        refreshRuns();
    }, [refreshStatus, refreshRuns]);

    // Active scan log polling
    const isJobActive =
        activeJob &&
        ["starting", "running", "stopping"].includes(activeJob.status);

    useEffect(() => {
        isScanningRef.current = Boolean(isJobActive);
        if (!isJobActive) return;

        let timer: ReturnType<typeof setTimeout>;

        const pollLogs = async () => {
            try {
                const res = await fetch(
                    `/api/strix/scan?after=${lastSeqRef.current}`,
                );
                if (!res.ok) return;
                const data = await res.json();
                if (data.success && data.job) {
                    const job: StrixJob = data.job;
                    setActiveJob(job);
                    if (Array.isArray(job.logs) && job.logs.length > 0) {
                        setLogs((prev) => [...prev, ...(job.logs ?? [])]);
                        lastSeqRef.current = job.next_after;
                    }

                    // Jika baru saja selesai
                    if (
                        ["completed", "findings", "stopped", "failed"].includes(
                            job.status,
                        )
                    ) {
                        refreshStatus();
                        refreshRuns();
                        return;
                    }
                }
            } catch (e) {
                console.error("Gagal poll log scan Strix:", e);
            }

            if (isScanningRef.current) {
                timer = setTimeout(pollLogs, 1000);
            }
        };

        timer = setTimeout(pollLogs, 1000);

        return () => {
            clearTimeout(timer);
        };
    }, [isJobActive, refreshRuns, refreshStatus]);

    return {
        status,
        runs,
        activeJob,
        logs,
        selectedRunDetail,
        loadingDetail,
        submitting,
        stopping,
        savingConfig,
        error,
        refreshStatus,
        refreshRuns,
        loadRunDetail,
        clearRunDetail,
        startScan,
        stopScan,
        saveConfig,
        clearLogs: () => setLogs([]),
        clearError: () => setError(null),
    };
}
