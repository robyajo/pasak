import type { AttackResult } from "@/components/attack/useAttackRunner";
interface ResultRowProps { result: AttackResult }
interface AttackResultsProps { results: AttackResult[]; running: boolean; progress: string }
import { useState } from "react";
import { parseAllowedMethods } from "@/components/attack/attackBuilder";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Empty, EmptyDescription } from "@/components/ui/empty";
import { Progress } from "@/components/ui/progress";
import { Separator } from "@/components/ui/separator";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";

function statusVariant(status: number): "default" | "secondary" | "destructive" | "outline" {
    if (status === 403 || status === 429 || status >= 500) {
        return "destructive";
    }
    if (status >= 400) {
        return "outline";
    }
    if (status >= 200 && status < 300) {
        return "default";
    }
    return "secondary";
}

function ResultRow({ result }: ResultRowProps) {
    const [open, setOpen] = useState(false);
    const hint = parseAllowedMethods(result);

    return (
        <div className="rounded-lg border p-3">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant={statusVariant(result.status)}>
                    {result.status || "ERR"}
                </Badge>
                <Badge variant="outline">
                    {result.request.method}
                </Badge>
                {result.blocked && (
                    <Badge variant="destructive">
                        DIBLOKIR
                    </Badge>
                )}
                <span className="font-mono text-[11px] text-muted-foreground">
                    {result.durationMs} ms · {result.size} B
                </span>
                <Button
                    type="button"
                    variant="link"
                    size="sm"
                    onClick={() => setOpen((value) => !value)}
                    className="ml-auto"
                >
                    {open ? "Sembunyikan" : "Lihat body"}
                </Button>
            </div>

            <div className="mt-2 font-mono text-[11px] break-all text-muted-foreground">
                {result.request.method} {result.request.path}
            </div>

            {hint && (
                <div className="mt-1 text-[11px] text-muted-foreground">
                    Metode didukung server: {hint.allowed.join(", ")}
                </div>
            )}

            {result.payload && (
                <div className="mt-1 font-mono text-[11px] break-all text-muted-foreground">
                    payload: {result.payload}
                </div>
            )}

            {open && (
                <>
                    <Separator className="mt-2" />
                    <pre className="mt-2 max-h-48 overflow-auto rounded border p-2 font-mono text-[11px] whitespace-pre-wrap text-muted-foreground">
                        {result.body || "(kosong)"}
                    </pre>
                </>
            )}
        </div>
    );
}

export default function AttackResults({ results, running, progress }: AttackResultsProps) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between gap-2">
                    <CardTitle>Hasil Serangan</CardTitle>
                    {progress !== "" && (
                        <span className="flex items-center gap-2 text-xs text-muted-foreground">
                            {running && <Spinner />}
                            {progress}
                        </span>
                    )}
                </div>
                {running && <Progress value={null} />}
            </CardHeader>
            <CardContent>
                {results.length === 0 ? (
                    running ? (
                        <div className="flex flex-col gap-2">
                            <Skeleton className="h-16 w-full" />
                            <p className="text-xs text-muted-foreground">
                                Menjalankan serangan...
                            </p>
                        </div>
                    ) : (
                        <Empty>
                            <EmptyDescription>
                                Belum ada serangan yang dijalankan.
                            </EmptyDescription>
                        </Empty>
                    )
                ) : (
                    <div className="flex max-h-110 flex-col gap-2 overflow-auto pr-1">
                        {results.map((result, index) => (
                            <ResultRow
                                key={`${result.request.path}-${result.payload}-${index}`}
                                result={result}
                            />
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
