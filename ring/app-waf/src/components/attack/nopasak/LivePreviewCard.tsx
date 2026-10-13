import { useState } from "react";
import {
    ExternalLinkIcon,
    EyeIcon,
    RefreshCwIcon,
    ShieldCheckIcon,
} from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { LAB_BROWSERS } from "@/stores/lab";
import { PREVIEW_PAGES } from "./constants";
import type { LivePreviewCardProps } from "./types";

export function LivePreviewCard({
    previewUrl,
    previewSrc,
    previewPage,
    previewTick,
    onPageChange,
    onRefresh,
    sendRaw,
    attackerIp,
    browser,
    deviceId,
    busy,
    target,
}: LivePreviewCardProps) {
    const [blockCheck, setBlockCheck] = useState<{
        status?: number;
        blocked: boolean;
    } | null>(null);
    const [checking, setChecking] = useState(false);

    /** Cek apakah IP penyerang kini diblokir (GET / memakai IP spoof). */
    const checkAttackerBlocked = async (): Promise<void> => {
        setChecking(true);
        try {
            const res = await sendRaw({
                target,
                path: "/",
                method: "GET",
                ip: attackerIp.trim(),
            });
            setBlockCheck({
                status: res.status,
                blocked:
                    res.success === true &&
                    (res.status === 403 || res.status === 429),
            });
        } finally {
            setChecking(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>👁️ Preview Korban (live)</CardTitle>
                <CardAction>
                    <Tooltip>
                        <TooltipTrigger
                            render={
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon-sm"
                                    onClick={onRefresh}
                                >
                                    <RefreshCwIcon data-icon="inline-start" />
                                </Button>
                            }
                        />
                        <TooltipContent>Segarkan preview</TooltipContent>
                    </Tooltip>
                </CardAction>
                <CardDescription className="font-mono break-all">
                    {previewUrl}
                </CardDescription>
                <CardDescription className="font-mono break-all">
                    🎭 {attackerIp} · 🌐{" "}
                    {LAB_BROWSERS.find((b) => b.code === browser)?.label ??
                        browser}{" "}
                    · 🖥️ {deviceId}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
                <div className="flex flex-wrap gap-1 text-[11px]">
                    {PREVIEW_PAGES.map((page) => (
                        <Button
                            key={page}
                            type="button"
                            size="sm"
                            variant={
                                previewPage === page ? "secondary" : "outline"
                            }
                            onClick={() => onPageChange(page)}
                            className="font-mono"
                        >
                            {page}
                        </Button>
                    ))}
                    <Button
                        variant="outline"
                        size="sm"
                        render={
                            <a
                                href={previewUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLinkIcon data-icon="inline-start" />⬈
                                tab baru
                            </a>
                        }
                    />
                </div>
                <div className="overflow-hidden rounded-lg bg-white ring-1 ring-foreground/10">
                    <iframe
                        key={previewSrc}
                        src={previewSrc}
                        title="Preview aplikasi yang diserang"
                        sandbox="allow-forms allow-scripts"
                        className="h-[560px] w-full bg-white"
                    />
                </div>
                <p className="flex items-center gap-1 text-[11px] text-muted-foreground">
                    <EyeIcon data-icon="inline-start" />
                    Otomatis segar tiap serangan dikirim. Buka <code>/</code>{" "}
                    untuk Monitor, <code>/upload</code> untuk bukti file masuk.
                </p>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-auto whitespace-normal py-1.5 text-left"
                        onClick={checkAttackerBlocked}
                        disabled={busy !== null || checking}
                    >
                        {checking ? (
                            <Spinner data-icon="inline-start" />
                        ) : (
                            <ShieldCheckIcon data-icon="inline-start" />
                        )}
                        {checking
                            ? "Mengecek..."
                            : "🛡️ Cek apakah IP penyerang diblokir"}
                    </Button>
                    {blockCheck !== null &&
                        (blockCheck.blocked ? (
                            <Badge
                                variant="destructive"
                                className="h-auto whitespace-normal py-1 text-left"
                            >
                                IP penyerang DIBLOKIR ({blockCheck.status}) —
                                preview di atas ikut menampilkan halaman blokir
                                karena memakai IP yang sama
                            </Badge>
                        ) : (
                            <Badge
                                variant="secondary"
                                className="h-auto whitespace-normal py-1 text-left"
                            >
                                IP penyerang BELUM diblokir ({blockCheck.status})
                            </Badge>
                        ))}
                </div>
                <Alert>
                    <ShieldCheckIcon data-icon="inline-start" />
                    <AlertTitle>Preview kosong / ditolak browser?</AlertTitle>
                    <AlertDescription className="flex flex-col gap-2">
                        <span>
                            Itu artinya target mengirim{" "}
                            <code>X-Frame-Options: SAMEORIGIN</code> atau{" "}
                            <code>frame-ancestors</code> (proteksi clickjacking
                            — justru nilai plus untuk audit). Serangan via
                            tombol di kiri tetap jalan; hanya bingkai preview
                            yang diblokir. Klik <b>⬈ tab baru</b> di atas untuk
                            melihat langsung.
                        </span>
                        <span>
                            Khusus lab lokal, izinkan bingkai dari konsol ini
                            dengan header di aplikasi target:
                        </span>
                        <code className="break-all">
                            Content-Security-Policy: frame-ancestors
                            &apos;self&apos; http://localhost:5175
                        </code>
                    </AlertDescription>
                </Alert>
            </CardContent>
        </Card>
    );
}
