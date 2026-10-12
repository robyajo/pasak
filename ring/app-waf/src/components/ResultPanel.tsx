import type { ProxyResult } from "@/hooks/useWafConsole";
interface ResultPanelProps { result: ProxyResult | null; tab: string; onTab: (id: string) => void; onOpenPreview: () => void; loading: boolean; progress: string }
interface MetaProps { label: string; value: string; mono?: boolean }
import { ExpandIcon, TriangleAlertIcon } from "lucide-react";
import PreviewFrame from "@/components/PreviewFrame";
import SurfacePanel from "@/components/SurfacePanel";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Empty, EmptyDescription, EmptyTitle } from "@/components/ui/empty";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "cn";

const TABS = [
    { id: "preview", label: "Pratinjau" },
    { id: "body", label: "Body" },
    { id: "res", label: "Response Headers" },
    { id: "req", label: "Request Headers" },
    { id: "surface", label: "Permukaan Serangan" },
];

function statusVariant(status: number): "destructive" | "secondary" | "default" | "outline" {
    if (status === 403 || status === 429 || status >= 500 || status === 0) {
        return "destructive";
    }
    if (status >= 400) {
        return "secondary";
    }
    if (status >= 200) {
        return "default";
    }
    return "outline";
}

function Meta({ label, value, mono = false }: MetaProps) {
    return (
        <div className="rounded-lg border px-3 py-2">
            <div className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                {label}
            </div>
            <div className={cn("truncate", mono && "font-mono")}>{value}</div>
        </div>
    );
}

/**
 * Origin target dipakai sebagai <base> pada iframe pratinjau.
 * Memakai `base_url` dari proxy bila ada, atau menurunkannya dari `url`.
 */
function baseUrlOf(result: ProxyResult | null | undefined): string {
    if (!result) {
        return "";
    }

    if (result.base_url) {
        return result.base_url;
    }

    if (typeof result.url !== 'string' || result.url === '') {
        return '';
    }

    try {
        return new URL(result.url).origin;
    } catch {
        return '';
    }
}

export default function ResultPanel({
    result,
    tab,
    onTab,
    onOpenPreview,
    loading,
    progress,
}: ResultPanelProps) {
    const success = result?.success === true;

    const badgeText = success
        ? `${result?.status ?? 0} ${result?.status_text ?? ''}`
        : result
          ? "GAGAL"
          : "MENUNGGU";

    const renderText = () => {
        if (!result) {
            return 'Belum ada permintaan. Isi form lalu klik "Kirim".';
        }

        if (!result.success) {
            return result.message ?? "Terjadi kesalahan.";
        }

        if (tab === "res") {
            return result.response_headers || "(kosong)";
        }

        if (tab === "req") {
            return result.request_headers || "(kosong)";
        }

        return result.body || "(kosong)";
    };

    const showFrame = success && tab === "preview";
    const showSurface = success && tab === "surface";

    return (
        <Card className="h-full xl:sticky xl:top-20">
            <CardHeader>
                <CardTitle>Hasil</CardTitle>
                <Badge variant={success ? statusVariant(result?.status ?? 0) : "outline"}>
                    {badgeText}
                </Badge>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col">
                {success && (
                    <>
                        <div className="mb-4 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                            <Meta label="IP" value={result?.ip ?? ""} mono />
                            <Meta
                                label="Durasi"
                                value={`${result?.duration_ms ?? 0} ms`}
                            />
                            <Meta
                                label="Ukuran"
                                value={`${result?.size ?? 0} B`}
                            />
                            <Meta
                                label="Blokir"
                                value={
                                    result?.blocked === true ? "YA" : "tidak"
                                }
                            />
                        </div>
                        {result?.blocked === true ? (
                            <Badge variant="destructive" className="mb-3 w-fit">
                                DIBLOKIR
                            </Badge>
                        ) : (
                            <Badge variant="default" className="mb-3 w-fit">
                                LOLOS
                            </Badge>
                        )}

                        <div className="mb-3 flex flex-wrap items-center gap-2">
                            <Tabs value={tab} onValueChange={onTab}>
                                <TabsList>
                                    {TABS.map((item) => (
                                        <TabsTrigger
                                            key={item.id}
                                            value={item.id}
                                        >
                                            {item.label}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                            </Tabs>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={onOpenPreview}
                                className="ml-auto"
                            >
                                <ExpandIcon data-icon="inline-start" />
                                Buka Penuh
                            </Button>
                        </div>
                    </>
                )}

                <div className="flex min-h-[360px] flex-1 overflow-hidden rounded-lg border">
                    {loading ? (
                        <div className="flex w-full flex-col gap-2 p-4">
                            <Skeleton className="h-4 w-1/3" />
                            <Skeleton className="h-4 w-full" />
                            <Skeleton className="h-4 w-2/3" />
                            <p className="text-xs text-muted-foreground">
                                {progress || "Mengirim permintaan..."}
                            </p>
                        </div>
                    ) : showFrame ? (
                        <PreviewFrame
                            body={result?.body ?? ""}
                            baseUrl={baseUrlOf(result)}
                            title="Pratinjau target"
                        />
                    ) : showSurface ? (
                        <SurfacePanel
                            body={result?.body ?? ""}
                            contentType={result?.content_type ?? ""}
                            baseUrl={baseUrlOf(result)}
                        />
                    ) : !result ? (
                        <Empty>
                            <EmptyTitle>Belum ada hasil</EmptyTitle>
                            <EmptyDescription>{renderText()}</EmptyDescription>
                        </Empty>
                    ) : (
                        <pre className="h-full w-full overflow-auto p-4 font-mono text-xs leading-relaxed whitespace-pre-wrap break-words">
                            {renderText()}
                        </pre>
                    )}
                </div>

                {success && result?.truncated === true && (
                    <Alert variant="destructive" className="mt-2">
                        <TriangleAlertIcon data-icon="inline-start" />
                        <AlertTitle>Body dipotong</AlertTitle>
                        <AlertDescription>
                            Body dipotong pada 200 KB.
                        </AlertDescription>
                    </Alert>
                )}
            </CardContent>
        </Card>
    );
}
