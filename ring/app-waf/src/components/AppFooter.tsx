import { useState, type ReactNode } from "react";
import { ExternalLink, Globe, Shield, ShieldCheck } from "lucide-react";
import PrivacyPolicyModal from "./PrivacyPolicyModal";

interface AppFooterProps {
    children?: ReactNode;
}

export default function AppFooter({ children }: AppFooterProps) {
    const [privacyOpen, setPrivacyOpen] = useState(false);

    return (
        <footer className="mt-8 space-y-3 pb-6 text-center text-xs text-muted-foreground">
            {children && (
                <p className="max-w-3xl mx-auto leading-relaxed">{children}</p>
            )}

            <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 pt-2 border-t border-border/50 text-[11px]">
                <div className="flex items-center gap-1.5 font-medium text-foreground/80">
                    <Shield className="size-3.5 text-primary" />
                    <span>
                        Dikembangkan oleh{" "}
                        <strong className="font-semibold text-foreground">
                            Roby Karti S
                        </strong>
                    </span>
                </div>

                <span className="text-muted-foreground/40">•</span>

                <a
                    href="https://pasak.is-a.dev"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 hover:text-primary transition-colors underline-offset-4 hover:underline"
                >
                    <Globe className="size-3" />
                    <span>Dokumentasi Resmi (pasak.is-a.dev)</span>
                    <ExternalLink className="size-2.5 opacity-60" />
                </a>

                <span className="text-muted-foreground/40">•</span>

                <a
                    href="https://github.com/robyajo/pasak"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 hover:text-primary transition-colors underline-offset-4 hover:underline"
                >
                    <span>GitHub Repositori</span>
                    <ExternalLink className="size-2.5 opacity-60" />
                </a>

                <span className="text-muted-foreground/40">•</span>

                <button
                    type="button"
                    onClick={() => setPrivacyOpen(true)}
                    className="inline-flex items-center gap-1 text-primary font-medium hover:underline cursor-pointer"
                >
                    <ShieldCheck className="size-3" />
                    <span>Kebijakan &amp; Privasi</span>
                </button>
            </div>

            <PrivacyPolicyModal
                open={privacyOpen}
                onOpenChange={setPrivacyOpen}
            />
        </footer>
    );
}
