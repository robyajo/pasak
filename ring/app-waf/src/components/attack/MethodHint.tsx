import type { MethodProbeResult } from "@/components/attack/attackBuilder";
interface MethodHintProps { result: MethodProbeResult | null; running: boolean; onApplyMethod: (method: string) => void }
import { parseAllowedMethods } from "@/components/attack/attackBuilder";
import { TriangleAlertIcon } from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";

/**
 * Saran metode saat server membalas 405 (Method Not Allowed).
 *
 * Menampilkan metode yang benar-benar didukung server dan tombol untuk
 * mengulang serangan dengan metode tersebut.
 */
export default function MethodHint({ result, running, onApplyMethod }: MethodHintProps) {
    const hint = parseAllowedMethods(result);

    if (!hint) {
        return null;
    }

    return (
        <Alert>
            <TriangleAlertIcon />
            <AlertTitle>
                Metode tidak didukung (405)
                <span className="ml-2 text-[11px] font-normal text-muted-foreground">
                    dari {hint.source}
                </span>
            </AlertTitle>
            <AlertDescription>
                Server menolak{" "}
                <span className="font-mono">
                    {hint.current || "metode ini"}
                </span>{" "}
                pada route ini. Metode yang didukung:{" "}
                <span className="font-mono">
                    {hint.allowed.join(", ")}
                </span>
                . Status <span className="font-mono">405</span> (bukan 403)
                berarti WAF tidak memblokir — router aplikasi yang menolak.
            </AlertDescription>
            {hint.alternatives.length > 0 && (
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <span className="text-[11px] text-muted-foreground">
                        Coba ulang dengan metode:
                    </span>
                    {hint.alternatives.map((method) => (
                        <Button
                            key={method}
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={running}
                            onClick={() => onApplyMethod(method)}
                        >
                            {method}
                        </Button>
                    ))}
                </div>
            )}
        </Alert>
    );
}
