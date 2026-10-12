import type { SurfaceFinding } from "@/lib/surface";
import { cn } from "cn";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Empty, EmptyDescription } from "@/components/ui/empty";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";

interface AttackFindingListProps { findings: SurfaceFinding[]; selectedIndex: number; onSelect: (index: number) => void }
const KIND_LABEL: Record<string, string> = {
    form: "Formulir",
    param: "Parameter",
    endpoint: "Endpoint",
    upload: "Unggah",
    comment: "Komentar",
    error: "Error",
};

/**
 * Daftar temuan dari tab pertama; temuan yang punya `request` bisa dipilih
 * untuk diserang.
 */
export default function AttackFindingList({ findings, selectedIndex, onSelect }: AttackFindingListProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Temuan dari Tab Konsol</CardTitle>
            </CardHeader>
            <CardContent>
                {findings.length === 0 ? (
                    <Empty>
                        <EmptyDescription>
                            Belum ada temuan. Kirim permintaan di tab Konsol Uji terlebih
                            dahulu, lalu buka tab ini.
                        </EmptyDescription>
                    </Empty>
                ) : (
                    <ul className="flex max-h-[600px] flex-col gap-2 overflow-auto pr-1">
                        {findings.map((finding, index) => {
                            const active = index === selectedIndex;
                            const attackable = Boolean(finding.request);

                            return (
                                <li
                                    key={`${finding.kind}-${finding.target}-${index}`}
                                >
                                    <Tooltip>
                                        <TooltipTrigger
                                            render={
                                                <Button
                                                    type="button"
                                                    disabled={!attackable}
                                                    onClick={() => onSelect(index)}
                                                    variant={active ? "secondary" : "outline"}
                                                    className={cn(
                                                        "h-auto w-full flex-col items-start gap-1 p-3 text-left",
                                                        !attackable && "opacity-50",
                                                    )}
                                                >
                                                    <span className="flex items-center gap-2">
                                                        <Badge variant="outline">
                                                            {KIND_LABEL[finding.kind] ??
                                                                finding.kind}
                                                        </Badge>
                                                        <Badge variant={active ? "default" : "secondary"}>
                                                            {finding.method}
                                                        </Badge>
                                                        {!attackable && (
                                                            <span className="text-muted-foreground text-[10px]">
                                                                tanpa request
                                                            </span>
                                                        )}
                                                    </span>
                                                    <span className="truncate text-xs font-semibold">
                                                        {finding.title}
                                                    </span>
                                                    <span className="truncate font-mono text-[11px] text-muted-foreground">
                                                        {finding.target}
                                                    </span>
                                                </Button>
                                            }
                                        />
                                        {!attackable && (
                                            <TooltipContent>
                                                Temuan ini tidak punya request konkret
                                            </TooltipContent>
                                        )}
                                    </Tooltip>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
