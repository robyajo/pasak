import { PlayIcon, TriangleAlertIcon } from "lucide-react";
import { Alert, AlertTitle } from "@/components/ui/alert";
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import { ATTACKER_PRESETS, QUICK_COMMANDS } from "./constants";
import { attackerLocationLabel, useAttackerLocation } from "@/hooks/useAttackerLocation";
import { useLabStore } from "@/stores/lab";
import type { AttackerCardProps } from "./types";

export function AttackerCard({
    uploadIdx,
    execCmd,
    onExecCmdChange,
    execResult,
    busy,
    onExploitShell,
    onExploitShellWith,
}: AttackerCardProps) {
    const isExecBusy = busy === `exec-${uploadIdx}`;
    const attackerIp = useLabStore((s) => s.attackerIp);
    const shellLocation = attackerLocationLabel(
        useAttackerLocation(attackerIp),
    );

    return (
        <Card size="sm" className="@2xl:col-span-2">
            <CardHeader>
                <CardTitle>
                    ☠️ Kartu Penyerang — Manfaatkan Hasil Susupan
                </CardTitle>
                <CardAction>
                    {execResult?.ok && (
                        <Badge variant="destructive">RCE AKTIF</Badge>
                    )}
                </CardAction>
                <CardDescription>
                    Webshell terverifikasi. Bertindak sebagai penyerang: kiri
                    perintah bebas, kanan paket pencurian data sekali klik.
                    {shellLocation !== "" ? ` — 🛰️ ${shellLocation}` : ""}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <div className="grid gap-3 @lg:grid-cols-2">
                    <div>
                        <Label className="mb-1 text-[11px] font-bold">
                            KIRI — perintah bebas
                        </Label>
                        <div className="flex flex-wrap gap-2">
                            <Input
                                type="text"
                                value={execCmd}
                                onChange={(e) =>
                                    onExecCmdChange(e.target.value)
                                }
                                placeholder="id"
                                className="min-w-0 flex-1 font-mono"
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                onClick={() => onExploitShell(uploadIdx)}
                                disabled={busy !== null}
                            >
                                {isExecBusy ? (
                                    <Spinner data-icon="inline-start" />
                                ) : (
                                    <PlayIcon data-icon="inline-start" />
                                )}
                                {isExecBusy ? "Menjalankan..." : "▶ Jalankan"}
                            </Button>
                        </div>
                        <div className="mt-1 flex flex-wrap gap-1 text-[11px]">
                            {QUICK_COMMANDS.map((c) => (
                                <Button
                                    key={c}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => onExecCmdChange(c)}
                                    className="font-mono"
                                >
                                    {c}
                                </Button>
                            ))}
                        </div>
                    </div>
                    <div>
                        <Label className="mb-1 text-[11px] font-bold">
                            KANAN — curi data hasil susupan
                        </Label>
                        <div className="flex flex-col gap-1">
                            {ATTACKER_PRESETS.map((preset) => (
                                <Button
                                    key={preset.label}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={busy !== null}
                                    onClick={() => {
                                        onExecCmdChange(preset.cmd);
                                        setTimeout(() => {
                                            void onExploitShellWith(
                                                uploadIdx,
                                                preset.cmd,
                                            );
                                        }, 0);
                                    }}
                                    className="h-auto justify-start whitespace-normal py-1.5 text-left"
                                >
                                    <preset.icon data-icon="inline-start" />
                                    {preset.label}
                                </Button>
                            ))}
                        </div>
                    </div>
                </div>

                {execResult ? (
                    <pre className="overflow-x-auto rounded bg-black p-2 font-mono text-green-300">
                        $ {execResult.cmd}
                        {"\n"}
                        {execResult.ok
                            ? execResult.output
                            : `(gagal/HTTP ${execResult.status}) ${execResult.output.slice(0, 200)}`}
                    </pre>
                ) : (
                    isExecBusy && <Skeleton className="h-16 w-full" />
                )}

                {execResult?.ok && (
                    <Alert variant="destructive">
                        <TriangleAlertIcon data-icon="inline-start" />
                        <AlertTitle>
                            RCE TERBUKTI ✅ — tanpa Pasak, penyerang kini
                            mengendalikan server. Vonis: JEBOL TOTAL.
                        </AlertTitle>
                    </Alert>
                )}
            </CardContent>
        </Card>
    );
}
