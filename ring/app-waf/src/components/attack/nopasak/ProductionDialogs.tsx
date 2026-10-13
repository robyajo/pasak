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
import type { PendingMethodState } from "../sheet";

interface ConfirmMethodDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    pendingMethod: PendingMethodState | null;
    url: string;
    onConfirm: () => void;
}

export function ConfirmMethodDialog({
    open,
    onOpenChange,
    pendingMethod,
    url,
    onConfirm,
}: ConfirmMethodDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Eksekusi ke PRODUKSI?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Tindakan <b>{pendingMethod?.label ?? ""}</b> akan
                        dikirim ke server nyata <b>{url}</b> — menanam file /
                        mencoba eksekusi. Lanjutkan hanya bila temuan sudah
                        terbukti (tidak 403) dan dalam cakupan izin audit.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm}>
                        Ya, eksekusi
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

interface ConfirmProdDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    label: string;
    url: string;
    onConfirm: () => void;
}

export function ConfirmProdDialog({
    open,
    onOpenChange,
    label,
    url,
    onConfirm,
}: ConfirmProdDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Kirim serangan ke PRODUKSI?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        Tindakan <b>{label}</b> akan dikirim ke server nyata{" "}
                        <b>{url}</b>. Lanjutkan hanya dalam cakupan izin audit —
                        jejaknya tercatat di log server target.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm}>
                        Ya, kirim
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
