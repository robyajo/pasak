import { useState } from "react";
import { PlayIcon, ShieldCheckIcon } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import type { ActiveStageHeaderProps } from "./types";

export function ActiveStageHeader({
    stage,
    busy,
    onFireStage,
    isPassed,
    canAdvance,
    onAdvance,
    canWaive,
    waiverNote,
    onWaive,
}: ActiveStageHeaderProps) {
    const [waiverOpen, setWaiverOpen] = useState(false);
    const [noteDraft, setNoteDraft] = useState("");

    return (
        <Card>
            <CardHeader>
                <CardTitle>{stage.title}</CardTitle>
                <CardDescription>
                    <b>Tujuan:</b> {stage.goal}
                </CardDescription>
                <CardDescription>{stage.doneHint}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    onClick={onFireStage}
                    disabled={busy !== null}
                >
                    {busy !== null ? (
                        <Spinner data-icon="inline-start" />
                    ) : (
                        <PlayIcon data-icon="inline-start" />
                    )}
                    {busy !== null
                        ? "Menyerang..."
                        : `▶ Jalankan ${stage.title.split("—")[0].trim()}`}
                </Button>

                {isPassed && canAdvance && (
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        onClick={onAdvance}
                    >
                        <ShieldCheckIcon data-icon="inline-start" />
                        Lanjut ke tahap {stage.id + 1} →
                    </Button>
                )}

                {!isPassed &&
                    canAdvance &&
                    canWaive &&
                    waiverNote === undefined &&
                    (waiverOpen ? (
                        <span className="flex flex-wrap items-center gap-2">
                            <Input
                                value={noteDraft}
                                onChange={(e) => setNoteDraft(e.target.value)}
                                placeholder="Catatan: mis. /.env 403 dari Nginx, bukan WAF"
                                className="min-w-36 flex-1"
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                disabled={noteDraft.trim() === ""}
                                onClick={() => {
                                    onWaive(noteDraft.trim());
                                    setWaiverOpen(false);
                                    setNoteDraft("");
                                    onAdvance();
                                }}
                            >
                                Konfirmasi & lanjut →
                            </Button>
                        </span>
                    ) : (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setWaiverOpen(true)}
                        >
                            ⚠️ Lanjut dengan catatan
                        </Button>
                    ))}

                {waiverNote !== undefined && (
                    <Badge variant="outline">
                        ⚠️ Catatan: {waiverNote}
                    </Badge>
                )}
            </CardContent>
        </Card>
    );
}
