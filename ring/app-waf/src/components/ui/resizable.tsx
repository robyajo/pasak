import { GripVertical } from "lucide-react";
import * as ResizablePrimitive from "react-resizable-panels";

import * as React from "react";

import { cn } from "@/lib/utils";

function ResizablePanelGroup({
    className,
    ...props
}: ResizablePrimitive.GroupProps) {
    return (
        <ResizablePrimitive.Group
            data-slot="resizable-panel-group"
            className={cn(
                "flex h-full w-full font-base aria-[orientation=vertical]:flex-col",
                className,
            )}
            {...props}
        />
    );
}

function ResizablePanel({
    className,
    ...props
}: ResizablePrimitive.PanelProps) {
    return (
        <ResizablePrimitive.Panel
            data-slot="resizable-panel"
            className={cn("min-w-0", className)}
            {...props}
        />
    );
}

function ResizableHandle({
    withHandle,
    className,
    ...props
}: ResizablePrimitive.SeparatorProps & {
    withHandle?: boolean;
}) {
    return (
        <ResizablePrimitive.Separator
            data-slot="resizable-handle"
            className={cn(
                "relative flex w-0.5 shrink-0 flex-col items-center justify-center bg-border transition-colors hover:bg-main data-[separator=hover]:bg-main data-[separator=active]:bg-main focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-1 cursor-col-resize select-none",
                "after:absolute after:inset-y-0 after:left-1/2 after:w-4 after:-translate-x-1/2",
                "aria-[orientation=horizontal]:h-0.5 aria-[orientation=horizontal]:w-full aria-[orientation=horizontal]:flex-row aria-[orientation=horizontal]:cursor-row-resize aria-[orientation=horizontal]:after:left-0 aria-[orientation=horizontal]:after:h-4 aria-[orientation=horizontal]:after:w-full aria-[orientation=horizontal]:after:-translate-y-1/2 aria-[orientation=horizontal]:after:translate-x-0 [&[aria-orientation=horizontal]>div]:rotate-90",
                className,
            )}
            {...props}
        >
            {withHandle && (
                <div className="z-20 flex h-7 w-4.5 shrink-0 items-center justify-center rounded-base border-2 border-border bg-card text-muted-foreground shadow-xs transition-all hover:bg-main hover:text-main-foreground active:scale-95 sticky top-1/2 -translate-y-1/2">
                    <GripVertical className="size-3.5" />
                </div>
            )}
        </ResizablePrimitive.Separator>
    );
}

export { ResizablePanelGroup, ResizablePanel, ResizableHandle };
