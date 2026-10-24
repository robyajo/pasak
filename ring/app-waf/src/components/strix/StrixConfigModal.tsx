import { useEffect, useState } from "react";
import {
    BotIcon,
    CheckCircle2Icon,
    CpuIcon,
    EyeIcon,
    EyeOffIcon,
    GlobeIcon,
    KeyIcon,
    SaveIcon,
    ServerIcon,
    SparklesIcon,
    XCircleIcon,
} from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import type { LlmProvider, StrixConfigInfo } from "@/types/strix";
import type { SaveConfigInput } from "@/hooks/useStrix";

interface StrixConfigModalProps {
    open: boolean;
    config: StrixConfigInfo | null;
    saving: boolean;
    onClose: () => void;
    onSave: (params: SaveConfigInput) => Promise<unknown>;
}

interface ModelPreset {
    id: string;
    label: string;
    desc: string;
    badge?: string;
}

const GEMINI_MODELS: ModelPreset[] = [
    {
        id: "gemini/gemini-2.5-pro",
        label: "Gemini 2.5 Pro",
        desc: "Model penalaran canggih Google untuk audit kompleks",
        badge: "Rekomendasi Utama",
    },
    {
        id: "gemini/gemini-2.5-flash",
        label: "Gemini 2.5 Flash",
        desc: "Sangat kencang, responsif, dan hemat biaya token",
        badge: "Cepat & Hemat",
    },
    {
        id: "gemini/gemini-1.5-pro",
        label: "Gemini 1.5 Pro",
        desc: "Konteks hingga 2M token, analisis kode mendalam",
    },
    {
        id: "gemini/gemini-1.5-flash",
        label: "Gemini 1.5 Flash",
        desc: "Ringan & optimal untuk scan cepat (quick mode)",
    },
];

const OPENAI_MODELS: ModelPreset[] = [
    {
        id: "openai/gpt-5",
        label: "ChatGPT GPT-5",
        desc: "Mode GPT-5 / alias model generasi lanjutan OpenAI",
        badge: "Pilihan Aktif",
    },
    {
        id: "openai/gpt-4o",
        label: "ChatGPT GPT-4o",
        desc: "Model andalan OpenAI untuk penalaran & analisis kode",
        badge: "Standar Industri",
    },
    {
        id: "openai/gpt-4o-mini",
        label: "GPT-4o Mini",
        desc: "Cerdas, sangat cepat, dan biaya operasional rendah",
        badge: "Paling Hemat",
    },
    {
        id: "openai/o3-mini",
        label: "OpenAI o3-mini",
        desc: "Model reasoning generasi terbaru untuk eksploitasi logika",
        badge: "Deep Reasoning",
    },
    {
        id: "openai/o1",
        label: "OpenAI o1",
        desc: "Penalaran matematika, kriptografi, & arsitektur kompleks",
    },
];

const OPENROUTER_MODELS: ModelPreset[] = [
    {
        id: "openrouter/z-ai/glm-5.3",
        label: "GLM-5.3 (OpenRouter)",
        desc: "Model default bawaan saat ini di .env",
        badge: "Bawaan Proyek",
    },
    {
        id: "openrouter/anthropic/claude-3.5-sonnet",
        label: "Claude 3.5 Sonnet",
        desc: "Performa pentest & eksploitasi web paling akurat",
        badge: "Sangat Disukai Pentester",
    },
    {
        id: "openrouter/deepseek/deepseek-chat",
        label: "DeepSeek V3",
        desc: "Performa tinggi dengan biaya API terjangkau",
    },
    {
        id: "openrouter/meta-llama/llama-3.3-70b-instruct",
        label: "Llama 3.3 70B",
        desc: "Model open source terbaik dari Meta via OpenRouter",
    },
];

const OPENCODE_MODELS: ModelPreset[] = [
    {
        id: "openai/qwen2.5-coder",
        label: "Qwen 2.5 Coder",
        desc: "Khusus pemahaman kode sumber & debugging keamanan",
        badge: "OpenCode",
    },
    {
        id: "openai/deepseek-coder",
        label: "DeepSeek Coder",
        desc: "Handal untuk inspeksi payload & alur webshell",
    },
    {
        id: "openai/llama3",
        label: "Llama 3 Local",
        desc: "Model lokal berjalan di server lokal Anda (Ollama/vLLM)",
    },
];

export function StrixConfigModal({
    open,
    config,
    saving,
    onClose,
    onSave,
}: StrixConfigModalProps) {
    const currentProvider = config?.provider || "openrouter";
    const [activeTab, setActiveTab] = useState<LlmProvider>(currentProvider);
    const [model, setModel] = useState(
        config?.llm || "openrouter/z-ai/glm-5.3",
    );
    const [apiKey, setApiKey] = useState("");
    const [apiBase, setApiBase] = useState("");
    const [showKey, setShowKey] = useState(false);
    const [feedback, setFeedback] = useState<string | null>(null);

    // Sync tab when modal opens or config updates
    useEffect(() => {
        if (config?.llm) {
            setModel(config.llm);
            if (config.provider) {
                setActiveTab(config.provider);
            }
        }
    }, [config]);

    const handleTabChange = (val: string) => {
        const prov = val as LlmProvider;
        setActiveTab(prov);
        setApiKey("");
        // Set default model for the selected tab if current model doesn't match
        if (prov === "gemini" && !model.startsWith("gemini/")) {
            setModel("gemini/gemini-2.5-pro");
        } else if (
            prov === "openai" &&
            !model.startsWith("openai/") &&
            !model.startsWith("gpt-")
        ) {
            setModel("openai/gpt-4o");
        } else if (prov === "openrouter" && !model.startsWith("openrouter/")) {
            setModel("openrouter/z-ai/glm-5.3");
        } else if (prov === "opencode" && !model.includes("coder")) {
            setModel("openai/qwen2.5-coder");
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setFeedback(null);
        try {
            const payload: SaveConfigInput = {
                llm: model.trim(),
                provider: activeTab,
            };

            if (apiKey.trim() !== "") {
                payload.apiKey = apiKey.trim();
                if (activeTab === "gemini")
                    payload.geminiApiKey = apiKey.trim();
                if (activeTab === "openai")
                    payload.openaiApiKey = apiKey.trim();
                if (activeTab === "openrouter")
                    payload.openrouterApiKey = apiKey.trim();
            }

            if (activeTab === "opencode" && apiBase.trim() !== "") {
                payload.apiBase = apiBase.trim();
            }

            await onSave(payload);
            setFeedback("Konfigurasi LLM berhasil disimpan ke .env!");
            setTimeout(() => {
                onClose();
                setFeedback(null);
                setApiKey("");
            }, 800);
        } catch (err) {
            setFeedback(
                err instanceof Error ? err.message : "Gagal menyimpan.",
            );
        }
    };

    const getProviderStatusBadge = (
        prov: "gemini" | "openai" | "openrouter",
    ) => {
        const p = config?.providers?.[prov];
        if (p?.configured) {
            return (
                <span className="text-[10px] text-emerald-500 font-medium inline-flex items-center gap-1">
                    <CheckCircle2Icon className="size-3" /> Siap
                </span>
            );
        }
        if (p?.is_placeholder) {
            return (
                <span className="text-[10px] text-amber-500 font-medium">
                    Placeholder
                </span>
            );
        }
        return (
            <span className="text-[10px] text-muted-foreground">
                Belum Diset
            </span>
        );
    };

    return (
        <Dialog open={open} onOpenChange={(val) => !val && onClose()}>
            <DialogContent className="max-w-2xl max-h-[92vh] flex flex-col p-6">
                <DialogHeader className="border-b pb-3 shrink-0">
                    <DialogTitle className="flex items-center gap-2 text-base">
                        <SparklesIcon className="size-5 text-primary" />
                        Pilih Provider & Model LLM Strix
                    </DialogTitle>
                    <DialogDescription className="text-xs">
                        Strix AI Pentest mendukung <b>Google Gemini</b>,{" "}
                        <b>OpenAI (ChatGPT)</b>, <b>OpenRouter</b>, dan{" "}
                        <b>OpenCode / Local AI</b>. Kunci tersimpan di{" "}
                        <code>ring/app-waf/.env</code>.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex-1 overflow-y-auto py-2 flex flex-col gap-4"
                >
                    {feedback && (
                        <Alert
                            variant={
                                feedback.includes("berhasil")
                                    ? "default"
                                    : "destructive"
                            }
                        >
                            <AlertDescription className="text-xs font-medium">
                                {feedback}
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Navigasi Tab Provider */}
                    <Tabs
                        value={activeTab}
                        onValueChange={handleTabChange}
                        className="w-full"
                    >
                        <TabsList className="grid grid-cols-4 w-full h-auto p-1 bg-muted/60">
                            <TabsTrigger
                                value="gemini"
                                className="flex flex-col items-center gap-1 py-1.5 text-xs data-active:bg-background data-active:shadow-xs"
                            >
                                <span className="font-bold flex items-center gap-1">
                                    ♊ Gemini
                                </span>
                                {getProviderStatusBadge("gemini")}
                            </TabsTrigger>

                            <TabsTrigger
                                value="openai"
                                className="flex flex-col items-center gap-1 py-1.5 text-xs data-active:bg-background data-active:shadow-xs"
                            >
                                <span className="font-bold flex items-center gap-1">
                                    🤖 ChatGPT
                                </span>
                                {getProviderStatusBadge("openai")}
                            </TabsTrigger>

                            <TabsTrigger
                                value="openrouter"
                                className="flex flex-col items-center gap-1 py-1.5 text-xs data-active:bg-background data-active:shadow-xs"
                            >
                                <span className="font-bold flex items-center gap-1">
                                    🌐 OpenRouter
                                </span>
                                {getProviderStatusBadge("openrouter")}
                            </TabsTrigger>

                            <TabsTrigger
                                value="opencode"
                                className="flex flex-col items-center gap-1 py-1.5 text-xs data-active:bg-background data-active:shadow-xs"
                            >
                                <span className="font-bold flex items-center gap-1">
                                    💻 OpenCode / Lokal
                                </span>
                                <span className="text-[10px] text-muted-foreground">
                                    Ollama / vLLM
                                </span>
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>

                    {/* Pilihan Model Rekomendasi berdasarkan Tab Aktif */}
                    <div className="flex flex-col gap-2">
                        <Label className="text-xs font-semibold flex items-center justify-between">
                            <span>Pilih Model Teruji:</span>
                            <span className="text-[11px] text-muted-foreground font-normal">
                                Format: LiteLLM prefix
                            </span>
                        </Label>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            {(activeTab === "gemini"
                                ? GEMINI_MODELS
                                : activeTab === "openai"
                                  ? OPENAI_MODELS
                                  : activeTab === "openrouter"
                                    ? OPENROUTER_MODELS
                                    : OPENCODE_MODELS
                            ).map((m) => (
                                <div
                                    key={m.id}
                                    onClick={() => setModel(m.id)}
                                    className={`p-2.5 rounded-lg border text-left cursor-pointer transition-all ${
                                        model === m.id
                                            ? "border-primary bg-primary/10 shadow-xs"
                                            : "border-border hover:bg-muted/50"
                                    }`}
                                >
                                    <div className="flex items-center justify-between gap-1 mb-1">
                                        <span className="font-bold text-xs">
                                            {m.label}
                                        </span>
                                        {m.badge && (
                                            <Badge
                                                variant="secondary"
                                                className="text-[9px] px-1.5 py-0"
                                            >
                                                {m.badge}
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-[11px] text-muted-foreground leading-snug">
                                        {m.desc}
                                    </p>
                                    <div className="mt-1 font-mono text-[10px] text-primary">
                                        {m.id}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Input Model String Bebas */}
                    <div className="flex flex-col gap-1.5">
                        <Label
                            htmlFor="custom-model-input"
                            className="text-xs font-semibold"
                        >
                            Nama Model (STRIX_LLM)
                        </Label>
                        <Input
                            id="custom-model-input"
                            type="text"
                            value={model}
                            onChange={(e) => setModel(e.target.value)}
                            placeholder="gemini/gemini-2.5-pro atau openai/gpt-4o"
                            className="font-mono text-xs"
                            required
                        />
                    </div>

                    {/* Input API Key Spesifik untuk Provider yang Aktif */}
                    <div className="flex flex-col gap-1.5 bg-card/60 p-3 rounded-lg border">
                        <div className="flex items-center justify-between">
                            <Label
                                htmlFor="provider-api-key"
                                className="text-xs font-semibold flex items-center gap-1.5"
                            >
                                <KeyIcon className="size-3.5 text-primary" />
                                {activeTab === "gemini" &&
                                    "Google Gemini API Key (GEMINI_API_KEY)"}
                                {activeTab === "openai" &&
                                    "OpenAI / ChatGPT API Key (OPENAI_API_KEY)"}
                                {activeTab === "openrouter" &&
                                    "OpenRouter API Key (OPENROUTER_API_KEY)"}
                                {activeTab === "opencode" &&
                                    "API Key OpenCode / Local AI (opsional)"}
                            </Label>

                            {activeTab !== "opencode" && (
                                <a
                                    href={
                                        activeTab === "gemini"
                                            ? "https://aistudio.google.com/app/apikey"
                                            : activeTab === "openai"
                                              ? "https://platform.openai.com/api-keys"
                                              : "https://openrouter.ai/keys"
                                    }
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-[10px] text-primary hover:underline"
                                >
                                    Dapatkan Kunci API ↗
                                </a>
                            )}
                        </div>

                        <div className="relative mt-1">
                            <Input
                                id="provider-api-key"
                                type={showKey ? "text" : "password"}
                                value={apiKey}
                                onChange={(e) => setApiKey(e.target.value)}
                                placeholder={
                                    activeTab === "gemini"
                                        ? "AIzaSy..."
                                        : activeTab === "openai"
                                          ? "sk-proj-..."
                                          : activeTab === "openrouter"
                                            ? "sk-or-v1-..."
                                            : "Biarkan kosong jika tidak memakai otentikasi"
                                }
                                className="font-mono text-xs pr-10"
                            />
                            <button
                                type="button"
                                onClick={() => setShowKey(!showKey)}
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                tabIndex={-1}
                            >
                                {showKey ? (
                                    <EyeOffIcon className="size-4" />
                                ) : (
                                    <EyeIcon className="size-4" />
                                )}
                            </button>
                        </div>
                        <p className="text-[11px] text-muted-foreground">
                            Kunci disimpan ke berkas <code>.env</code> lokal dan
                            diteruskan ke proses Strix/LiteLLM. Kosongkan jika
                            sudah ada di <code>.env</code> dan tidak ingin
                            diubah.
                        </p>
                    </div>

                    {/* Jika Tab OpenCode / Lokal: Opsi Base URL */}
                    {activeTab === "opencode" && (
                        <div className="flex flex-col gap-1.5 bg-card/60 p-3 rounded-lg border">
                            <Label
                                htmlFor="opencode-base-url"
                                className="text-xs font-semibold flex items-center gap-1.5"
                            >
                                <ServerIcon className="size-3.5 text-primary" />
                                Endpoint Base URL (OPENAI_API_BASE)
                            </Label>
                            <Input
                                id="opencode-base-url"
                                type="text"
                                value={apiBase}
                                onChange={(e) => setApiBase(e.target.value)}
                                placeholder="http://localhost:11434/v1 (Ollama) atau http://127.0.0.1:8000/v1"
                                className="font-mono text-xs"
                            />
                            <p className="text-[11px] text-muted-foreground">
                                Mengarahkan Strix ke server LLM lokal (Ollama,
                                vLLM, LocalAI) tanpa koneksi internet luar.
                            </p>
                        </div>
                    )}

                    <DialogFooter className="mt-2 shrink-0">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={saving}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={saving}>
                            {saving ? (
                                <>
                                    <Spinner className="size-4 mr-1.5" />{" "}
                                    Menyimpan…
                                </>
                            ) : (
                                <>
                                    <SaveIcon className="size-4 mr-1.5" />{" "}
                                    Simpan Konfigurasi
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
