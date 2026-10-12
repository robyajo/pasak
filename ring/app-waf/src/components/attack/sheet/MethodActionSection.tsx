import { Alert, AlertDescription } from "@/components/ui/alert";
import { ScrollArea } from "@/components/ui/scroll-area";
import type { SheetMethodState } from "./types";

interface MethodActionSectionProps {
    method: SheetMethodState;
}

export default function MethodActionSection({
    method,
}: MethodActionSectionProps) {
    return (
        <section className="flex flex-col gap-2">
            <h3 className="text-xs font-bold tracking-wide text-muted-foreground uppercase">
                Penjelasan aksi
            </h3>
            <Alert>
                <AlertDescription className="text-xs leading-relaxed">
                    {method.desc}
                </AlertDescription>
            </Alert>
            <div className="rounded-lg border bg-muted/20 p-3">
                <p className="mb-1 text-[11px] font-bold text-muted-foreground">
                    REQUEST DIKIRIM
                </p>
                <ScrollArea className="max-h-32 rounded bg-background/50">
                    <code className="block p-2 font-mono text-xs break-all whitespace-pre-wrap">
                        {method.request}
                    </code>
                </ScrollArea>
            </div>
        </section>
    );
}
