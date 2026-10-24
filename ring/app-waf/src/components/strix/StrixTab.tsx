import { useState } from "react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { useStrix } from "@/hooks/useStrix";
import { StrixConfigModal } from "./StrixConfigModal";
import { StrixDetailModal } from "./StrixDetailModal";
import { StrixLiveConsole } from "./StrixLiveConsole";
import { StrixRunsHistory } from "./StrixRunsHistory";
import { StrixScanLauncher } from "./StrixScanLauncher";
import { StrixStatusBanner } from "./StrixStatusBanner";
import { TriangleAlertIcon } from "lucide-react";

interface StrixTabProps {
    mode?: "local" | "production";
    defaultTarget?: string;
}

export default function StrixTab({
    mode = "local",
    defaultTarget = "http://localhost:8000",
}: StrixTabProps) {
    const {
        status,
        runs,
        activeJob,
        logs,
        selectedRunDetail,
        loadingDetail,
        submitting,
        stopping,
        savingConfig,
        error,
        refreshStatus,
        refreshRuns,
        loadRunDetail,
        clearRunDetail,
        startScan,
        stopScan,
        saveConfig,
        clearLogs,
        clearError,
    } = useStrix();

    const [configModalOpen, setConfigModalOpen] = useState(false);

    return (
        <div className="flex flex-col gap-6">
            {/* Banner Kesiapan & Status */}
            <StrixStatusBanner
                status={status}
                onRefresh={refreshStatus}
                onOpenConfig={() => setConfigModalOpen(true)}
            />

            {error && (
                <Alert variant="destructive" className="relative">
                    <TriangleAlertIcon className="size-4" />
                    <AlertTitle>Terjadi Kesalahan</AlertTitle>
                    <AlertDescription className="text-xs">
                        {error}
                    </AlertDescription>
                    <button
                        type="button"
                        onClick={clearError}
                        className="absolute right-3 top-3 text-xs underline opacity-80 hover:opacity-100"
                    >
                        Tutup
                    </button>
                </Alert>
            )}

            {/* Split Grid: Launcher di kiri, Live Console di kanan */}
            <div className="grid gap-6 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] items-stretch">
                <StrixScanLauncher
                    defaultTarget={defaultTarget}
                    activeJob={activeJob}
                    submitting={submitting}
                    stopping={stopping}
                    onStart={async (params) => {
                        await startScan(params);
                    }}
                    onStop={stopScan}
                />

                <StrixLiveConsole
                    job={activeJob}
                    logs={logs}
                    onClearLogs={clearLogs}
                />
            </div>

            {/* Riwayat Run & Temuan */}
            <StrixRunsHistory
                runs={runs}
                loadingDetail={loadingDetail}
                onRefresh={refreshRuns}
                onSelectRun={(name) => loadRunDetail(name)}
            />

            {/* Modal Detail Hasil Run */}
            <StrixDetailModal
                open={Boolean(selectedRunDetail)}
                detail={selectedRunDetail}
                onClose={clearRunDetail}
            />

            {/* Modal Konfigurasi Model & API Key */}
            <StrixConfigModal
                open={configModalOpen}
                config={status?.config || null}
                saving={savingConfig}
                onClose={() => setConfigModalOpen(false)}
                onSave={saveConfig}
            />
        </div>
    );
}
