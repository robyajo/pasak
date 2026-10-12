import { MoonIcon, ShieldIcon, SunIcon } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";

export type ConsoleMode = "local" | "production";

interface AppHeaderProps {
    title: string;
    subtitle?: string;
    mode: ConsoleMode;
    onModeChange: (mode: ConsoleMode) => void;
    theme: "dark" | "light";
    onThemeChange: (theme: "dark" | "light") => void;
}

export default function AppHeader({
    title,
    subtitle,
    mode,
    onModeChange,
    theme,
    onThemeChange,
}: AppHeaderProps) {
    const production = mode === "production";
    return (
        <header className="sticky top-0 backdrop-blur z-9">
            <div className="mx-auto flex max-w-full items-center justify-between gap-4 px-5 py-3">
                <div className="flex items-center gap-3">
                    <div className="grid size-10 place-items-center rounded-xl bg-primary text-primary-foreground">
                        <ShieldIcon data-icon="inline-start" />
                    </div>
                    <div>
                        <h1 className="text-base font-extrabold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            {subtitle}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger
                                render={
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon-sm"
                                        onClick={() => onThemeChange(theme === "dark" ? "light" : "dark")}
                                        aria-label={theme === "dark" ? "Ganti ke mode terang" : "Ganti ke mode gelap"}
                                    >
                                        {theme === "dark" ? <SunIcon data-icon="inline-start" /> : <MoonIcon data-icon="inline-start" />}
                                    </Button>
                                }
                            />
                            <TooltipContent>{theme === "dark" ? "Mode terang" : "Mode gelap"}</TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <Badge variant={production ? "destructive" : "secondary"}>
                        {production ? "PRODUCTION" : "Local only"}
                    </Badge>
                    <Label
                        htmlFor="mode-switch"
                        className="text-xs text-muted-foreground"
                    >
                        {production ? "Produksi" : "Lokal"}
                    </Label>
                    <Switch
                        id="mode-switch"
                        checked={production}
                        onCheckedChange={(checked) =>
                            onModeChange(
                                checked === true ? "production" : "local",
                            )
                        }
                        aria-label="Mode pengujian: lokal atau produksi"
                    />
                </div>
            </div>
            <Separator />
        </header>
    );
}
