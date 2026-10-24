import { useEffect, useRef, useState } from "react";
import {
    ClockIcon,
    CopyCheckIcon,
    CopyIcon,
    TerminalIcon,
    Trash2Icon,
} from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Spinner } from "@/components/ui/spinner";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import type { StrixJob, StrixLogLine } from "@/types/strix";

interface StrixLiveConsoleProps {
    job: StrixJob | null;
    logs: StrixLogLine[];
    onClearLogs: () => void;
}

function formatDuration(ms: number): string {
    const totalSeconds = Math.floor(ms / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`;
}

export function StrixLiveConsole({
    job,
    logs,
    onClearLogs,
}: StrixLiveConsoleProps) {
    const [autoScroll, setAutoScroll] = useState(true);
    const [copied, setCopied] = useState(false);
    const [filter, setFilter] = useState<"all" | "stdout" | "stderr" | "system">("all");
    const [elapsedMs, setElapsedMs] = useState(0);

    const logContainerRef = useRef<HTMLDivElement>(null);

    // Live elapsed timer
    useEffect(() => {
        if (!job?.started_at) {
            setElapsedMs(0);
            return;
        }

        const isRunning = ["starting", "running", "stopping"].includes(
            job.status,
        );

        if (!isRunning) {
            if (job.duration_ms) {
                setElapsedMs(job.duration_ms);
            }
            return;
        }

        const start = Date.parse(job.started_at);
        const update = () => {
            setElapsedMs(Math.max(0, Date.now() - start));
        };
        update();
        const interval = setInterval(update, 1000);

        return () => clearInterval(interval);
    }, [job?.started_at, job?.status, job?.duration_ms]);

    // Auto-scroll to bottom on new logs
    useEffect(() => {
        if (autoScroll && logContainerRef.current) {
            logContainerRef.current.scrollTop = logContainerRef.current.scrollHeight;
        }
    }, [logs, autoScroll]);

    const handleCopy = () => {
        const text = logs.map((l) => `[${l.stream.toUpperCase()}] ${l.text}`).join("\n");
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const filteredLogs = logs.filter((l) => {
        if (filter === "all") return true;
        return l.stream === filter;
    });

    const statusBadge = () => {
        if (!job) {
            return <Badge variant="outline">Idle</Badge>;
        }
        switch (job.status) {
            case "starting":
                return <Badge variant="secondary" className="animate-pulse">Memulai…</Badge>;
            case "running":
                return <Badge variant="default" className="bg-emerald-600 animate-pulse">Running</Badge>;
            case "stopping":
                return <Badge variant="secondary" className="text-amber-500">Menghentikan…</Badge>;
            case "completed":
                return <Badge variant="default" className="bg-emerald-700">Selesai (Aman)</Badge>;
            case "findings":
                return <Badge variant="destructive">Ditemukan Celah!</Badge>;
            case "stopped":
                return <Badge variant="outline">Dihentikan</Badge>;
            case "failed":
                return <Badge variant="destructive">Gagal: {job.error || "Error"}</Badge>;
            default:
                return <Badge variant="outline">{job.status}</Badge>;
        }
    };

    return (
        <Card className="h-full flex flex-col justify-between">
            <CardHeader className="pb-3 border-b">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <TerminalIcon className="size-4 text-primary" />
                        <CardTitle className="text-base font-mono">
                            Console Stream
                        </CardTitle>
                        {statusBadge()}
                    </div>

                    <div className="flex items-center gap-2 text-xs">
                        {job?.started_at && (
                            <span className="flex items-center gap-1 font-mono text-muted-foreground">
                                <ClockIcon className="size-3.5" />
                                {formatDuration(elapsedMs)}
                            </span>
                        )}

                        <div className="flex items-center rounded-md border border-border p-0.5 bg-muted/30">
                            {(["all", "stdout", "stderr", "system"] as const).map((f) => (
                                <button
                                    key={f}
                                    type="button"
                                    onClick={() => setFilter(f)}
                                    className={`px-1.5 py-0.5 rounded text-[10px] font-mono uppercase ${
                                        filter === f
                                            ? "bg-background text-foreground font-semibold shadow-2xs"
                                            : "text-muted-foreground hover:text-foreground"
                                    }`}
                                >
                                    {f}
                                </button>
                            ))}
                        </div>

                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={handleCopy}
                            disabled={logs.length === 0}
                            title="Salin log"
                        >
                            {copied ? (
                                <CopyCheckIcon className="size-3.5 text-emerald-500" />
                            ) : (
                                <CopyIcon className="size-3.5" />
                            )}
                        </Button>

                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={onClearLogs}
                            disabled={logs.length === 0}
                            title="Bersihkan log"
                        >
                            <Trash2Icon className="size-3.5" />
                        </Button>
                    </div>
                </div>
                {job?.run_name && (
                    <CardDescription className="text-[11px] font-mono truncate">
                        ID Run: <b>{job.run_name}</b> {job.target ? `• Target: ${job.target}` : ""}
                    </CardDescription>
                )}
            </CardHeader>

            <CardContent className="p-0 flex-1 relative flex flex-col">
                {logs.some((l) => l.text.toLowerCase().includes("pulling image ghcr.io/usestrix/strix-sandbox")) &&
                    job && ["starting", "running"].includes(job.status) && (
                        <div className="bg-amber-500/10 border-b border-amber-500/25 px-3 py-2 text-xs flex items-center gap-2 text-amber-300">
                            <Spinner className="size-3.5 text-amber-400 shrink-0" />
                            <span>
                                <b>Mengunduh Docker Sandbox (~1.5 GB):</b> Strix sedang mengunduh image container untuk isolasi eksekusi. Ini hanya terjadi <b>sekali di peluncuran pertama</b>. Harap biarkan proses berjalan dan jangan dihentikan sampai selesai.
                            </span>
                        </div>
                    )}
                <div
                    ref={logContainerRef}
                    className="flex-1 min-h-[340px] max-h-[480px] overflow-y-auto p-3 font-mono text-[11px] leading-relaxed bg-zinc-950 text-zinc-100 dark:bg-black"
                >
                    {filteredLogs.length === 0 ? (
                        <div className="h-full flex flex-col items-center justify-center text-zinc-500 py-16 gap-2">
                            <TerminalIcon className="size-8 opacity-40" />
                            <p>Belum ada output log. Jalankan scan untuk melihat stream real-time.</p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-1">
                            {filteredLogs.map((log) => {
                                let badgeColor = "text-zinc-400";
                                if (log.stream === "system") badgeColor = "text-cyan-400 font-semibold";
                                if (log.stream === "stderr") badgeColor = "text-rose-400";
                                if (log.stream === "stdout") badgeColor = "text-zinc-300";

                                return (
                                    <div key={log.seq} className="flex items-start gap-2 break-all hover:bg-zinc-900/60 px-1 py-0.5 rounded">
                                        <span className="text-[10px] text-zinc-600 select-none shrink-0 w-8 text-right font-mono">
                                            #{log.seq}
                                        </span>
                                        <span className={`text-[10px] uppercase select-none shrink-0 font-mono ${badgeColor}`}>
                                            [{log.stream}]
                                        </span>
                                        <span className="text-zinc-200 whitespace-pre-wrap flex-1">
                                            {log.text}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Footer bar */}
                <div className="flex items-center justify-between px-3 py-1.5 border-t bg-muted/20 text-[11px] text-muted-foreground">
                    <div className="flex items-center gap-2">
                        <span>Total: {logs.length} baris</span>
                        {job?.dropped_logs ? (
                            <span className="text-amber-500">
                                ({job.dropped_logs} baris dipangkas)
                            </span>
                        ) : null}
                    </div>

                    <label className="flex items-center gap-1.5 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            checked={autoScroll}
                            onChange={(e) => setAutoScroll(e.target.checked)}
                            className="rounded size-3 text-primary"
                        />
                        <span>Auto-scroll ke bawah</span>
                    </label>
                </div>
            </CardContent>
        </Card>
    );
}
