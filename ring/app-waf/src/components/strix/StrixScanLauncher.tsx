import { useState } from "react";
import {
    DollarSignIcon,
    FlameIcon,
    GaugeIcon,
    PlayIcon,
    ShieldAlertIcon,
    SparklesIcon,
    SquareIcon,
    TargetIcon,
    TerminalIcon,
    ZapIcon,
} from "lucide-react";
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
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import type { StrixJob, StrixScanMode } from "@/types/strix";

interface StrixScanLauncherProps {
    defaultTarget?: string;
    activeJob: StrixJob | null;
    submitting: boolean;
    stopping: boolean;
    onStart: (params: {
        target: string;
        mode: StrixScanMode;
        budget: number;
        instruction: string;
    }) => Promise<void>;
    onStop: () => Promise<void>;
}

const INSTRUCTION_PRESETS = [
    {
        title: "🛡️ Bypass WAF & Injeksi",
        text: "Audit ketahanan aplikasi terhadap bypass WAF, injeksi SQL pada parameter query/body, Cross-Site Scripting (XSS), dan Path Traversal.",
    },
    {
        title: "📂 Webshell & Eksekusi RCE",
        text: "Fokus audit pada endpoint upload file, deserialisasi tidak aman, dan kemungkinan pengunggahan webshell untuk remote code execution.",
    },
    {
        title: "⚡ Pemetaan Endpoint & Auth",
        text: "Temukan endpoint tersembunyi, uji proteksi brute-force login, manipulasi header IP, dan verifikasi proteksi rate limit WAF.",
    },
];

export function StrixScanLauncher({
    defaultTarget = "http://localhost:8000",
    activeJob,
    submitting,
    stopping,
    onStart,
    onStop,
}: StrixScanLauncherProps) {
    const [target, setTarget] = useState(defaultTarget);
    const [mode, setMode] = useState<StrixScanMode>("quick");
    const [budget, setBudget] = useState(1);
    const [instruction, setInstruction] = useState(
        "Lakukan audit keamanan otomatis terhadap endpoint web. Uji ketahanan terhadap bypass WAF, celah injeksi, dan upload berbahaya.",
    );

    const isRunning = Boolean(
        activeJob && ["starting", "running", "stopping"].includes(activeJob.status)
    );

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (isRunning) return;
        const normalizedBudget = Math.max(1, Number(budget) || 1);
        await onStart({
            target: target.trim(),
            mode,
            budget: normalizedBudget,
            instruction: instruction.trim(),
        });
    };

    return (
        <Card className="h-full flex flex-col justify-between">
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <TargetIcon className="size-4 text-primary" />
                        Peluncur Audit Otonom
                    </CardTitle>
                    {isRunning && (
                        <Badge variant="destructive" className="animate-pulse text-[11px]">
                            ● SCAN BERJALAN
                        </Badge>
                    )}
                </div>
                <CardDescription className="text-xs">
                    Konfigurasi parameter audit Strix untuk memindai target secara otonom.
                </CardDescription>
            </CardHeader>

            <CardContent className="flex flex-col gap-4 text-xs">
                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    {/* Target URL */}
                    <div className="flex flex-col gap-1.5">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="strix-target" className="text-xs font-semibold">
                                URL Target
                            </Label>
                            <div className="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    onClick={() => setTarget("http://localhost:8000")}
                                    className="text-[10px] text-muted-foreground hover:text-primary transition-colors underline"
                                >
                                    :8000 (Pasak-No)
                                </button>
                                <span className="text-muted-foreground">•</span>
                                <button
                                    type="button"
                                    onClick={() => setTarget("http://localhost:8001")}
                                    className="text-[10px] text-muted-foreground hover:text-primary transition-colors underline"
                                >
                                    :8001 (Pasak-Yes)
                                </button>
                            </div>
                        </div>
                        <Input
                            id="strix-target"
                            type="text"
                            value={target}
                            onChange={(e) => setTarget(e.target.value)}
                            placeholder="http://localhost:8000 atau https://..."
                            className="font-mono text-xs"
                            disabled={isRunning}
                            required
                        />
                    </div>

                    {/* Mode Scan */}
                    <div className="flex flex-col gap-1.5">
                        <Label className="text-xs font-semibold">
                            Tingkat Intensitas (Scan Mode)
                        </Label>
                        <div className="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                disabled={isRunning}
                                onClick={() => setMode("quick")}
                                className={`flex flex-col items-center justify-center p-2.5 rounded-lg border text-center transition-all ${
                                    mode === "quick"
                                        ? "border-primary bg-primary/10 text-primary font-bold shadow-xs"
                                        : "border-border hover:bg-muted text-muted-foreground"
                                }`}
                            >
                                <ZapIcon className="size-4 mb-1" />
                                <span className="text-xs">Quick</span>
                                <span className="text-[10px] font-normal opacity-80">
                                    Cepat & Ringan
                                </span>
                            </button>

                            <button
                                type="button"
                                disabled={isRunning}
                                onClick={() => setMode("standard")}
                                className={`flex flex-col items-center justify-center p-2.5 rounded-lg border text-center transition-all ${
                                    mode === "standard"
                                        ? "border-primary bg-primary/10 text-primary font-bold shadow-xs"
                                        : "border-border hover:bg-muted text-muted-foreground"
                                }`}
                            >
                                <GaugeIcon className="size-4 mb-1" />
                                <span className="text-xs">Standard</span>
                                <span className="text-[10px] font-normal opacity-80">
                                    Audit Lengkap
                                </span>
                            </button>

                            <button
                                type="button"
                                disabled={isRunning}
                                onClick={() => setMode("deep")}
                                className={`flex flex-col items-center justify-center p-2.5 rounded-lg border text-center transition-all ${
                                    mode === "deep"
                                        ? "border-primary bg-primary/10 text-primary font-bold shadow-xs"
                                        : "border-border hover:bg-muted text-muted-foreground"
                                }`}
                            >
                                <FlameIcon className="size-4 mb-1" />
                                <span className="text-xs">Deep</span>
                                <span className="text-[10px] font-normal opacity-80">
                                    Fuzzing Dalam
                                </span>
                            </button>
                        </div>
                    </div>

                    {/* Max Budget */}
                    <div className="flex flex-col gap-1.5">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="strix-budget" className="text-xs font-semibold flex items-center gap-1">
                                <DollarSignIcon className="size-3.5" /> Batas Budget Maksimum (USD)
                            </Label>
                            <span className="font-mono text-xs font-bold text-primary">
                                ${Number(budget).toFixed(2)} USD
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Input
                                id="strix-budget"
                                type="number"
                                min="1"
                                max="1000"
                                step="1"
                                value={budget}
                                onChange={(e) => {
                                    const val = parseFloat(e.target.value);
                                    setBudget(isNaN(val) ? 1 : val);
                                }}
                                className="font-mono text-xs w-24"
                                disabled={isRunning}
                            />
                            <div className="flex items-center gap-1">
                                {[1, 2, 5, 10, 25].map((b) => (
                                    <button
                                        key={b}
                                        type="button"
                                        disabled={isRunning}
                                        onClick={() => setBudget(b)}
                                        className={`px-2 py-1 rounded text-[11px] border ${
                                            budget === b
                                                ? "bg-secondary text-secondary-foreground font-semibold border-primary/40"
                                                : "border-border hover:bg-muted text-muted-foreground"
                                        }`}
                                    >
                                        ${b}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <p className="text-[10px] text-muted-foreground">
                            Batas aman pengeluaran token API LLM per sesi. Scan otomatis berhenti bila estimasi biaya mencapai angka ini.
                        </p>
                    </div>

                    {/* Instruksi Pentest Khusus */}
                    <div className="flex flex-col gap-1.5">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="strix-instruction" className="text-xs font-semibold flex items-center gap-1">
                                <SparklesIcon className="size-3.5 text-primary" />
                                Instruksi / Panduan Pentest Khusus
                            </Label>
                        </div>
                        <Textarea
                            id="strix-instruction"
                            value={instruction}
                            onChange={(e) => setInstruction(e.target.value)}
                            rows={3}
                            placeholder="Tuliskan arahan spesifik untuk Agent Strix..."
                            className="text-xs font-sans"
                            disabled={isRunning}
                        />
                        <div className="flex flex-wrap gap-1.5 mt-1">
                            {INSTRUCTION_PRESETS.map((p, idx) => (
                                <button
                                    key={idx}
                                    type="button"
                                    disabled={isRunning}
                                    onClick={() => setInstruction(p.text)}
                                    className="text-[11px] px-2 py-0.5 rounded-md border border-border bg-background hover:bg-muted text-muted-foreground text-left"
                                >
                                    {p.title}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Tombol Aksi */}
                    <div className="pt-2">
                        {isRunning ? (
                            <Button
                                type="button"
                                variant="destructive"
                                className="w-full h-9 font-semibold"
                                onClick={onStop}
                                disabled={stopping}
                            >
                                {stopping ? (
                                    <>
                                        <Spinner className="size-4 mr-2" />
                                        Menghentikan Scan…
                                    </>
                                ) : (
                                    <>
                                        <SquareIcon className="size-4 mr-2" />
                                        Hentikan Scan Sekarang (SIGINT)
                                    </>
                                )}
                            </Button>
                        ) : (
                            <Button
                                type="submit"
                                variant="default"
                                className="w-full h-9 font-semibold"
                                disabled={submitting}
                            >
                                {submitting ? (
                                    <>
                                        <Spinner className="size-4 mr-2" />
                                        Memulai Sesi Strix…
                                    </>
                                ) : (
                                    <>
                                        <PlayIcon className="size-4 mr-2" />
                                        Mulai Audit Otonom Strix
                                    </>
                                )}
                            </Button>
                        )}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
