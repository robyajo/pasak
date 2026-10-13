import { useEffect, useMemo, useState } from "react";
import { useWafConsole, type ProxyResult } from "@/hooks/useWafConsole";
import { analyzeSurface } from "@/lib/surface";
import AppHeader, { type ConsoleMode } from "@/components/AppHeader";
import AppFooter from "@/components/AppFooter";
import AppTabs from "@/components/AppTabs";
import RequestForm from "@/components/RequestForm";
import ResultPanel from "@/components/ResultPanel";
import HistoryTable from "@/components/HistoryTable";
import ConsoleReport from "@/components/ConsoleReport";
import PreviewModal from "@/components/PreviewModal";
import AttackTab from "@/components/attack/AttackTab";
import NoPasakTab from "@/components/attack/NoPasakTab";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { TriangleAlertIcon } from "lucide-react";
import { Separator } from "@/components/ui/separator";

/**
 * Origin target dipakai untuk menurunkan path relatif saat menganalisis body.
 */
interface WafResultLike {
    base_url?: string;
    url?: string;
}
function baseUrlOf(result: ProxyResult | null | undefined): string {
    if (!result) {
        return "";
    }

    if (result.base_url) {
        return result.base_url;
    }

    if (typeof result.url !== "string" || result.url === "") {
        return "";
    }

    try {
        return new URL(result.url).origin;
    } catch {
        return "";
    }
}

export default function App() {
    const waf = useWafConsole();
    const [view, setView] = useState("nopasak");
    const [mode, setMode] = useState<ConsoleMode>(() => {
        try {
            return window.localStorage.getItem("waf-console-mode") ===
                "production"
                ? "production"
                : "local";
        } catch {
            return "local";
        }
    });
    const [confirmProduction, setConfirmProduction] = useState(false);
    const [theme, setTheme] = useState<"dark" | "light">(() => {
        try {
            return window.localStorage.getItem("waf-console-theme") === "dark"
                ? "dark"
                : "light";
        } catch {
            return "dark";
        }
    });

    useEffect(() => {
        try {
            window.localStorage.setItem("waf-console-theme", theme);
        } catch {
            // Abaikan bila storage tidak tersedia.
        }
    }, [theme]);

    useEffect(() => {
        try {
            window.localStorage.setItem("waf-console-mode", mode);
        } catch {
            // Abaikan bila storage tidak tersedia.
        }
    }, [mode]);

    /** Terapkan mode produksi setelah dikonfirmasi via dialog. */
    const applyProduction = (): void => {
        if (
            waf.form.target.includes("localhost") ||
            waf.form.target.includes("127.0.0.1")
        ) {
            waf.update({ target: "https://" });
        }
        setMode("production");
        setConfirmProduction(false);
    };

    /**
     * Ganti mode sekaligus sesuaikan target otomatis:
     * lokal → :8000, produksi → dialog konfirmasi dulu.
     */
    const changeMode = (next: ConsoleMode): void => {
        if (next === "production" && mode !== "production") {
            setConfirmProduction(true);
            return;
        }
        if (
            next === "local" &&
            (waf.form.target.trim() === "" ||
                waf.form.target.trim() === "https://")
        ) {
            waf.update({ target: "http://localhost:8000" });
        }
        setMode(next);
    };

    // Temuan dari body respons tab pertama, dipakai tab Pengujian Serangan.
    const surface = useMemo(
        () =>
            analyzeSurface(waf.result?.body ?? "", {
                baseUrl: baseUrlOf(waf.result),
                contentType: waf.result?.content_type ?? "",
            }),
        [waf.result],
    );

    const tabs = [
        { id: "nopasak", label: "⚠️ Tanpa Pasak = Jebol" },
        { id: "console", label: "Konsol Uji" },
        {
            id: "attack",
            label: "Pengujian Serangan",
            badge: surface.summary.total,
        },
    ];

    return (
        <div className={`${theme} min-h-screen bg-background text-foreground`}>
            <AppHeader
                title="WAF Test Console"
                subtitle="Laravel Security Monitor · Bulwark"
                mode={mode}
                onModeChange={changeMode}
                theme={theme}
                onThemeChange={setTheme}
            />

            <main className="mx-auto max-w-full px-5 py-6">
                {mode === "production" && (
                    <Alert variant="destructive" className="mb-6">
                        <TriangleAlertIcon data-icon="inline-start" />
                        <AlertTitle>
                            MODE PRODUCTION — target server nyata
                        </AlertTitle>
                        <AlertDescription>
                            Isi URL target dengan domain audit ber-skema{" "}
                            <code>https://</code> (mis.{" "}
                            <code>https://dummy-api.lapeh.web.id</code>).
                            Alur pengujian identik dengan mode lokal —
                            termasuk verifikasi webshell tahap 4 dan
                            eksekusi RCE (/shell). Header IP virtual
                            hanya terbaca bila server memercayai proxy;
                            atribusi IP bisa jatuh ke IP server.
                        </AlertDescription>
                    </Alert>
                )}
                <AppTabs tabs={tabs} active={view} onChange={setView} />

                {view === "nopasak" ? (
                    <NoPasakTab
                        sendRaw={waf.sendRaw}
                        target={waf.form.target}
                        onTargetChange={(target) => waf.update({ target })}
                        mode={mode}
                    />
                ) : view === "console" ? (
                    <div className="grid gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
                        <div className="flex flex-col gap-6">
                            <RequestForm
                                form={waf.form}
                                onChange={waf.update}
                                onRandomIp={waf.randomizeIp}
                                onSend={() => waf.send()}
                                onSendRandom={waf.sendWithRandomIp}
                                onBulk={waf.bulkTest}
                                onApplyVector={waf.applyVector}
                                activeVector={waf.activeVector}
                                onClearVector={waf.clearVector}
                                loading={waf.loading}
                                progress={waf.progress}
                            />
                            <HistoryTable
                                history={waf.history}
                                onClear={waf.clearHistory}
                            />
                            <ConsoleReport
                                history={waf.history}
                                target={waf.form.target}
                                surface={surface}
                            />
                        </div>

                        <ResultPanel
                            result={waf.result}
                            tab={waf.tab}
                            onTab={waf.setTab}
                            onOpenPreview={() => waf.setPreviewOpen(true)}
                            loading={waf.loading}
                            progress={waf.progress}
                        />
                    </div>
                ) : (
                    <AttackTab
                        result={waf.result}
                        findings={surface.findings}
                        sendRaw={waf.sendRaw}
                        fallbackPath={waf.form.path}
                    />
                )}

                <Separator className="mt-8" />
                <AppFooter>
                    Hanya untuk pengujian lokal. Header IP virtual dikirim oleh
                    proxy sisi server karena browser melarang JavaScript
                    menyetel <code>CF-Connecting-IP</code> /{" "}
                    <code>X-Forwarded-For</code>.
                </AppFooter>
            </main>

            <PreviewModal
                open={waf.previewOpen}
                body={waf.result?.body ?? ""}
                baseUrl={waf.result?.base_url ?? ""}
                onClose={() => waf.setPreviewOpen(false)}
            />

            <AlertDialog
                open={confirmProduction}
                onOpenChange={setConfirmProduction}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Aktifkan mode PRODUCTION?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Serangan akan mengenai server <b>NYATA</b>{" "}
                            via <code>https://</code>. Alur pengujian
                            sama persis seperti mode lokal. Lanjutkan
                            hanya dengan izin audit tertulis.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={applyProduction}>
                            Ya, aktifkan produksi
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
