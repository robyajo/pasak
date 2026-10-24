import {
    AlertTriangleIcon,
    BotIcon,
    CheckCircle2Icon,
    CpuIcon,
    FolderArchiveIcon,
    RefreshCwIcon,
    Settings2Icon,
    SlidersHorizontalIcon,
    XCircleIcon,
} from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import type { StrixStatus } from "@/types/strix";

interface StrixStatusBannerProps {
    status: StrixStatus | null;
    onRefresh: () => void;
    onOpenConfig: () => void;
}

export function StrixStatusBanner({
    status,
    onRefresh,
    onOpenConfig,
}: StrixStatusBannerProps) {
    const isStrixInstalled = Boolean(status?.strix?.installed);
    const isDockerRunning = Boolean(status?.docker?.running);
    const hasApiKey = Boolean(status?.config?.has_api_key);
    const isPlaceholderKey = Boolean(status?.config?.is_placeholder_key);
    const provider = status?.config?.provider || "openrouter";

    const isReady = isStrixInstalled && isDockerRunning && hasApiKey;

    const getProviderLabel = () => {
        switch (provider) {
            case "gemini":
                return "Google Gemini";
            case "openai":
                return "ChatGPT (OpenAI)";
            case "openrouter":
                return "OpenRouter";
            case "opencode":
                return "OpenCode / Lokal";
            default:
                return "Kustom LLM";
        }
    };

    return (
        <div className="flex flex-col gap-4 mb-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2.5">
                    <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary border border-primary/20">
                        <BotIcon className="size-5" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="text-base font-bold tracking-tight">
                                Strix Autonomous AI Pentesting
                            </h2>
                            <Badge
                                variant={isReady ? "default" : "destructive"}
                                className="text-[10px] uppercase font-mono"
                            >
                                {isReady ? "Sistem Siap Audit" : "Perlu Setup"}
                            </Badge>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Agent AI otonom pemetaan celah WAF, fuzzing
                            kerentanan & pembuatan PoC otomatis. Mendukung{" "}
                            <b>Gemini</b>, <b>ChatGPT</b>, <b>OpenRouter</b>, &{" "}
                            <b>OpenCode</b>.
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={onRefresh}
                        className="text-xs h-8"
                    >
                        <RefreshCwIcon className="size-3.5 mr-1" />
                        Periksa Status
                    </Button>
                    <Button
                        variant="default"
                        size="sm"
                        onClick={onOpenConfig}
                        className="text-xs h-8"
                    >
                        <Settings2Icon className="size-3.5 mr-1" />
                        Pilih Provider & Kunci API
                    </Button>
                </div>
            </div>

            {/* Grid 4 Kartu Kesiapan */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                {/* 1. Strix CLI */}
                <Card size="sm" className="bg-card/50">
                    <CardContent className="pt-4 flex flex-col justify-between h-full gap-2">
                        <div className="flex items-center justify-between">
                            <span className="text-[11px] font-semibold text-muted-foreground uppercase flex items-center gap-1.5">
                                <CpuIcon className="size-3.5" /> Strix CLI
                            </span>
                            {isStrixInstalled ? (
                                <span className="inline-flex items-center text-[11px] font-medium text-emerald-500">
                                    <CheckCircle2Icon className="size-3.5 mr-1" />{" "}
                                    Terpasang
                                </span>
                            ) : (
                                <span className="inline-flex items-center text-[11px] font-medium text-rose-500">
                                    <XCircleIcon className="size-3.5 mr-1" />{" "}
                                    Hilang
                                </span>
                            )}
                        </div>
                        <div className="font-mono text-xs truncate">
                            {status?.strix?.version ||
                                status?.strix?.path ||
                                "strix"}
                        </div>
                        <p className="text-[11px] text-muted-foreground truncate">
                            {isStrixInstalled
                                ? "Binary executable aktif"
                                : "Belum terpasang di PATH"}
                        </p>
                    </CardContent>
                </Card>

                {/* 2. Docker Daemon */}
                <Card size="sm" className="bg-card/50">
                    <CardContent className="pt-4 flex flex-col justify-between h-full gap-2">
                        <div className="flex items-center justify-between">
                            <span className="text-[11px] font-semibold text-muted-foreground uppercase flex items-center gap-1.5">
                                <SlidersHorizontalIcon className="size-3.5" />{" "}
                                Docker Daemon
                            </span>
                            {isDockerRunning ? (
                                <span className="inline-flex items-center text-[11px] font-medium text-emerald-500">
                                    <CheckCircle2Icon className="size-3.5 mr-1" />{" "}
                                    Aktif
                                </span>
                            ) : (
                                <span className="inline-flex items-center text-[11px] font-medium text-amber-500">
                                    <AlertTriangleIcon className="size-3.5 mr-1" />{" "}
                                    Non-Aktif
                                </span>
                            )}
                        </div>
                        <div className="font-mono text-xs truncate">
                            {status?.docker?.version || "Docker Engine"}
                        </div>
                        <p className="text-[11px] text-muted-foreground truncate">
                            {!isDockerRunning
                                ? "Integrasi WSL 2 belum aktif"
                                : status?.docker?.sandbox_ready
                                    ? "Sandbox container siap (v1.3.0)"
                                    : "Image sandbox diunduh otomatis di scan pertama"}
                        </p>
                    </CardContent>
                </Card>

                {/* 3. LLM Engine & API Key */}
                <Card
                    size="sm"
                    className="bg-card/50 cursor-pointer hover:border-primary/50 transition-colors"
                    onClick={onOpenConfig}
                >
                    <CardContent className="pt-4 flex flex-col justify-between h-full gap-2">
                        <div className="flex items-center justify-between">
                            <span className="text-[11px] font-semibold text-muted-foreground uppercase flex items-center gap-1.5">
                                <BotIcon className="size-3.5" />{" "}
                                {getProviderLabel()}
                            </span>
                            {hasApiKey ? (
                                <span className="inline-flex items-center text-[11px] font-medium text-emerald-500">
                                    <CheckCircle2Icon className="size-3.5 mr-1" />{" "}
                                    Kunci Aktif
                                </span>
                            ) : isPlaceholderKey ? (
                                <span className="inline-flex items-center text-[11px] font-medium text-amber-500">
                                    <AlertTriangleIcon className="size-3.5 mr-1" />{" "}
                                    Placeholder
                                </span>
                            ) : (
                                <span className="inline-flex items-center text-[11px] font-medium text-rose-500">
                                    <XCircleIcon className="size-3.5 mr-1" />{" "}
                                    Kunci Kosong
                                </span>
                            )}
                        </div>
                        <div className="font-mono text-xs truncate font-semibold text-primary">
                            {status?.config?.llm || "openrouter/z-ai/glm-5.3"}
                        </div>
                        <p className="text-[11px] text-muted-foreground truncate">
                            {hasApiKey
                                ? "Klik untuk ganti model / kunci"
                                : "Klik untuk pasang API Key"}
                        </p>
                    </CardContent>
                </Card>

                {/* 4. Riwayat & Runs */}
                <Card size="sm" className="bg-card/50">
                    <CardContent className="pt-4 flex flex-col justify-between h-full gap-2">
                        <div className="flex items-center justify-between">
                            <span className="text-[11px] font-semibold text-muted-foreground uppercase flex items-center gap-1.5">
                                <FolderArchiveIcon className="size-3.5" />{" "}
                                Riwayat Audit
                            </span>
                            <Badge variant="secondary" className="text-[10px]">
                                {status?.run_count ?? 0} Sesi
                            </Badge>
                        </div>
                        <div className="text-xs font-semibold">
                            {status?.finding_count ?? 0} Temuan Kerentanan
                        </div>
                        <p className="text-[11px] text-muted-foreground truncate">
                            Direktori: <code>strix_runs/</code>
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Peringatan Bantuan jika ada yang belum siap */}
            {!isStrixInstalled && (
                <Alert variant="destructive">
                    <AlertTriangleIcon className="size-4" />
                    <AlertTitle>Strix CLI belum terpasang di sistem</AlertTitle>
                    <AlertDescription className="text-xs flex flex-col gap-1 mt-1">
                        <span>
                            Strix adalah AI Pentesting Agent otonom berbasis
                            Python. Pasang melalui pipx atau pip di terminal
                            Anda:
                        </span>
                        <div className="bg-background/80 p-2 rounded font-mono text-[11px] border border-border select-all">
                            pip install strix
                            <span className="text-muted-foreground">
                                {" "}
                                # atau clone repositori
                                https://github.com/usestrix/strix
                            </span>
                        </div>
                        <span>
                            Setelah terpasang, periksa dengan{" "}
                            <code>strix --version</code> atau set variabel{" "}
                            <code>STRIX_BIN=/path/to/strix</code> di{" "}
                            <code>.env</code>.
                        </span>
                    </AlertDescription>
                </Alert>
            )}

            {!isDockerRunning && isStrixInstalled && (
                <Alert
                    variant="default"
                    className="border-amber-500/50 bg-amber-500/10"
                >
                    <AlertTriangleIcon className="size-4 text-amber-500" />
                    <AlertTitle className="text-amber-600 dark:text-amber-400">
                        Docker Daemon belum aktif atau integrasi WSL 2 belum
                        dicentang
                    </AlertTitle>
                    <AlertDescription className="text-xs text-muted-foreground flex flex-col gap-1 mt-1">
                        <span>
                            {status?.docker?.message ||
                                "Strix menjalankan container tool terisolasi untuk eksekusi perintah pentest. Pastikan Docker Desktop berjalan dan centang integrasi WSL 2 di Docker Desktop → Settings → Resources → WSL Integration."}
                        </span>
                    </AlertDescription>
                </Alert>
            )}

            {isPlaceholderKey && (
                <Alert
                    variant="default"
                    className="border-primary/50 bg-primary/5"
                >
                    <AlertTriangleIcon className="size-4 text-primary" />
                    <AlertTitle>
                        Kunci API untuk provider {getProviderLabel()} masih
                        placeholder
                    </AlertTitle>
                    <AlertDescription className="text-xs text-muted-foreground flex items-center justify-between gap-4 mt-1">
                        <span>
                            Model aktif adalah{" "}
                            <code>{status?.config?.llm}</code>. Masukkan API Key
                            Anda untuk provider ini agar Strix dapat menjalankan
                            pemindaian.
                        </span>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={onOpenConfig}
                            className="shrink-0 h-7 text-xs"
                        >
                            Pasang API Key Sekarang
                        </Button>
                    </AlertDescription>
                </Alert>
            )}
        </div>
    );
}
