import { useState } from "react";
import {
    CheckIcon,
    CopyIcon,
    DicesIcon,
    DownloadIcon,
    LockIcon,
    TriangleAlertIcon,
} from "lucide-react";
import { cn } from "cn";
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from "@/components/ui/accordion";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Progress,
    ProgressLabel,
    ProgressValue,
} from "@/components/ui/progress";
import { Separator } from "@/components/ui/separator";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { LAB_BROWSERS, useLabStore } from "@/stores/lab";
import { DEMO_LOCAL_IP } from "./constants";
import type { ConfigStepperCardProps } from "./types";

export function ConfigStepperCard({
    url,
    mode,
    onTargetChange,
    activeStage,
    onSelectStage,
    stages,
    isStagePassed,
    isStageUnlocked,
    waived,
    doneCount,
    totalCount,
    passedCount,
    pct,
    allPassed,
    fileCheck,
    busy,
    copiedIdx,
    onCopyReportJson,
    onDownloadReport,
    reportJson,
}: ConfigStepperCardProps) {
    const attackerIp = useLabStore((s) => s.attackerIp);
    const setAttackerIp = useLabStore((s) => s.setAttackerIp);
    const newAttackerIp = useLabStore((s) => s.newAttackerIp);
    const deviceId = useLabStore((s) => s.deviceId);
    const rotateDevice = useLabStore((s) => s.rotateDevice);
    const browser = useLabStore((s) => s.browser);
    const setBrowser = useLabStore((s) => s.setBrowser);
    const resetLab = useLabStore((s) => s.resetLab);
    const storeTarget = useLabStore((s) => s.setTarget);

    const [draftTarget, setDraftTarget] = useState<string | null>(null);
    const [draftIp, setDraftIp] = useState<string | null>(null);
    const [draftBrowser, setDraftBrowser] = useState<string | null>(null);
    const [savedTick, setSavedTick] = useState(false);

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    ⚠️ Tanpa Pasak = Jebol — audit 4 tahap
                </CardTitle>
                <CardDescription>
                    Ikuti tahap 1→4 berurutan. Tiap tahap terkunci
                    sampai tahap sebelumnya <b>semua LOLOS</b>{" "}
                    (tidak 403). Di tahap 4 Anda menanam webshell
                    sungguhan lalu{" "}
                    <b>memverifikasi file-nya bisa dibuka publik</b>{" "}
                    — bukti tak terbantahkan. Target{" "}
                    <b>{url || "(isi URL dulu)"}</b>
                    {mode === "production"
                        ? " — gunakan URL https server audit."
                        : " — mis. pasak-no di http://localhost:8000."}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <div className="grid gap-3 @lg:grid-cols-2">
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="nopasak-target">
                            URL target
                        </Label>
                        <Input
                            id="nopasak-target"
                            type="url"
                            value={draftTarget ?? url}
                            placeholder="http://localhost:8000"
                            onChange={(e) =>
                                setDraftTarget(e.target.value)
                            }
                            className="font-mono"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="nopasak-ip">
                            🎭 IP penyerang (simulasi)
                        </Label>
                        <div className="flex gap-2">
                            <Input
                                id="nopasak-ip"
                                type="text"
                                value={draftIp ?? attackerIp}
                                placeholder="otomatis acak"
                                onChange={(e) =>
                                    setDraftIp(e.target.value)
                                }
                                className="min-w-0 flex-1 font-mono"
                            />
                            <Tooltip>
                                <TooltipTrigger
                                    render={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={() => {
                                                newAttackerIp();
                                                setDraftIp(null);
                                            }}
                                        >
                                            <DicesIcon data-icon="inline-start" />
                                        </Button>
                                    }
                                />
                                <TooltipContent>
                                    Buat IP acak baru
                                </TooltipContent>
                            </Tooltip>
                        </div>
                        <span className="text-xs font-normal text-muted-foreground">
                            Draf — klik Set untuk dipakai serangan +
                            preview korban.
                        </span>
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="nopasak-browser">
                            🌐 Browser penyerang (jadi device)
                        </Label>
                        <Select
                            value={draftBrowser ?? browser}
                            onValueChange={(v) =>
                                setDraftBrowser(v)
                            }
                        >
                            <SelectTrigger
                                id="nopasak-browser"
                                className="w-full"
                            >
                                <SelectValue placeholder="Pilih browser" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {LAB_BROWSERS.map((b) => (
                                        <SelectItem
                                            key={b.code}
                                            value={b.code}
                                        >
                                            {b.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <span className="text-xs font-normal text-muted-foreground">
                            UA-nya dikirim tiap request; ID
                            perangkat ikut kode browser ini.
                        </span>
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="nopasak-device">
                            🖥️ ID perangkat lab (simulasi)
                        </Label>
                        <div className="flex gap-2">
                            <Input
                                id="nopasak-device"
                                type="text"
                                value={deviceId}
                                readOnly
                                className="min-w-0 flex-1 font-mono"
                            />
                            <Tooltip>
                                <TooltipTrigger
                                    render={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={rotateDevice}
                                        >
                                            <DicesIcon data-icon="inline-start" />
                                        </Button>
                                    }
                                />
                                <TooltipContent>
                                    Putar ID baru (identitas baru =
                                    lolos dari blokir device)
                                </TooltipContent>
                            </Tooltip>
                        </div>
                        <span className="text-xs font-normal text-muted-foreground">
                            Dikirim sebagai X-Device-Id di semua
                            request + preview (IP lokal{" "}
                            {DEMO_LOCAL_IP}). Blokir device-scope
                            mengikat ID ini.
                        </span>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="default"
                        onClick={() => {
                            const t = (draftTarget ?? url).trim();
                            const ip = (
                                draftIp ?? attackerIp
                            ).trim();
                            const br = draftBrowser ?? browser;
                            if (t !== "") {
                                storeTarget(t);
                                onTargetChange?.(t);
                            }
                            if (ip !== "") {
                                setAttackerIp(ip);
                            }
                            if (br !== browser) {
                                setBrowser(br);
                            }
                            setDraftTarget(null);
                            setDraftIp(null);
                            setDraftBrowser(null);
                            setSavedTick(true);
                            window.setTimeout(
                                () => setSavedTick(false),
                                1500,
                            );
                        }}
                    >
                        💾 Set & simpan ke Zustand
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            resetLab();
                            setDraftTarget(null);
                            setDraftIp(null);
                            setDraftBrowser(null);
                        }}
                    >
                        ↺ Reset Zustand
                    </Button>
                    {((draftTarget !== null &&
                        draftTarget !== url) ||
                        (draftIp !== null &&
                            draftIp !== attackerIp) ||
                        (draftBrowser !== null &&
                            draftBrowser !== browser)) && (
                        <span className="text-xs text-amber-600">
                            ● ada perubahan belum di-Set
                        </span>
                    )}
                    {savedTick && (
                        <span className="text-xs text-green-600">
                            Tersimpan ✓
                        </span>
                    )}
                </div>

                <Separator />

                {/* Stepper tahapan */}
                <ol className="flex flex-wrap gap-2">
                    {stages.map((stage) => {
                        const passed = isStagePassed(stage.id);
                        const open = isStageUnlocked(stage.id);
                        const noted =
                            waived[stage.id] !== undefined;
                        return (
                            <li key={stage.id}>
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={!open}
                                    variant={
                                        activeStage === stage.id
                                            ? "default"
                                            : passed
                                              ? "secondary"
                                              : "outline"
                                    }
                                    onClick={() =>
                                        onSelectStage(stage.id)
                                    }
                                    className={cn(
                                        "rounded-full font-bold",
                                        !open && "opacity-50",
                                    )}
                                    title={
                                        noted
                                            ? `Catatan: ${waived[stage.id]}`
                                            : undefined
                                    }
                                >
                                    {passed ? (
                                        <CheckIcon data-icon="inline-start" />
                                    ) : noted ? (
                                        "⚠️"
                                    ) : open ? null : (
                                        <LockIcon data-icon="inline-start" />
                                    )}
                                    {!passed && !noted && open
                                        ? `${stage.id}. `
                                        : ""}
                                    {stage.title.replace(
                                        `Tahap ${stage.id} — `,
                                        "",
                                    )}
                                </Button>
                            </li>
                        );
                    })}
                </ol>

                {doneCount > 0 && (
                    <Card size="sm">
                        <CardContent className="flex flex-col gap-2">
                            <p className="text-sm font-bold">
                                📊 {passedCount}/{totalCount} lolos (
                                {pct}%)
                                {allPassed &&
                                    fileCheck?.ok &&
                                    " — TERBUKTI 100% JEBOL + WEBSHELL AKTIF ✅"}
                                {allPassed &&
                                    !fileCheck?.ok &&
                                    " — semua lolos, verifikasi file tahap 4 untuk vonis final."}
                            </p>
                            <Progress value={pct}>
                                <ProgressLabel className="sr-only">
                                    Progres lolos
                                </ProgressLabel>
                                <ProgressValue>
                                    {() => `${pct}%`}
                                </ProgressValue>
                            </Progress>
                            {busy !== null && (
                                <Skeleton className="h-2 w-full" />
                            )}
                            {allPassed && fileCheck?.ok && (
                                    <Alert variant="destructive">
                                        <TriangleAlertIcon data-icon="inline-start" />
                                        <AlertTitle>
                                            Saran (terbukti bekerja):
                                        </AlertTitle>
                                        <AlertDescription>
                                            <ol className="ml-4 list-decimal">
                                                <li>
                                                    Simpan bukti:
                                                    screenshot vonis
                                                    ini + ekspor
                                                    History sebagai
                                                    laporan
                                                    baseline.
                                                </li>
                                                <li>
                                                    Cek{" "}
                                                    <code>
                                                        {url}/
                                                    </code>{" "}
                                                    —
                                                    serangan
                                                    tercatat
                                                    di
                                                    Monitor
                                                    = rantai
                                                    deteksi
                                                    berjalan.
                                                </li>
                                                <li>
                                                    Ulangi 4
                                                    tahap ke
                                                    target
                                                    ber-Pasak —
                                                    target:
                                                    terhenti
                                                    di tahap
                                                    2 (semua
                                                    403).
                                                </li>
                                                {Object.entries(
                                                    waived,
                                                ).map(
                                                    ([
                                                        stageId,
                                                        note,
                                                    ]) => (
                                                        <li
                                                            key={
                                                                stageId
                                                            }
                                                        >
                                                            Pengecualian
                                                            tahap{" "}
                                                            {
                                                                stageId
                                                            }
                                                            : {note}
                                                        </li>
                                                    ),
                                                )}
                                            </ol>
                                        </AlertDescription>
                                    </Alert>
                                )}
                            <Accordion className="mt-1">
                                <AccordionItem value="report">
                                    <AccordionTrigger className="text-xs">
                                        📋 Laporan internal (preview
                                        + salin JSON)
                                    </AccordionTrigger>
                                    <AccordionContent>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={onCopyReportJson}
                                            >
                                                <CopyIcon data-icon="inline-start" />
                                                {copiedIdx === -2
                                                    ? "Disalin ✓"
                                                    : "Salin JSON laporan"}
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={onDownloadReport}
                                            >
                                                <DownloadIcon data-icon="inline-start" />
                                                Unduh .json
                                            </Button>
                                        </div>
                                        <pre className="mt-2 max-h-96 overflow-auto rounded bg-black p-2 font-mono text-[11px] whitespace-pre-wrap text-green-300">
                                            {reportJson}
                                        </pre>
                                    </AccordionContent>
                                </AccordionItem>
                            </Accordion>
                        </CardContent>
                    </Card>
                )}
            </CardContent>
        </Card>
    );
}
