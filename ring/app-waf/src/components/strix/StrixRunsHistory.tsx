import {
    CalendarIcon,
    ChevronRightIcon,
    FileTextIcon,
    FolderArchiveIcon,
    RefreshCwIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    TargetIcon,
} from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import type { StrixRunSummary } from "@/types/strix";

interface StrixRunsHistoryProps {
    runs: StrixRunSummary[];
    loadingDetail: boolean;
    onRefresh: () => void;
    onSelectRun: (name: string) => void;
}

export function StrixRunsHistory({
    runs,
    loadingDetail,
    onRefresh,
    onSelectRun,
}: StrixRunsHistoryProps) {
    return (
        <Card>
            <CardHeader className="pb-3 border-b">
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="text-base flex items-center gap-2">
                            <FolderArchiveIcon className="size-4 text-primary" />
                            Riwayat Sesi Audit Strix
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Seluruh laporan hasil pentest yang tersimpan di direktori <code>strix_runs/</code>.
                        </CardDescription>
                    </div>

                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onRefresh}
                        className="text-xs h-7"
                    >
                        <RefreshCwIcon className="size-3 mr-1" />
                        Segarkan
                    </Button>
                </div>
            </CardHeader>

            <CardContent className="p-0">
                {runs.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-center text-muted-foreground gap-2">
                        <FolderArchiveIcon className="size-8 opacity-40" />
                        <p className="text-xs">Belum ada riwayat audit yang tersimpan.</p>
                        <p className="text-[11px] max-w-sm">
                            Jalankan scan otonom pertama Anda untuk melihat laporan lengkap kerentanan di sini.
                        </p>
                    </div>
                ) : (
                    <div className="divide-y divide-border">
                        {runs.map((run) => {
                            const dateStr = run.start_time
                                ? new Date(run.start_time).toLocaleString("id-ID", {
                                      dateStyle: "medium",
                                      timeStyle: "short",
                                  })
                                : "Waktu tidak tercatat";

                            const hasFindings = run.findings > 0;

                            return (
                                <div
                                    key={run.name}
                                    className="p-3.5 flex flex-wrap items-center justify-between gap-3 hover:bg-muted/40 transition-colors cursor-pointer"
                                    onClick={() => onSelectRun(run.name)}
                                >
                                    <div className="flex items-start gap-3 min-w-0">
                                        <div
                                            className={`flex size-8 shrink-0 items-center justify-center rounded-lg border ${
                                                hasFindings
                                                    ? "bg-rose-500/10 border-rose-500/30 text-rose-500"
                                                    : "bg-emerald-500/10 border-emerald-500/30 text-emerald-500"
                                            }`}
                                        >
                                            {hasFindings ? (
                                                <ShieldAlertIcon className="size-4" />
                                            ) : (
                                                <ShieldCheckIcon className="size-4" />
                                            )}
                                        </div>

                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-xs font-mono truncate">
                                                    {run.name}
                                                </span>
                                                <Badge
                                                    variant="secondary"
                                                    className="text-[10px] uppercase font-mono px-1.5 py-0"
                                                >
                                                    {run.scan_mode || "quick"}
                                                </Badge>
                                                <span className="text-[11px] text-muted-foreground">
                                                    {run.status}
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-2 mt-0.5 text-[11px] text-muted-foreground">
                                                <span className="flex items-center gap-1">
                                                    <TargetIcon className="size-3" /> {run.target}
                                                </span>
                                                <span>•</span>
                                                <span className="flex items-center gap-1">
                                                    <CalendarIcon className="size-3" /> {dateStr}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Findings & Action */}
                                    <div className="flex items-center gap-3">
                                        <div className="flex items-center gap-1">
                                            {run.severity_counts?.critical ? (
                                                <span className="text-[10px] bg-rose-500/20 text-rose-600 dark:text-rose-400 font-bold px-1.5 py-0.5 rounded">
                                                    {run.severity_counts.critical} C
                                                </span>
                                            ) : null}
                                            {run.severity_counts?.high ? (
                                                <span className="text-[10px] bg-orange-500/20 text-orange-600 dark:text-orange-400 font-bold px-1.5 py-0.5 rounded">
                                                    {run.severity_counts.high} H
                                                </span>
                                            ) : null}
                                            {run.severity_counts?.medium ? (
                                                <span className="text-[10px] bg-amber-500/20 text-amber-600 dark:text-amber-400 font-medium px-1.5 py-0.5 rounded">
                                                    {run.severity_counts.medium} M
                                                </span>
                                            ) : null}
                                            {run.findings === 0 && (
                                                <span className="text-[10px] text-emerald-500 font-medium px-1.5 py-0.5">
                                                    Aman (0)
                                                </span>
                                            )}
                                        </div>

                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="h-7 text-xs"
                                            disabled={loadingDetail}
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onSelectRun(run.name);
                                            }}
                                        >
                                            <FileTextIcon className="size-3 mr-1" />
                                            Laporan
                                            <ChevronRightIcon className="size-3 ml-0.5" />
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
