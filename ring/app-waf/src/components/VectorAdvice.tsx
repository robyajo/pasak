import type { ActiveVector } from "@/hooks/useWafConsole";
interface VectorAdviceProps { vector: ActiveVector | null; onClear: () => void }
interface FieldProps { label: string; value?: string | null }
import { XIcon } from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

function methodVariant(method: string): "default" | "secondary" | "destructive" | "outline" {
    switch (method) {
        case "GET":
            return "default";
        case "DELETE":
            return "destructive";
        case "POST":
            return "secondary";
        default:
            return "outline";
    }
}

function Field({ label, value }: FieldProps) {
    if (!value) {
        return null;
    }

    return (
        <div className="flex flex-col gap-0.5">
            <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                {label}
            </span>
            <span className="font-mono text-xs break-all">{value}</span>
        </div>
    );
}

/**
 * Kartu saran yang muncul setelah sebuah vektor serangan dipilih: menegaskan
 * metode HTTP yang dipilih otomatis, alasan, payload, ekspektasi, dan langkah.
 */
export default function VectorAdvice({ vector, onClear }: VectorAdviceProps) {
    if (!vector) {
        return null;
    }

    const method = vector.method ?? "GET";
    const payload =
        vector.payload ??
        (vector.upload
            ? `${vector.upload.filename} — ${vector.upload.content}`
            : null);
    const advice = vector.advice;
    const steps = advice.steps ?? [];

    return (
        <Alert className="mt-4">
            <AlertTitle className="flex flex-wrap items-center gap-2">
                <span className="text-xs font-bold uppercase tracking-wider">
                    Saran Vektor
                </span>
                <Badge variant={methodVariant(method)}>
                    {method} · otomatis
                </Badge>
                {vector.group && (
                    <span className="text-xs font-normal text-muted-foreground">
                        {vector.group}
                    </span>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    size="xs"
                    onClick={onClear}
                    className="ml-auto"
                >
                    <XIcon data-icon="inline-start" />
                    Tutup
                </Button>
            </AlertTitle>
            <AlertDescription>
                <span className="mt-2 block text-sm font-semibold text-foreground">
                    {vector.label}
                </span>

                {advice.reason && (
                    <p className="mt-1 text-xs leading-relaxed">
                        <span className="font-semibold">Metode: </span>
                        {advice.reason}
                    </p>
                )}

                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                    <Field label="Path / Query" value={vector.path} />
                    <Field label="Payload" value={payload} />
                    {advice.expect && (
                        <div className="flex flex-col gap-0.5 sm:col-span-2">
                            <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Ekspektasi
                            </span>
                            <span className="text-xs">{advice.expect}</span>
                        </div>
                    )}
                </div>

                {steps.length > 0 && (
                    <div className="mt-3">
                        <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Langkah
                        </span>
                        <ol className="mt-1 list-inside list-decimal text-xs">
                            {steps.map((step) => (
                                <li key={step}>{step}</li>
                            ))}
                        </ol>
                    </div>
                )}
            </AlertDescription>
        </Alert>
    );
}
