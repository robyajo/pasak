interface PreviewModalProps { open: boolean; body: string; baseUrl?: string; onClose: () => void }
import PreviewFrame from "@/components/PreviewFrame";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

export default function PreviewModal({ open, body, baseUrl = "", onClose }: PreviewModalProps) {
  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          onClose();
        }
      }}
    >
      <DialogContent className="flex h-[85vh] max-w-5xl flex-col overflow-hidden sm:max-w-5xl">
        <DialogHeader>
          <DialogTitle>Pratinjau target (sandbox)</DialogTitle>
          <DialogDescription>
            Pratinjau target dalam bingkai terisolasi.
          </DialogDescription>
        </DialogHeader>
        <div className="min-h-0 flex-1">
          <PreviewFrame body={body} baseUrl={baseUrl} title="Pratinjau target" />
        </div>
      </DialogContent>
    </Dialog>
  );
}
