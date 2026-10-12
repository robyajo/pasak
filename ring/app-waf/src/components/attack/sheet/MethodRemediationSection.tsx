import { WrenchIcon } from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { ScrollArea } from "@/components/ui/scroll-area";

interface MethodRemediationSectionProps {
    vulnName: string;
    fix: string;
}

export default function MethodRemediationSection({
    vulnName,
    fix,
}: MethodRemediationSectionProps) {
    return (
        <section className="flex flex-col gap-2">
            <h3 className="text-xs font-bold tracking-wide text-muted-foreground uppercase">
                🛠️ Penanganan
            </h3>
            <Alert>
                <WrenchIcon data-icon="inline-start" />
                <AlertTitle>Cara menutup celah {vulnName}</AlertTitle>
                <AlertDescription className="mt-1">
                    <ScrollArea className="max-h-48 pr-2">
                        <div className="text-xs leading-relaxed whitespace-pre-wrap">
                            {fix}
                        </div>
                    </ScrollArea>
                </AlertDescription>
            </Alert>
        </section>
    );
}
