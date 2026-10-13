import { useEffect, useState, type ReactNode } from "react";
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from "@/components/ui/resizable";

interface PreviewSplitProps {
    left: ReactNode;
    right: ReactNode;
}

/**
 * Pembagi geser konten audit vs preview korban: berdampingan + bisa
 * digeser pada layar lebar (preview maks 50%), susun vertikal di HP.
 */
export function PreviewSplit({ left, right }: PreviewSplitProps) {
    const [wide, setWide] = useState(
        () =>
            typeof window === "undefined" ||
            window.matchMedia("(min-width: 1024px)").matches,
    );

    useEffect(() => {
        const mq = window.matchMedia("(min-width: 1024px)");
        const onChange = (): void => setWide(mq.matches);
        mq.addEventListener("change", onChange);
        return () => mq.removeEventListener("change", onChange);
    }, []);

    if (!wide) {
        return (
            <div className="grid items-start gap-4">
                {left}
                {right}
            </div>
        );
    }

    return (
        <ResizablePanelGroup orientation="horizontal" className="w-full">
            <ResizablePanel
                defaultSize={55}
                className="min-w-0 pr-2 lg:pr-3"
            >
                {left}
            </ResizablePanel>
            <ResizableHandle
                withHandle
                className="mx-1"
            />
            <ResizablePanel
                defaultSize={45}
                className="min-w-0 pl-2 lg:pl-3"
            >
                {right}
            </ResizablePanel>
        </ResizablePanelGroup>
    );
}
