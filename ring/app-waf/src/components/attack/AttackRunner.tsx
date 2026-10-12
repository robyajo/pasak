import type { SurfaceFinding } from "@/lib/surface";
import type { SendRawFn } from "@/hooks/useWafConsole";
interface RunnerProps { finding: SurfaceFinding | null; fallbackPath: string; sendRaw: SendRawFn }
import { useEffect, useMemo, useState } from "react";
import {
    buildAttackJobs,
    injectableFields,
    isAttackable,
} from "@/components/attack/attackBuilder";
import { useAttackRunner } from "@/components/attack/useAttackRunner";
import AttackResults from "@/components/attack/AttackResults";
import MethodHint from "@/components/attack/MethodHint";
import { cn } from "cn";
import { SwordsIcon, EraserIcon } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Spinner } from "@/components/ui/spinner";

/**
 * Menyusun dan menjalankan serangan untuk satu temuan: pilih field injeksi,
 * pilih payload, lalu kirim lewat `sendRaw`.
 */
export default function AttackRunner({ finding, fallbackPath, sendRaw }: RunnerProps) {
    const { running, results, progress, run, clear } = useAttackRunner(sendRaw);
    const [selectedPayloads, setSelectedPayloads] = useState<string[]>([]);
    const [fieldName, setFieldName] = useState("");
    const [customPayload, setCustomPayload] = useState("");

    const fields = useMemo(() => injectableFields(finding), [finding]);
    const payloads = finding?.payloads ?? [];

    useEffect(() => {
        setSelectedPayloads(payloads.slice(0, 1));
        setFieldName(fields[0] ?? "");
        setCustomPayload("");
        clear();
        // Reset hanya saat temuan berubah.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [finding]);

    if (!finding) {
        return (
            <Card>
                <CardContent>
                    <Alert>
                        <AlertDescription>
                            Pilih temuan di kiri untuk mulai menyusun serangan.
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>
        );
    }

    const togglePayload = (payload: string): void => {
        setSelectedPayloads((prev) =>
            prev.includes(payload)
                ? prev.filter((item) => item !== payload)
                : [...prev, payload],
        );
    };

    const custom = customPayload.trim();
    const activePayloads =
        custom !== ""
            ? [custom]
            : selectedPayloads.length > 0
              ? selectedPayloads
              : payloads.length > 0
                ? [payloads[0]]
                : [""];

    const runOne = (): void => {
        run(
            buildAttackJobs(
                finding,
                activePayloads.slice(0, 1),
                fieldName,
                fallbackPath,
            ),
        );
    };

    const runAll = (): void => {
        run(buildAttackJobs(finding, activePayloads, fieldName, fallbackPath));
    };

    const lastResult = results[results.length - 1] ?? null;

    // Ulangi payload terakhir dengan metode yang disarankan server (405).
    const applyMethod = (method: string): void => {
        const payload = lastResult?.payload ?? activePayloads[0] ?? "";

        run(
            buildAttackJobs(
                finding,
                [payload],
                fieldName,
                fallbackPath,
                method,
            ),
        );
    };

    const attackable = isAttackable(finding);

    return (
        <div className="flex flex-col gap-4">
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge>{finding.method}</Badge>
                        <CardTitle>{finding.title}</CardTitle>
                    </div>
                    <CardDescription className="font-mono break-all">
                        {finding.target}
                    </CardDescription>
                    {finding.recommendation && (
                        <CardDescription>
                            {finding.recommendation}
                        </CardDescription>
                    )}
                </CardHeader>
                <CardContent>
                    {!attackable ? (
                        <Alert>
                            <AlertDescription>
                                Temuan ini tidak punya request konkret (mis. komentar
                                atau indikasi error), jadi tidak bisa ditembak langsung.
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <>
                            {fields.length > 1 && (
                                <div className="mt-4 flex flex-col gap-1">
                                    <Label htmlFor="attack-field">
                                        Field Injeksi
                                    </Label>
                                    <Select value={fieldName} onValueChange={(value) => setFieldName(value ?? "")}>
                                        <SelectTrigger id="attack-field" className="w-full">
                                            <SelectValue placeholder="Pilih field" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {fields.map((field) => (
                                                    <SelectItem key={field} value={field}>
                                                        {field}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {payloads.length > 0 && (
                                <div className="mt-4">
                                    <p className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Payload (klik untuk pilih)
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {payloads.map((payload) => (
                                            <Button
                                                key={payload}
                                                type="button"
                                                size="sm"
                                                variant={
                                                    selectedPayloads.includes(payload)
                                                        ? "default"
                                                        : "outline"
                                                }
                                                onClick={() =>
                                                    togglePayload(payload)
                                                }
                                                className={cn("h-auto font-mono text-[11px]")}
                                            >
                                                {payload}
                                            </Button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="mt-4 flex flex-col gap-1">
                                <Label htmlFor="attack-custom">
                                    Payload Kustom (opsional, menimpa pilihan)
                                </Label>
                                <Input
                                    id="attack-custom"
                                    value={customPayload}
                                    onChange={(event) =>
                                        setCustomPayload(event.target.value)
                                    }
                                    placeholder="mis. 1' UNION SELECT ..."
                                    className="font-mono"
                                />
                            </div>

                            <Separator className="mt-5" />

                            <div className="mt-5 flex flex-wrap items-center gap-3">
                                <Button
                                    type="button"
                                    onClick={runOne}
                                    disabled={running}
                                    variant="destructive"
                                >
                                    <SwordsIcon data-icon="inline-start" />
                                    Kirim 1 Payload
                                </Button>
                                <Button
                                    type="button"
                                    onClick={runAll}
                                    disabled={running}
                                    variant="outline"
                                >
                                    <SwordsIcon data-icon="inline-start" />
                                    Uji Semua Terpilih ({activePayloads.length})
                                </Button>
                                <Button
                                    type="button"
                                    onClick={clear}
                                    disabled={running || results.length === 0}
                                    variant="ghost"
                                >
                                    <EraserIcon data-icon="inline-start" />
                                    Bersihkan
                                </Button>
                                {progress !== "" && (
                                    <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                        {running && <Spinner />}
                                        {progress}
                                    </span>
                                )}
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>

            <MethodHint
                result={lastResult}
                running={running}
                onApplyMethod={applyMethod}
            />

            <AttackResults
                results={results}
                running={running}
                progress={progress}
            />
        </div>
    );
}
